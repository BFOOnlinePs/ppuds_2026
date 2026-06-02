<?php

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Modules\Core\Actions\StudentCompanyAssistant\FindStudentsForCompanyAssistant;
use Modules\Core\Ai\StudentTrainingChatAgent;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentReport;
use Modules\PPUDS\Enums\AttendanceStatus;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Settings\GeneralSettings as PPUDSSettings;
use Throwable;

class StudentTrainingChatService
{
    public function answer(string $question, ?int $currentStudentId = null): array
    {
        $question = trim($question);
        [$student, $matches] = $this->resolveStudent($question, $currentStudentId);

        if (! $student && $matches->isNotEmpty()) {
            return [
                'message' => 'وجدت أكثر من طالب مطابق. اختر الطالب المطلوب حتى أجيبك عن معلوماته وتدريبه.',
                'student_matches' => $matches->map(fn (User $student): array => $this->studentMatchPayload($student))->values()->all(),
                'used_ai' => false,
            ];
        }

        if (! $student) {
            return [
                'message' => 'لم أجد طالبًا مطابقًا في النظام. جرّب كتابة الاسم الكامل أو الرقم الجامعي.',
                'student_matches' => [],
                'used_ai' => false,
            ];
        }

        return $this->answerForLoadedStudent($question, $student);
    }

    public function answerForStudent(string $question, int $studentId): array
    {
        $student = $this->loadStudent($studentId);

        if (! $student) {
            return [
                'message' => 'تعذر العثور على الطالب المختار.',
                'student_matches' => [],
                'used_ai' => false,
            ];
        }

        return $this->answerForLoadedStudent($question, $student);
    }

    public function studentLookupTerm(string $question): string
    {
        return $this->studentSearchTerm($question);
    }

