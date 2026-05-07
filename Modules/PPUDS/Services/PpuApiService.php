<?php

namespace Modules\PPUDS\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Jobs\ProcessStudentSync;
use Modules\PPUDS\Jobs\ProcessStudentCourseSync;

class PpuApiService
{

    public function getAccessToken(): string
    {
        $token = session('keycloak_access_token');
        if (!$token) {
            throw new \Exception('لا يوجد صلاحية للوصول إلى بيانات الجامعة. يرجى تسجيل الدخول عبر بوابة الجامعة.');
        }

        return $token;
    }

    public static function logToTerminal(string $message, ?int $userId = null): void
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) return;

        $key = "sync_terminal_logs_{$userId}";
        $logs = cache()->get($key, []);
        $logs[] = ['message' => $message, 'time' => now()->format('H:i:s')];

        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }

        cache()->forever($key, $logs);
    }

    public static function getTerminalLogs(?int $userId = null): array
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) return [];
        return cache()->get("sync_terminal_logs_{$userId}", []);
    }

    public static function clearTerminalLogs(?int $userId = null): void
    {
        $userId = $userId ?? auth()->id();
        if ($userId) {
            cache()->forget("sync_terminal_logs_{$userId}");
        }
    }

    public function syncStudents($academicYear, $semesterNo)
    {
        $userId = auth()->id();
        self::logToTerminal("جارٍ بدء مزامنة الطلاب للسنة: {$academicYear} الفصل: {$semesterNo}...", $userId);

        try {
            $url = "https://api-core.ppu.edu/api/DualStudies/getAllDsStudents/{$academicYear}/{$semesterNo}";
            $token = $this->getAccessToken();

            self::logToTerminal('جلب البيانات من API الجامعة...', $userId);

            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->withToken($token)
                ->get($url);

            if ($response->successful()) {
                $students = $response->json('data') ?? [];
                $total = count($students);
                self::logToTerminal("تم استلام {$total} طالب من الـ API.", $userId);

                $dispatched = 0;
                collect($students)->chunk(50)->each(function ($chunk) use ($userId, &$dispatched) {
                    foreach ($chunk as $student) {
                        try {
                            ProcessStudentSync::dispatch($student, $userId);
                            $dispatched++;
                        } catch (\Exception $e) {
                            self::logToTerminal("فشل جدولة مزامنة الطالب: " . ($student['studentNo'] ?? 'Unknown'), $userId);
                            Log::error("Failed to dispatch sync job for student: " . ($student['studentNo'] ?? 'Unknown'));
                        }
                    }
                });

                self::logToTerminal("تم إرسال {$dispatched} وظيفة مزامنة للطلاب إلى الخلفية.", $userId);
                self::logToTerminal('✓ انتهت مزامنة الطلاب بنجاح.', $userId);
                return true;
            }

            self::logToTerminal('✗ فشل جلب الطلاب من API (كود: ' . $response->status() . ')', $userId);
            Log::error("Failed to fetch students from API", ['status' => $response->status(), 'body' => $response->body()]);
            return false;

        } catch (\Exception $e) {
            self::logToTerminal('✗ خطأ في مزامنة الطلاب: ' . $e->getMessage(), $userId);
            Log::error("PPU Student Sync Error: " . $e->getMessage());
            return false;
        }
    }

    public function syncStudentPracticalCourses($academicYear, $semesterNo)
    {
        $userId = auth()->id();
        self::logToTerminal("جارٍ بدء اسناد الطلاب للمقررات العملية للسنة: {$academicYear} الفصل: {$semesterNo}...", $userId);

        try {
            $studentsUrl = "https://api-core.ppu.edu/api/DualStudies/getAllDsStudents/{$academicYear}/{$semesterNo}";
            $token = $this->getAccessToken();

            self::logToTerminal('جلب الطلاب من API لاسناد المقررات...', $userId);

            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->withToken($token)
                ->get($studentsUrl);

            if (!$response->successful()) {
                self::logToTerminal('✗ فشل جلب الطلاب من API لاسناد المقررات (كود: ' . $response->status() . ')', $userId);
                return false;
            }

            $students = $response->json('data') ?? [];
            $total = count($students);
            self::logToTerminal("تم استلام {$total} طالب لاسناد المقررات.", $userId);

            $dispatched = 0;
            collect($students)->chunk(50)->each(function ($chunk) use ($token, $academicYear, $semesterNo, $userId, &$dispatched) {
                foreach ($chunk as $student) {
                    try {
                        ProcessStudentCourseSync::dispatch($student, $token, $academicYear, $semesterNo, $userId);
                        $dispatched++;
                    } catch (\Exception $e) {
                        self::logToTerminal("فشل جدولة اسناد مقررات الطالب: " . ($student['studentNo'] ?? 'Unknown'), $userId);
                        Log::error("Failed to dispatch course sync job for student: " . ($student['studentNo'] ?? 'Unknown'));
                    }
                }
            });

            self::logToTerminal("تم إرسال {$dispatched} وظيفة اسناد مقررات للطلاب إلى الخلفية.", $userId);
            self::logToTerminal('✓ انتهت عملية اسناد المقررات بنجاح.', $userId);
            return true;

        } catch (\Exception $e) {
            self::logToTerminal('✗ خطأ في اسناد المقررات: ' . $e->getMessage(), $userId);
            Log::error("PPU Course Sync Error: " . $e->getMessage());
            return false;
        }
    }

    public function syncMajors()
    {
        $userId = auth()->id();
        self::logToTerminal('جارٍ بدء مزامنة التخصصات...', $userId);

        try {
            $token = $this->getAccessToken();

            self::logToTerminal('جلب البيانات من API الجامعة...', $userId);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])
                ->withToken($token)
                ->connectTimeout(5)
                ->get("https://api-core.ppu.edu/api/DualStudies/getAllDSMajors");

            if ($response->successful()) {
                $majors = $response->json('data') ?? [];
                $total = count($majors);
                self::logToTerminal("تم استلام {$total} تخصص من الـ API.", $userId);

                $synced = 0;
                foreach ($majors as $majorData) {
                    Major::updateOrCreate(
                        ['reference_code' => $majorData['majorNo'], 'created_by' => auth()->id()],
                        [
                            'ar' => ['name' => $majorData['majorArabicName']],
                            'en' => ['name' => $majorData['majorEnglishName']],
                        ]
                    );
                    $synced++;
                }

                self::logToTerminal("تمت مزامنة {$synced} تخصص بنجاح.", $userId);
                self::logToTerminal('✓ انتهت مزامنة التخصصات بنجاح.', $userId);
                return true;
            }

            self::logToTerminal('✗ فشل جلب التخصصات من API (كود: ' . $response->status() . ')', $userId);
            return false;

        } catch (\Exception $e) {
            self::logToTerminal('✗ خطأ في مزامنة التخصصات: ' . $e->getMessage(), $userId);
            Log::error("PPU Major Sync Error: " . $e->getMessage());
            return false;
        }
    }
}
