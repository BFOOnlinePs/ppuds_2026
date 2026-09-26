<?php

namespace Modules\PPUDS\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\User;
use Modules\Core\Services\PdfService;
use Modules\PPUDS\Entities\FinalReport;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\AttendanceStatus;
use Modules\PPUDS\Enums\FinalReportItemType;
use Modules\PPUDS\Enums\FinalReportStatus;
use Modules\PPUDS\Enums\ReportStatus;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * يجمع منطق تسليم التقرير النهائي حتى تستخدمه الشاشة والـ API معاً.
 */
class FinalReportService
{
    /**
     * حالة التقارير في إعدادات النظام. عند الإغلاق يختفي العنصر من القائمة
     * الجانبية ويُمنع الحفظ والتسليم من الشاشة والـ API معاً.
     */
    public function submissionIsOpen(): bool
    {
        return app(GeneralSettings::class)->report_status === ReportStatus::OPEN;
    }

    /**
     * تسجيل الطالب في الفصل الحالي، وهو المرساة التي يُعلَّق عليها التقرير.
     */
    public function currentRegistrationFor(User|int $student): ?Registration
    {
        $studentId = $student instanceof User ? $student->id : $student;
        $settings = app(GeneralSettings::class);

        return Registration::query()
            ->where('student_id', $studentId)
            ->where('year', $settings->year)
            ->where('semester', $settings->semester_type->value)
            ->latest('id')
            ->first();
    }

    public function reportForRegistration(Registration|int $registration): ?FinalReport
    {
        $registrationId = $registration instanceof Registration ? $registration->id : $registration;

        return FinalReport::query()
            ->with(['tasks', 'skills', 'items', 'registration.media'])
            ->where('registration_id', $registrationId)
            ->first();
    }

    /**
     * المرفق اختياري ويُخزَّن في مجموعة final_file الموجودة أصلاً على التسجيل،
     * حتى تبقى الملفات المرفوعة سابقاً ظاهرة كما هي.
     */
    public function saveAttachment(Registration $registration, mixed $file): bool
    {
        if (blank($file)) {
            return true;
        }

        return $registration->addImage($file) !== null;
    }

    public function attachmentUrl(?Registration $registration): ?string
    {
        return $registration?->hasMedia('final_file')
            ? $registration->getFirstMediaUrl('final_file')
            : null;
    }

    /**
     * العرض التقديمي إجباري، لكن الحفظ هنا يتجاهل الطلب الخالي من ملف جديد
     * حتى لا يفقد الطالب العرض الذي رفعه سابقاً عند تعديل بقية الحقول.
     */
    public function savePresentation(Registration $registration, mixed $file): bool
    {
        if (blank($file)) {
            return true;
        }

        return $registration->addPresentation($file) !== null;
    }

    public function presentationUrl(?Registration $registration): ?string
    {
        return $registration?->hasMedia(Registration::PRESENTATION_COLLECTION)
            ? $registration->getFirstMediaUrl(Registration::PRESENTATION_COLLECTION)
            : null;
    }

    /**
     * يستخدمها التحقق والتسليم معاً: العرض التقديمي مطلوب مرة واحدة فقط،
     * فإن كان مرفوعاً من قبل لا يُطلب من الطالب رفعه في كل حفظ.
     */
    public function hasPresentation(?Registration $registration): bool
    {
        return (bool) $registration?->hasMedia(Registration::PRESENTATION_COLLECTION);
    }

    /**
     * تقرير الطالب في الفصل الحالي إن وُجد.
     */
    public function currentReportFor(User|int $student): ?FinalReport
    {
        $registration = $this->currentRegistrationFor($student);

        return $registration ? $this->reportForRegistration($registration) : null;
    }

    /**
     * إنشاء التقرير أو تحديثه مع صفوف الجداول. الصفوف تُستبدل بالكامل
     * لأن الواجهة ترسل الجدول كما هو بعد كل تعديل.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(Registration $registration, array $data, User|int $author): FinalReport
    {
        $authorId = $author instanceof User ? $author->id : $author;

        return DB::transaction(function () use ($registration, $data, $authorId): FinalReport {
            $report = FinalReport::query()->firstOrNew(['registration_id' => $registration->id]);

            $report->fill([
                'registration_id' => $registration->id,
                'student_id' => $registration->student_id,
                'role_description' => $data['role_description'] ?? $report->role_description,
                'summary' => $data['summary'] ?? $report->summary,
            ]);

            if (! $report->exists) {
                $report->status = FinalReportStatus::DRAFT;
                $report->created_by = $authorId;
            }

            $report->save();

            if (array_key_exists('tasks', $data)) {
                $this->syncTasks($report, $data['tasks'] ?? []);
            }

            if (array_key_exists('skills', $data)) {
                $this->syncSkills($report, $data['skills'] ?? []);
            }

            if (array_key_exists('contributions', $data)) {
                $this->syncItems($report, FinalReportItemType::CONTRIBUTION, $data['contributions'] ?? []);
            }

            if (array_key_exists('difficulties', $data)) {
                $this->syncItems($report, FinalReportItemType::DIFFICULTY, $data['difficulties'] ?? []);
            }

            return $report->load(['tasks', 'skills', 'items', 'registration.media']);
        });
    }

    public function submit(FinalReport $report): FinalReport
    {
        $report->forceFill([
            'status' => FinalReportStatus::SUBMITTED,
            'submitted_at' => now(),
        ])->save();

        return $report->load(['tasks', 'skills', 'items', 'registration.media']);
    }

    /**
     * ملف التقرير بالنموذج الرسمي، نفسه للطالب وللمشرف ولتطبيق الموبايل.
     * الطباعة عرض فقط ولا تغيّر الحالة، فهي متاحة للمسودة وللتقرير المسلَّم معاً.
     */
    public function pdf(FinalReport $report)
    {
        return app(PdfService::class)->streamPdf(
            'ppuds::pdf.final-report.report',
            $this->pdfData($report->loadMissing(['tasks', 'skills', 'items', 'registration'])),
            'final-report-'.now()->format('Y-m-d-His').'.pdf',
        );
    }