    private function answerForLoadedStudent(string $question, User $student): array
    {
        $payload = $this->studentTrainingPayload($student);
        $message = $this->fallbackAnswer($question, $payload);
        $usedAi = false;

        if ($this->aiIsConfigured()) {
            try {
                $response = StudentTrainingChatAgent::make()->prompt(
                    $this->prompt($question, $payload),
                    provider: $this->configuredProvider(),
                    model: $this->configuredModel(),
                    timeout: $this->configuredTimeout(),
                );

                $aiMessage = trim($response->text);

                if ($aiMessage !== '') {
                    $message = $aiMessage;
                    $usedAi = true;
                }
            } catch (Throwable $exception) {
                Log::warning('Student training chat AI answer failed.', [
                    'student_id' => $student->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'message' => $message,
            'student_id' => $student->id,
            'student_name' => $student->name,
            'registration_label' => $this->contextRegistrationLabel($payload),
            'student_matches' => [],
            'context' => $this->contextCard($payload),
            'used_ai' => $usedAi,
        ];
    }

    private function resolveStudent(string $question, ?int $currentStudentId): array
    {
        $searchTerm = $this->studentSearchTerm($question);
        $matches = collect();

        if (filled($searchTerm)) {
            $finder = app(FindStudentsForCompanyAssistant::class);
            $matches = $finder->handle($searchTerm, 8);
            $exactMatch = $finder->exactMatch($matches, $searchTerm);

            if ($exactMatch) {
                return [$this->loadStudent($exactMatch->id), collect()];
            }

            if ($matches->count() === 1) {
                return [$this->loadStudent($matches->first()->id), collect()];
            }

            if ($matches->count() > 1) {
                return [null, $matches];
            }

            if ($this->looksLikeExplicitStudentLookup($question)) {
                return [null, collect()];
            }
        }

        if ($currentStudentId) {
            return [$this->loadStudent($currentStudentId), collect()];
        }

        return [null, collect()];
    }

    private function loadStudent(int $studentId): ?User
    {
        return User::query()
            ->with('studentProfile.major')
            ->whereHas('studentProfile')
            ->find($studentId);
    }

    private function studentSearchTerm(string $question): string
    {
        if (preg_match('/\b\d{3,}\b/u', $question, $matches)) {
            return $matches[0];
        }

        $cleaned = Str::lower($question);
        $cleaned = preg_replace('/[^\p{Arabic}\p{L}\p{N}@._\-\s]+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned);

        $stopWords = [
            'اعطيني', 'اعطني', 'جيب', 'هات', 'بدي', 'اريد', 'ممكن', 'شو', 'ما', 'هي', 'هو',
            'كم', 'وين', 'اين', 'أين', 'ايش', 'إيش', 'آخر', 'اخر', 'احدث', 'أحدث', 'له', 'لها',
            'اقترح', 'اقتراح', 'رشح', 'ترشيح', 'افضل', 'أفضل', 'مناسب', 'مناسبة',
            'معلومات', 'تفاصيل', 'استعلام', 'عن', 'على', 'في', 'من', 'الى', 'إلى',
            'طالب', 'الطالب', 'طالبة', 'الطالبة', 'لطالب', 'للطالب', 'للطالبة', 'اسمه', 'اسمها', 'رقم', 'الرقم', 'الجامعي',
            'تدريب', 'التدريب', 'يتدرب', 'بتدرب', 'تدرب', 'شركته', 'شركة', 'الشركة', 'شركات',
            'حضور', 'الحضور', 'غياب', 'الغياب', 'دوام', 'الدوام', 'طلبات', 'طلب',
            'مشرف', 'المشرف', 'زيارة', 'زيارات', 'تقرير', 'تقارير', 'حالة', 'علامة', 'تقييم',
        ];

        $words = collect(preg_split('/\s+/u', $cleaned) ?: [])
            ->map(fn (string $word): string => trim($word))
            ->map(fn (string $word): string => $this->normalizeLookupWord($word))
            ->filter(fn (string $word): bool => mb_strlen($word) >= 2 && ! in_array($word, $stopWords, true))
            ->values();

        return Str::limit($words->implode(' '), 120, '');
    }

    private function normalizeLookupWord(string $word): string
    {
        if (preg_match('/^ل[أإآا]/u', $word) === 1) {
            return mb_substr($word, 1);
        }

        if (str_starts_with($word, 'لل')) {
            return mb_substr($word, 1);
        }

        return $word;
    }

    private function looksLikeExplicitStudentLookup(string $question): bool
    {
        return preg_match('/\b\d{3,}\b/u', $question) === 1
            || str_contains($question, 'طالب')
            || str_contains($question, 'الطالب')
            || str_contains($question, 'طالبة')
            || str_contains($question, 'الطالبة');
    }

    private function studentTrainingPayload(User $student): array
    {
        $student->loadMissing('studentProfile.major');

        $registrations = $this->registrations($student);
        $currentRegistration = $this->currentRegistration($student) ?? $registrations->first();
        $trainingRecords = $this->trainingRecords($student, $currentRegistration);
        $settings = app(PPUDSSettings::class);

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'name_en' => $student->name_en,
                'email' => $student->email,
                'phone' => $student->phone,
                'student_number' => $student->studentProfile?->student_number,
                'major' => $student->studentProfile?->major?->name,
                'gender' => $this->enumLabel($student->studentProfile?->gender),
                'semester_level' => $student->studentProfile?->semester_level,
                'enrollment_year' => $student->studentProfile?->enrollment_year,
                'linkedin_url' => $student->studentProfile?->linkedin_url,
                'behance_url' => $student->studentProfile?->behance_url,
                'github_url' => $student->studentProfile?->github_url,
            ],
            'settings' => [
                'semester' => $settings->semester_type?->getLabel() ?? $settings->semester_type?->value,
                'year' => $settings->year,
                'start_semester' => $this->dateValue($settings->start_semester),
                'end_semester' => $this->dateValue($settings->end_semester),
            ],
            'current_registration' => $currentRegistration ? $this->registrationPayload($currentRegistration) : null,
            'registrations' => $registrations->map(fn (Registration $registration): array => $this->registrationPayload($registration))->values()->all(),
            'training_records' => $trainingRecords->map(fn (StudentCompany $studentCompany): array => $this->trainingRecordPayload($studentCompany))->values()->all(),
        ];
    }

    private function registrations(User $student): Collection
    {
        return Registration::query()
            ->with(['course', 'supervisor'])
            ->where('student_id', $student->id)
            ->orderByDesc('year')
            ->latest('id')
            ->limit(5)
            ->get();
    }

    private function currentRegistration(User $student): ?Registration
    {
        $settings = app(PPUDSSettings::class);
        $semester = $settings->semester_type?->value;
        $year = $settings->year;

        return Registration::query()
            ->with(['course', 'supervisor'])
            ->where('student_id', $student->id)
            ->when($semester, fn ($query) => $query->where('semester', $semester))
            ->when($year, fn ($query) => $query->where('year', $year))
            ->latest('id')
            ->first();
    }

    private function trainingRecords(User $student, ?Registration $currentRegistration): Collection
    {
        $query = StudentCompany::query()
            ->with([
                'registration.course',
                'registration.supervisor',
                'company.category',
                'branch.city',
                'branch.country',
                'department',
            ])
            ->where('student_id', $student->id);

        if ($currentRegistration) {
            $currentRecords = (clone $query)
                ->where('registration_id', $currentRegistration->id)
                ->latest('id')
                ->limit(5)
                ->get();

            if ($currentRecords->isNotEmpty()) {
                return $currentRecords;
            }
        }

        return $query
            ->latest('id')
            ->limit(5)
            ->get();
    }

    private function registrationPayload(Registration $registration): array
    {
        $registration->loadMissing(['course', 'supervisor']);

        return [
            'id' => $registration->id,
            'course' => $registration->course?->name,
            'semester' => $registration->semester?->getLabel() ?? $registration->semester?->value,
            'year' => $registration->year,
            'grade' => $registration->grade,
            'university_score' => $registration->university_score,
            'company_score' => $registration->company_score,
            'supervisor' => $registration->supervisor?->name,
        ];
    }

    private function trainingRecordPayload(StudentCompany $studentCompany): array
    {
        $studentCompany->loadMissing([
            'registration.course',
            'registration.supervisor',
            'company.category',
            'branch.city',
            'branch.country',
            'department',
        ]);

        return [
            'id' => $studentCompany->id,
            'status' => $this->enumLabel($studentCompany->status),
            'company' => [
                'name' => $studentCompany->company?->name,
                'category' => $studentCompany->company?->category?->name,
                'website' => $studentCompany->company?->website,
            ],
            'branch' => [
                'name' => $studentCompany->branch?->name,
                'city' => $studentCompany->branch?->city?->name,
                'country' => $studentCompany->branch?->country?->name,
                'email' => $studentCompany->branch?->email,
                'phone' => $studentCompany->branch?->phone,
            ],
            'department' => $studentCompany->department?->name,
            'registration' => $studentCompany->registration ? $this->registrationPayload($studentCompany->registration) : null,
            'attendance' => $this->attendanceSummary($studentCompany),
            'leave_requests' => $this->leaveRequestSummary($studentCompany),
            'field_visits' => $this->fieldVisitSummary($studentCompany),
            'reports' => $this->reportSummary($studentCompany),
        ];
    }

    private function attendanceSummary(StudentCompany $studentCompany): array
    {
        $baseQuery = StudentAttendance::query()->where('student_company_id', $studentCompany->id);
        $lastAttendance = (clone $baseQuery)
            ->latest('attendance_date')
            ->latest('id')
            ->first();
        $totalMinutes = (clone $baseQuery)
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->selectRaw('COALESCE(SUM(TIMESTAMPDIFF(MINUTE, check_in, check_out)), 0) as total_minutes')
            ->value('total_minutes');

        return [
            'records_count' => (clone $baseQuery)->count(),
            'attendance_days' => (clone $baseQuery)
                ->whereNotNull('attendance_date')
                ->whereNotNull('check_in')
                ->distinct('attendance_date')
                ->count('attendance_date'),
            'completed_days' => (clone $baseQuery)
                ->whereNotNull('check_in')
                ->whereNotNull('check_out')
                ->count(),
            'missing_checkout_count' => (clone $baseQuery)
                ->whereNotNull('check_in')
                ->whereNull('check_out')
                ->count(),
            'approved_count' => (clone $baseQuery)->where('status', AttendanceStatus::APPROVED->value)->count(),
            'discrepancy_count' => (clone $baseQuery)->where('status', AttendanceStatus::DISCREPANCY->value)->count(),
            'undetermined_count' => (clone $baseQuery)->where('status', AttendanceStatus::UNDETERMINED->value)->count(),
            'actual_working_hours' => round(((int) $totalMinutes) / 60, 2),
            'last_attendance' => $lastAttendance ? [
                'date' => $this->dateValue($lastAttendance->attendance_date),
                'check_in' => $this->timeValue($lastAttendance->check_in),
                'check_out' => $this->timeValue($lastAttendance->check_out),
                'status' => $this->enumLabel($lastAttendance->status),
            ] : null,
        ];
    }

    private function leaveRequestSummary(StudentCompany $studentCompany): array
    {
        $baseQuery = LeaveRequest::query()->where('student_company_id', $studentCompany->id);
        $approved = LeaveRequestStatus::APPROVED->value;
        $pending = LeaveRequestStatus::PENDING->value;
        $rejected = LeaveRequestStatus::REJECTED->value;

        return [
            'total' => (clone $baseQuery)->count(),
            'fully_approved' => (clone $baseQuery)
                ->where('company_approval', $approved)
                ->where('university_approval', $approved)
                ->count(),
            'pending' => (clone $baseQuery)
                ->where(fn ($query) => $query
                    ->where('company_approval', $pending)
                    ->orWhere('university_approval', $pending))
                ->count(),
            'rejected' => (clone $baseQuery)
                ->where(fn ($query) => $query
                    ->where('company_approval', $rejected)
                    ->orWhere('university_approval', $rejected))
                ->count(),
            'latest' => (clone $baseQuery)
                ->latest('start_at')
                ->limit(3)
                ->get()
                ->map(fn (LeaveRequest $request): array => [
                    'type' => $this->enumLabel($request->type),
                    'start_at' => $this->dateValue($request->start_at),
                    'end_at' => $this->dateValue($request->end_at),
                    'reason' => Str::limit((string) $request->reason, 160),
                    'company_approval' => $this->enumLabel($request->company_approval),
                    'university_approval' => $this->enumLabel($request->university_approval),
                ])
                ->values()
                ->all(),
        ];
    }

    private function fieldVisitSummary(StudentCompany $studentCompany): array
    {
        $visits = FieldVisit::query()
            ->with('supervisor')
            ->where('student_company_id', $studentCompany->id)
            ->latest('visit_date')
            ->latest('id')
            ->limit(3)
            ->get();

        return [
            'total' => FieldVisit::query()->where('student_company_id', $studentCompany->id)->count(),
            'latest' => $visits
                ->map(fn (FieldVisit $visit): array => [
                    'date' => $this->dateValue($visit->visit_date),
                    'time' => $visit->visit_time,
                    'place' => $visit->visiting_place,
                    'duration' => $visit->visit_duration,
                    'supervisor' => $visit->supervisor?->name,
                    'notes' => Str::limit((string) $visit->notes, 160),
                ])
                ->values()
                ->all(),
        ];
    }

    private function reportSummary(StudentCompany $studentCompany): array
    {
        $baseQuery = StudentReport::query()
            ->whereHas('studentAttendance', fn ($query) => $query->where('student_company_id', $studentCompany->id));

        return [
            'total' => (clone $baseQuery)->count(),
            'latest' => (clone $baseQuery)
                ->latest('id')
                ->limit(3)
                ->get()
                ->map(fn (StudentReport $report): array => [
                    'report_text' => Str::limit((string) $report->report_text, 180),
                    'company_feedback' => Str::limit((string) $report->company_feedback, 160),
                    'academic_feedback' => Str::limit((string) $report->academic_feedback, 160),
                    'created_at' => $this->dateValue($report->created_at),
                ])
                ->values()
                ->all(),
        ];
    }

    private function prompt(string $question, array $payload): string
    {
        return json_encode([
            'question' => $question,
            'system_data' => $payload,
            'response_style' => [
                'language' => 'ar',
                'tone' => 'professional_chat',
                'format' => 'short_paragraphs_and_bullets',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function fallbackAnswer(string $question, array $payload): string
    {
        $student = $payload['student'];
        $registration = $payload['current_registration'];
        $training = collect($payload['training_records'])->first();
        $lines = [
            "أكيد. هذه خلاصة بيانات {$student['name']}:",
            '',
            'بيانات الطالب:',
            '- الرقم الجامعي: '.($student['student_number'] ?: 'غير مسجل'),
            '- التخصص: '.($student['major'] ?: 'غير مسجل'),
            '- البريد: '.($student['email'] ?: 'غير مسجل'),
            '- الهاتف: '.($student['phone'] ?: 'غير مسجل'),
        ];

        if ($registration) {
            $lines = [
                ...$lines,
                '',
                'التسجيل الحالي:',
                '- المساق: '.($registration['course'] ?: 'غير مسجل'),
                '- الفصل/السنة: '.trim(($registration['semester'] ?: '-').'/'.($registration['year'] ?: '-')),
                '- المشرف الأكاديمي: '.($registration['supervisor'] ?: 'غير مسجل'),
                '- علامة الجامعة: '.($registration['university_score'] ?? 'غير مسجلة'),
                '- علامة الشركة: '.($registration['company_score'] ?? 'غير مسجلة'),
            ];
        } else {
            $lines[] = '';
            $lines[] = 'لا يوجد تسجيل تدريبي ظاهر لهذا الطالب حسب بيانات النظام.';
        }

        if ($training) {
            $attendance = $training['attendance'];
            $leaveRequests = $training['leave_requests'];
            $fieldVisits = $training['field_visits'];
            $reports = $training['reports'];

            $lines = [
                ...$lines,
                '',
                'معلومات التدريب:',
                '- الشركة: '.($training['company']['name'] ?: 'غير مربوطة'),
                '- الفرع: '.($training['branch']['name'] ?: 'غير مسجل'),
                '- القسم: '.($training['department'] ?: 'غير مسجل'),
                '- حالة التدريب: '.($training['status'] ?: 'غير مسجلة'),
                '',
                'الحضور والدوام:',
                '- أيام الحضور المسجلة: '.$attendance['attendance_days'],
                '- أيام مكتملة دخول/خروج: '.$attendance['completed_days'],
                '- ساعات العمل الفعلية: '.$attendance['actual_working_hours'],
                '- سجلات بدون مغادرة: '.$attendance['missing_checkout_count'],
                '- آخر حضور: '.$this->lastAttendanceLine($attendance['last_attendance']),
                '',
                'الغياب والزيارات والتقارير:',
                '- طلبات الغياب: '.$leaveRequests['total'].'، المعتمدة بالكامل: '.$leaveRequests['fully_approved'].'، المعلقة: '.$leaveRequests['pending'].'، المرفوضة: '.$leaveRequests['rejected'],
                '- الزيارات الميدانية: '.$fieldVisits['total'],
                '- التقارير اليومية: '.$reports['total'],
            ];
        } else {
            $lines[] = '';
            $lines[] = 'لم أجد شركة تدريب مربوطة بهذا التسجيل.';
        }

        return implode("\n", $lines);
    }

    private function contextCard(array $payload): array
    {
        $student = $payload['student'];
        $training = collect($payload['training_records'])->first();

        return [
            'student_name' => $student['name'],
            'student_number' => $student['student_number'],
            'major' => $student['major'],
            'registration' => $this->contextRegistrationLabel($payload),
            'company' => $training['company']['name'] ?? null,
            'branch' => $training['branch']['name'] ?? null,
            'department' => $training['department'] ?? null,
            'status' => $training['status'] ?? null,
            'attendance_days' => $training['attendance']['attendance_days'] ?? null,
            'leave_requests' => $training['leave_requests']['total'] ?? null,
        ];
    }

    private function contextRegistrationLabel(array $payload): ?string
    {
        $registration = $payload['current_registration'] ?? null;

        if (! $registration) {
            return null;
        }

        return trim(($registration['course'] ?: 'بدون مساق').' - '.($registration['semester'] ?: '-').'/'.($registration['year'] ?: '-'));
    }

    private function studentMatchPayload(User $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'student_number' => $student->studentProfile?->student_number,
            'major' => $student->studentProfile?->major?->name,
            'email' => $student->email,
        ];
    }

    private function lastAttendanceLine(?array $lastAttendance): string
    {
        if (! $lastAttendance) {
            return 'غير مسجل';
        }

        return trim(($lastAttendance['date'] ?: '-').'، دخول: '.($lastAttendance['check_in'] ?: '-').'، خروج: '.($lastAttendance['check_out'] ?: '-').'، الحالة: '.($lastAttendance['status'] ?: '-'));
    }

    private function enumLabel(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (is_object($value) && method_exists($value, 'getLabel')) {
            return $value->getLabel();
        }

        return (string) $value;
    }

    private function dateValue(mixed $date): ?string
    {
        return $date && method_exists($date, 'format') ? $date->format('Y-m-d') : ($date ? (string) $date : null);
    }

    private function timeValue(mixed $time): ?string
    {
        return $time && method_exists($time, 'format') ? $time->format('H:i') : ($time ? (string) $time : null);
    }

    private function aiIsConfigured(): bool
    {
        $provider = $this->configuredProvider() ?? config('ai.default');

        if ($provider instanceof Lab) {
            $provider = $provider->value;
        }

        if (is_array($provider)) {
            return collect($provider)
                ->map(fn ($model, $providerName) => is_int($providerName) ? $model : $providerName)
                ->contains(fn ($providerName) => $this->providerHasCredentials((string) $providerName));
        }

        return $this->providerHasCredentials((string) $provider);
    }

    private function providerHasCredentials(string $provider): bool
    {
        if ($provider === 'ollama') {
            return filled(config('ai.providers.ollama.url'));
        }

        return filled(config("ai.providers.{$provider}.key"));
    }

    private function configuredProvider(): Lab|array|string|null
    {
        $provider = config('ai.student_training_chat.provider');

        return blank($provider) ? null : $provider;
    }

    private function configuredModel(): ?string
    {
        $model = config('ai.student_training_chat.model');

        return blank($model) ? null : $model;
    }

    private function configuredTimeout(): int
    {
        return (int) config('ai.student_training_chat.timeout', 60);
    }
}
