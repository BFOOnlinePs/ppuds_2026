<?php

namespace Modules\PPUDS\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\FinalReport;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Enums\FinalReportItemType;
use Modules\PPUDS\Enums\FinalReportStatus;
use Modules\PPUDS\Settings\GeneralSettings;

/**
 * يجمع منطق تسليم التقرير النهائي حتى تستخدمه الشاشة والـ API معاً.
 */
class FinalReportService
{
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
            ->with(['tasks', 'skills', 'items'])
            ->where('registration_id', $registrationId)
            ->first();
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

            return $report->load(['tasks', 'skills', 'items']);
        });
    }

    public function submit(FinalReport $report): FinalReport
    {
        $report->forceFill([
            'status' => FinalReportStatus::SUBMITTED,
            'submitted_at' => now(),
        ])->save();

        return $report->load(['tasks', 'skills', 'items']);
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