    /**
     * بيانات النموذج الرسمي. ما يعرفه النظام يُملأ، وما لا يخزّنه يبقى خانة
     * فارغة في الورقة ليُكتب بخط اليد كما في النموذج المعتمد.
     *
     * @return array<string, mixed>
     */
    protected function pdfData(FinalReport $report): array
    {
        $registration = $report->registration;
        $studentCompany = $registration?->studentCompany;
        $company = $studentCompany?->company;
        $settings = app(GeneralSettings::class);

        return [
            'report' => $report,
            'registration' => $registration,
            'student' => $registration?->student,
            'company' => $company,
            'branch' => $studentCompany?->branch,
            // بلا fallback، وإلا ظهر الاسم العربي في خانة الاسم الإنجليزي.
            'companyNameAr' => $company?->translate('ar')?->name,
            'companyNameEn' => $company?->translate('en')?->name,
            'contributions' => $report->items->where('type', FinalReportItemType::CONTRIBUTION)->values(),
            'difficulties' => $report->items->where('type', FinalReportItemType::DIFFICULTY)->values(),
            'stats' => $this->trainingStats($report, $studentCompany),
            'trainingPeriod' => $settings->start_semester->format('j/n/Y').' – '.$settings->end_semester->format('j/n/Y'),
            'academicYear' => $settings->year.'/'.($settings->year + 1),
        ];
    }

    /**
     * إحصائية التدريب كما تحتسبها شاشة الغياب. الساعات من بصمات الحضور نفسها،
     * وتُجمع من كل تدريبات الطالب لا من تدريبه الحالي وحده، فمن انتقل بين
     * أكثر من شركة تظهر ساعاته كلها.
     *
     * @return array<string, int|float|string>
     */
    protected function trainingStats(FinalReport $report, ?StudentCompany $studentCompany): array
    {
        $minutes = StudentAttendance::query()
            ->whereHas('studentCompany', fn (Builder $query): Builder => $query->where('student_id', $report->student_id))
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->where('status', '!=', AttendanceStatus::DISCREPANCY->value)
            ->get(['check_in', 'check_out'])
            ->sum(fn (StudentAttendance $attendance): int => $attendance->check_in->diffInMinutes($attendance->check_out));

        if (! $studentCompany) {
            return ['days' => '', 'hours' => round($minutes / 60, 2), 'leaves' => ''];
        }

        $summary = app(AbsenceReportService::class)->summary($studentCompany);

        return [
            'days' => $summary['attendance_days'] ?? 0,
            'hours' => round($minutes / 60, 2),
            'leaves' => $summary['excused_absence_days'] ?? 0,
        ];
    }

    /**
     * تُستخدم في شاشة المتابعة للأدمن لمعرفة من سلّم تقريره.
     */
    public function scopeSubmittedRegistrations(Builder $query): Builder
    {
        return $query->whereIn(
            'registration_id',
            FinalReport::query()
                ->select('registration_id')
                ->where('status', FinalReportStatus::SUBMITTED)
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     */
    protected function syncTasks(FinalReport $report, array $tasks): void
    {
        $report->tasks()->delete();

        foreach (array_values($tasks) as $index => $task) {
            if (blank($task['task_name'] ?? null)) {
                continue;
            }

            $report->tasks()->create([
                'task_name' => $task['task_name'],
                'task_details' => $task['task_details'] ?? null,
                'work_duration' => $task['work_duration'] ?? null,
                'notes' => $task['notes'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $skills
     */
    protected function syncSkills(FinalReport $report, array $skills): void
    {
        $report->skills()->delete();

        foreach (array_values($skills) as $index => $skill) {
            if (blank($skill['skill'] ?? null)) {
                continue;
            }

            $report->skills()->create([
                'skill' => $skill['skill'],
                'mastery_percentage' => $skill['mastery_percentage'] ?? null,
                'notes' => $skill['notes'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $items
     */
    protected function syncItems(FinalReport $report, FinalReportItemType $type, array $items): void
    {
        $report->items()->where('type', $type)->delete();

        foreach (array_values($items) as $index => $item) {
            $content = is_array($item) ? ($item['content'] ?? null) : $item;

            if (blank($content)) {
                continue;
            }

            $report->items()->create([
                'type' => $type,
                'content' => $content,
                'sort_order' => $index,
            ]);
        }
    }
}
