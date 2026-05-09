<?php

namespace Modules\PPUDS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Enums\CourseStatus;
use Modules\PPUDS\Enums\CourseType;
use Modules\PPUDS\Services\PpuApiService;

class ProcessStudentCourseSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    protected $token;

    protected $academicYear;

    protected $semesterNo;

    protected $initiatorId;

    public function __construct(array $data, string $token, $academicYear, $semesterNo, ?int $initiatorId = null)
    {
        $this->data = $data;
        $this->token = $token;
        $this->academicYear = $academicYear;
        $this->semesterNo = $semesterNo;
        $this->initiatorId = $initiatorId;
    }

    public function handle(): int
    {
        $studentNumber = $this->data['studentNo'] ?? null;
        if (!$studentNumber) return 0;

        $studentName = $this->data['studentNameArabic'] ?? $this->data['studentNameEnglish'] ?? $studentNumber;

        $studentProfile = StudentProfile::where('student_number', $studentNumber)->first();
        if (!$studentProfile) {
            PpuApiService::logToTerminal("✗ لم يتم العثور على ملف الطالب {$studentName} في النظام", $this->initiatorId);
            return 0;
        }

        try {
            $url = "https://api-core.ppu.edu/api/DualStudies/getStudentPracticalCourses/{$studentNumber}";

            try {
                $response = Http::withHeaders(['Accept' => 'application/json'])
                    ->withToken($this->token)
                    ->retry(3, 1000, throw: false)
                    ->connectTimeout(15)
                    ->timeout(45)
                    ->get($url, [
                        'academicYear' => $this->academicYear,
                        'semesterNo' => $this->semesterNo,
                    ]);
            } catch (\Exception $e) {
                PpuApiService::logToTerminal("✗ تعذر الاتصال لجلب مقررات الطالب {$studentName}: " . $e->getMessage(), $this->initiatorId);
                Log::error("Failed to connect to practical courses API for {$studentNumber}: " . $e->getMessage());

                return 0;
            }

            if (!$response->successful()) {
                PpuApiService::logToTerminal("✗ فشل جلب مقررات الطالب {$studentName} (كود: {$response->status()})", $this->initiatorId);
                return 0;
            }

            $courses = $response->json('data') ?? [];

            if (empty($courses)) {
                $raw = $response->json();
                Log::info("ProcessStudentCourseSync: Empty response for {$studentNumber}", ['response' => $raw]);
                PpuApiService::logToTerminal("✗ استجابة API فارغة للطالب {$studentName} — أنظر إلى laravel log لرؤية الرد الخام", $this->initiatorId);
                return 0;
            }

            $syncedCount = 0;
            $inactiveSkipped = 0;
            $courseNames = [];
            foreach ($courses as $courseData) {
                $courseCode = $courseData['courseNo'] ?? $courseData['courseCode'] ?? $courseData['course_code'] ?? null;
                if (!$courseCode) continue;

                $courseName = $courseData['courseArabicName']
                    ?? $courseData['courseNameAr']
                    ?? $courseData['course_name_ar']
                    ?? $courseData['courseDisplay']
                    ?? $courseCode;

                $courseEnglishName = $courseData['courseEnglishName']
                    ?? $courseData['courseNameEn']
                    ?? $courseData['course_name_en']
                    ?? $courseName;

                $course = Course::updateOrCreate(
                    ['course_code' => (string) $courseCode],
                    [
                        'hours'       => $courseData['hours'] ?? 3,
                        'course_type' => CourseType::PRACTICAL,
                        'created_by'  => $this->createdById(),
                        'ar'          => ['name' => $courseName],
                        'en'          => ['name' => $courseEnglishName],
                    ]
                );

                if ($course->status === CourseStatus::INACTIVE) {
                    $inactiveSkipped++;
                    continue;
                }

                $supervisorId = $this->supervisorId($courseData, $studentProfile);

                Registration::updateOrCreate(
                    [
                        'student_id' => $studentProfile->user_id,
                        'course_id'  => $course->id,
                        'semester'   => $this->semesterNo,
                        'year'       => $this->academicYear,
                    ],
                    [
                        'supervisor_id'    => $supervisorId,
                        'university_score' => $courseData['universityScore'] ?? $courseData['university_score'] ?? null,
                        'company_score'    => $courseData['companyScore'] ?? $courseData['company_score'] ?? null,
                        'grade'            => $courseData['grade'] ?? null,
                        'created_by'       => $this->createdById(),
                    ]
                );

                $syncedCount++;
                $courseNames[] = $courseName;
            }

            $message = "✓ {$studentName}: تم اسناد ({$syncedCount}) " . implode(' - ', $courseNames);

            if ($inactiveSkipped > 0) {
                $message .= " / تم تجاهل {$inactiveSkipped} مساق غير فعال";
            }

            PpuApiService::logToTerminal($message, $this->initiatorId);

            return $syncedCount;

        } catch (\Exception $e) {
            PpuApiService::logToTerminal("✗ فشل اسناد مقررات الطالب {$studentName}: " . $e->getMessage(), $this->initiatorId);
            Log::error("ProcessStudentCourseSync Error for {$studentNumber}: " . $e->getMessage());
            throw $e;
        }
    }

    private function supervisorId(array $courseData, StudentProfile $studentProfile): int
    {
        $supervisorName = $courseData['supervisorName']
            ?? $courseData['supervisor_name']
            ?? $courseData['teacherName']
            ?? $courseData['teacher_name']
            ?? null;

        if ($supervisorName) {
            $supervisorId = User::where('name', $supervisorName)
                ->orWhere('name_en', $supervisorName)
                ->value('id');

            if ($supervisorId) {
                return (int) $supervisorId;
            }
        }

        $previousSupervisorId = Registration::where('student_id', $studentProfile->user_id)
            ->whereNotNull('supervisor_id')
            ->latest('id')
            ->value('supervisor_id');

        if ($previousSupervisorId) {
            return (int) $previousSupervisorId;
        }

        $trainingSupervisorId = User::whereHas('roles', fn($query) => $query->where('name', UserRole::PRACTICAL_TRAINING_SUPERVISOR->value))
            ->value('id');

        return (int) ($trainingSupervisorId ?: $this->createdById());
    }

    private function createdById(): int
    {
        $userId = $this->initiatorId ?? auth()->id() ?? User::query()->value('id');

        return (int) ($userId ?: 1);
    }
}
