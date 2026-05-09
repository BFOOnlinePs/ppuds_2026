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

        if ($this->isTokenExpired($token)) {
            $token = $this->refreshToken();
        }

        return $token;
    }

    private function isTokenExpired(string $token): bool
    {
        $payload = json_decode(base64_decode(explode('.', $token)[1] ?? '{}'), true);
        $exp = $payload['exp'] ?? 0;

        return ($exp - 30) < now()->timestamp;
    }

    private function refreshToken(): string
    {
        $refreshToken = session('keycloak_refresh_token');
        if (!$refreshToken) {
            throw new \Exception('انتهت صلاحية التوكن ولا يوجد توكن تحديث. يرجى تسجيل الدخول مرة أخرى.');
        }

        $baseUrl = config('services.keycloak.base_url');
        $realm = config('services.keycloak.realms');
        $clientId = config('services.keycloak.client_id');
        $clientSecret = config('services.keycloak.client_secret');

        $response = Http::asForm()->post("{$baseUrl}/realms/{$realm}/protocol/openid-connect/token", [
            'grant_type'    => 'refresh_token',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        if (!$response->successful()) {
            session()->forget(['keycloak_access_token', 'keycloak_refresh_token']);
            throw new \Exception('فشل تحديث التوكن. يرجى تسجيل الدخول مرة أخرى.');
        }

        $data = $response->json();

        session([
            'keycloak_access_token'  => $data['access_token'],
            'keycloak_refresh_token' => $data['refresh_token'] ?? $refreshToken,
        ]);

        return $data['access_token'];
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

    public function syncSystemData($academicYear, $semesterNo): bool
    {
        $userId = auth()->id();

        $majorsSynced = $this->syncMajors();
        if (! $majorsSynced) {
            self::logToTerminal('تم إيقاف المزامنة لأن مزامنة التخصصات فشلت.', $userId);

            return false;
        }

        try {
            $token = $this->getAccessToken();
            $students = $this->fetchStudentsFromUniversity($academicYear, $semesterNo, $token, $userId);

            if ($students === null) {
                return false;
            }

            $studentSynced = 0;
            $studentFailed = 0;

            foreach ($students as $student) {
                try {
                    ProcessStudentSync::dispatchSync($student, $userId);
                    $studentSynced++;
                } catch (\Exception $e) {
                    $studentFailed++;
                    self::logToTerminal('✗ فشل حفظ الطالب: ' . ($student['studentNo'] ?? 'Unknown'), $userId);
                    Log::error('Failed to sync student in full system sync', [
                        'student' => $student,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            self::logToTerminal("تمت مزامنة الطلاب: {$studentSynced} نجاح / {$studentFailed} فشل.", $userId);

            $registrationSynced = 0;
            $registrationFailed = 0;

            foreach ($students as $student) {
                try {
                    $registrationSynced += ProcessStudentCourseSync::dispatchSync($student, $token, $academicYear, $semesterNo, $userId) ?? 0;
                } catch (\Exception $e) {
                    $registrationFailed++;
                    self::logToTerminal('✗ فشل اسناد مقررات الطالب: ' . ($student['studentNo'] ?? 'Unknown'), $userId);
                    Log::error('Failed to sync student registrations in full system sync', [
                        'student' => $student,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            self::logToTerminal("تم إنشاء/تحديث {$registrationSynced} تسجيل في النظام.", $userId);
            self::logToTerminal("أخطاء اسناد التسجيل: {$registrationFailed}.", $userId);

            return $studentFailed === 0 && $registrationFailed === 0;
        } catch (\Exception $e) {
            self::logToTerminal('✗ خطأ في المزامنة الكاملة: ' . $e->getMessage(), $userId);
            Log::error('PPU Full System Sync Error: ' . $e->getMessage());

            return false;
        }
    }

    public function getCurrentSemesterSettings(): ?array
    {
        $userId = auth()->id();

        self::logToTerminal('جلب إعدادات الفصل الحالي من API الجامعة...', $userId);

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->withToken($this->getAccessToken())
                ->connectTimeout(5)
                ->get('https://api-core.ppu.edu/api/DualStudies/getCurrentSemesterSettings');

            if (! $response->successful()) {
                self::logToTerminal('✗ فشل جلب إعدادات الفصل من API الجامعة (كود: ' . $response->status() . ')', $userId);
                Log::error('Failed to fetch current semester settings from API', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json('data') ?? [];
            $academicYear = $data['academicYear'] ?? null;
            $semesterNo = $data['semesterNo'] ?? null;

            if (! $academicYear || ! $semesterNo) {
                self::logToTerminal('✗ إعدادات الفصل من API الجامعة غير مكتملة.', $userId);
                Log::warning('Incomplete current semester settings response', ['response' => $response->json()]);

                return null;
            }

            self::logToTerminal("✓ تم اعتماد إعدادات الجامعة: السنة {$academicYear} / الفصل {$semesterNo}", $userId);

            return [
                'academicYear' => (string) $academicYear,
                'semesterNo' => (string) $semesterNo,
            ];
        } catch (\Exception $e) {
            self::logToTerminal('✗ خطأ في جلب إعدادات الفصل من API الجامعة: ' . $e->getMessage(), $userId);
            Log::error('PPU Current Semester Settings Error: ' . $e->getMessage());

            return null;
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
            $token = $this->getAccessToken();

            $students = $this->fetchStudentsFromUniversity($academicYear, $semesterNo, $token, $userId);
            if ($students === null) {
                return false;
            }

            $total = count($students);
            self::logToTerminal("تم استلام {$total} طالب لاسناد المقررات.", $userId);

            $synced = 0;
            $failed = 0;
            collect($students)->chunk(50)->each(function ($chunk) use ($token, $academicYear, $semesterNo, $userId, &$synced, &$failed) {
                foreach ($chunk as $student) {
                    try {
                        $synced += ProcessStudentCourseSync::dispatchSync($student, $token, $academicYear, $semesterNo, $userId) ?? 0;
                    } catch (\Exception $e) {
                        $failed++;
                        self::logToTerminal("فشل اسناد مقررات الطالب: " . ($student['studentNo'] ?? 'Unknown'), $userId);
                        Log::error("Failed to sync course registration for student: " . ($student['studentNo'] ?? 'Unknown'));
                    }
                }
            });

            self::logToTerminal("تم إنشاء/تحديث {$synced} تسجيل في النظام.", $userId);
            self::logToTerminal($failed === 0 ? '✓ انتهت عملية اسناد المقررات بنجاح.' : "⚠ انتهت عملية اسناد المقررات مع {$failed} أخطاء.", $userId);

            return $failed === 0;

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
                    $majorNo = $majorData['majorNo'] ?? null;
                    if (! $majorNo) {
                        self::logToTerminal('تم تخطي تخصص بدون رقم مرجعي من API الجامعة.', $userId);
                        continue;
                    }

                    Major::updateOrCreate(
                        ['reference_code' => $majorNo],
                        [
                            'created_by' => auth()->id() ?? 1,
                            'ar' => ['name' => $majorData['majorArabicName'] ?? $majorNo],
                            'en' => ['name' => $majorData['majorEnglishName'] ?? ($majorData['majorArabicName'] ?? $majorNo)],
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

    private function fetchStudentsFromUniversity($academicYear, $semesterNo, string $token, ?int $userId = null): ?array
    {
        self::logToTerminal('جلب الطلاب من API الجامعة...', $userId);

        $studentsUrl = "https://api-core.ppu.edu/api/DualStudies/getAllDsStudents/{$academicYear}/{$semesterNo}";
        $response = Http::withHeaders(['Accept' => 'application/json'])
            ->withToken($token)
            ->get($studentsUrl);

        if (! $response->successful()) {
            self::logToTerminal('✗ فشل جلب الطلاب من API (كود: ' . $response->status() . ')', $userId);
            Log::error("Failed to fetch students from API", ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        return $response->json('data') ?? [];
    }
}
