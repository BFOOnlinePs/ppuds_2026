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
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Registration;
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

    public function handle()
    {
        $studentNumber = $this->data['studentNo'] ?? null;
        if (!$studentNumber) return;

        $studentProfile = \Modules\PPUDS\Entities\StudentProfile::where('student_number', $studentNumber)->first();
        if (!$studentProfile) {
            PpuApiService::logToTerminal("✗ لم يتم العثور على ملف الطالب {$studentNumber} في النظام", $this->initiatorId);
            return;
        }

        try {
            $url = "https://api-core.ppu.edu/api/DualStudies/getStudentPracticalCourses/{$studentNumber}?acadimicYear={$this->academicYear}&SemesterNumber={$this->semesterNo}";

            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->withToken($this->token)
                ->get($url);

            if (!$response->successful()) {
                PpuApiService::logToTerminal("✗ فشل جلب مقررات الطالب {$studentNumber} (كود: {$response->status()})", $this->initiatorId);
                return;
            }

            $courses = $response->json('data') ?? [];

            if (empty($courses)) {
                PpuApiService::logToTerminal("لا توجد مقررات عملية للطالب {$studentNumber}", $this->initiatorId);
                return;
            }

            $syncedCount = 0;
            foreach ($courses as $courseData) {
                $courseCode = $courseData['courseCode'] ?? $courseData['course_code'] ?? null;
                if (!$courseCode) continue;

                $course = Course::firstOrCreate(
                    ['course_code' => $courseCode],
                    [
                        'hours'       => $courseData['hours'] ?? 3,
                        'course_type' => CourseType::PRACTICAL,
                        'created_by'  => $this->initiatorId ?? 1,
                        'ar'          => ['name' => $courseData['courseNameAr'] ?? $courseData['course_name_ar'] ?? $courseCode],
                        'en'          => ['name' => $courseData['courseNameEn'] ?? $courseData['course_name_en'] ?? $courseCode],
                    ]
                );

                $supervisorId = null;
                $supervisorName = $courseData['supervisorName'] ?? $courseData['supervisor_name'] ?? null;
                if ($supervisorName) {
                    $supervisor = User::where('name', $supervisorName)->orWhere('name_en', $supervisorName)->first();
                    $supervisorId = $supervisor?->id;
                }

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
                        'created_by'       => $this->initiatorId ?? 1,
                    ]
                );

                $syncedCount++;
            }

            PpuApiService::logToTerminal("✓ تم اسناد {$syncedCount} مقرر للطالب {$studentNumber}", $this->initiatorId);

        } catch (\Exception $e) {
            PpuApiService::logToTerminal("✗ فشل اسناد مقررات الطالب {$studentNumber}: " . $e->getMessage(), $this->initiatorId);
            Log::error("ProcessStudentCourseSync Error for {$studentNumber}: " . $e->getMessage());
            throw $e;
        }
    }
}
