<?php

namespace Modules\PPUDS\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Enums\CourseType;
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

    public function syncSystemData($academicYear, $semesterNo, ?string $token = null, ?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        $token = $token ?? $this->getAccessToken();

        $majorsSynced = $this->syncMajors($token, $userId);
        if (! $majorsSynced) {
            self::logToTerminal('تم إيقاف المزامنة لأن مزامنة التخصصات فشلت.', $userId);

            return false;
        }

        try {
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

    public function syncCourses($academicYear, $semesterNo, ?string $token = null, ?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();
        $token = $token ?? $this->getAccessToken();

        self::logToTerminal("جارٍ بدء مزامنة المساقات للسنة: {$academicYear} الفصل: {$semesterNo}...", $userId);

        try {
            $students = $this->fetchStudentsFromUniversity($academicYear, $semesterNo, $token, $userId);

            if ($students === null) {
                return false;
            }

            $created = 0;
            $updated = 0;
            $failed = 0;
            $empty = 0;
            $seenCourseCodes = [];

            foreach ($students as $student) {
                $studentNumber = $student['studentNo'] ?? null;

                if (! $studentNumber) {
                    continue;
                }

                $courses = $this->fetchStudentPracticalCourses($studentNumber, $academicYear, $semesterNo, $token, $userId);

                if ($courses === null) {
                    $failed++;
                    continue;
                }

                if (empty($courses)) {
                    $empty++;
                    continue;
                }

                foreach ($courses as $courseData) {
                    $courseCode = $this->courseCodeFromApiData($courseData);

                    if (! $courseCode || isset($seenCourseCodes[$courseCode])) {
                        continue;
                    }

                    $course = $this->syncCourseFromApiData($courseData, $userId);

                    if (! $course) {
                        continue;
                    }

                    $seenCourseCodes[$courseCode] = true;
                    $course->wasRecentlyCreated ? $created++ : $updated++;
                }
            }

            $total = $created + $updated;

            self::logToTerminal("تمت مزامنة {$total} مساق: {$created} جديد / {$updated} تحديث.", $userId);
            self::logToTerminal("طلاب بدون مساقات عملية في هذا الفصل: {$empty}.", $userId);

            if ($failed > 0) {
                self::logToTerminal("⚠ انتهت مزامنة المساقات مع {$failed} أخطاء.", $userId);
            } else {
                self::logToTerminal('✓ انتهت مزامنة المساقات بنجاح. راجع حالة المساقات قبل إكمال المزامنة.', $userId);
            }

            return $failed === 0;
        } catch (\Exception $e) {
            self::logToTerminal('✗ خطأ في مزامنة المساقات: ' . $e->getMessage(), $userId);
            Log::error('PPU Courses Sync Error: ' . $e->getMessage());

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

    public function addCompanyToUniversity(
        Company $company,
        ?string $password = null,
        ?int $supervisorId = null,
        ?string $token = null,
        ?int $userId = null,
        bool $sendEvenIfCompanyExists = false,
    ): ?array {
        $userId = $userId ?? auth()->id();
        $company->loadMissing(['branches.supervisors', 'translations']);

        if (! $sendEvenIfCompanyExists && filled($company->old_company_id)) {
            self::logToTerminal("الشركة {$company->name} مضافة مسبقًا في نظام الجامعة، لذلك تم تخطي الإرسال.", $userId);

            return [
                'success' => true,
                'operation' => 'already_exists',
                'old_company_id' => $company->old_company_id,
            ];
        }

        $syncedCompany = $sendEvenIfCompanyExists ? null : $this->syncedCompanyWithSameName($company);

        if ($syncedCompany) {
            self::logToTerminal("الشركة {$company->name} موجودة مسبقًا في النظام ومضافة للجامعة، لذلك تم تخطي الإرسال.", $userId);

            return [
                'success' => true,
                'operation' => 'already_exists',
                'company_id' => $syncedCompany->id,
                'old_company_id' => $syncedCompany->old_company_id,
            ];
        }

        $payload = $this->universityCompanyPayload($company, $password, $supervisorId);

        if ($payload === null) {
            self::logToTerminal("لم يتم إرسال الشركة {$company->name} إلى API الجامعة بسبب نقص بيانات التواصل.", $userId);

            return null;
        }

        $url = $this->ppuApiUrl('/api/DualStudies/Company/Add');

        if ($supervisorId) {
            Log::info('Sending company supervisor to PPU add company API', [
                'company_id' => $company->id,
                'old_company_id' => $company->old_company_id,
                'supervisor_id' => $supervisorId,
                'url' => $url,
                'payload' => $this->redactUniversityCompanyPayload($payload),
            ]);
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])
                ->withToken($token ?? $this->getAccessToken())
                ->asJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->post($url, $payload);
        } catch (\Exception $e) {
            self::logToTerminal("✗ تعذر إرسال الشركة {$company->name} إلى API الجامعة: " . $e->getMessage(), $userId);
            Log::error('Failed to connect to PPU add company API', [
                'company_id' => $company->id,
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        $responseData = $response->json() ?? [];
        $universityCompanyId = $this->universityCompanyIdFromResponse($responseData);

        if ($supervisorId) {
            Log::info('PPU add company API response for supervisor sync', [
                'company_id' => $company->id,
                'old_company_id' => $company->old_company_id,
                'supervisor_id' => $supervisorId,
                'status' => $response->status(),
                'response' => $responseData ?: $response->body(),
            ]);
        }

        if (! $response->successful()) {
            if ($this->companyAlreadyExistsResponse($response, $responseData)) {
                if ($universityCompanyId && blank($company->old_company_id)) {
                    $company->forceFill(['old_company_id' => $universityCompanyId])->save();
                }

                self::logToTerminal("الشركة {$company->name} مضافة مسبقًا في API الجامعة، لذلك لم يتم إنشاؤها مرة أخرى.", $userId);

                return [
                    'success' => true,
                    'operation' => 'already_exists',
                    'old_company_id' => $universityCompanyId,
                    'response' => $responseData,
                ];
            }

            self::logToTerminal("✗ فشل إرسال الشركة {$company->name} إلى API الجامعة (كود: {$response->status()})", $userId);
            Log::error('Failed to add company to PPU API', [
                'company_id' => $company->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        if (data_get($responseData, 'success') === false) {
            self::logToTerminal("✗ رفض API الجامعة إضافة الشركة {$company->name}.", $userId);
            Log::error('PPU add company API returned success=false', [
                'company_id' => $company->id,
                'response' => $responseData,
            ]);

            return [
                'success' => false,
                'operation' => 'failed',
                'old_company_id' => $universityCompanyId,
                'response' => $responseData,
            ];
        }

        if ($universityCompanyId && blank($company->old_company_id)) {
            $company->forceFill(['old_company_id' => $universityCompanyId])->save();
        }

        if ($this->universityCompanyAlreadyExists($responseData)) {
            self::logToTerminal("الشركة {$company->name} مضافة مسبقًا في API الجامعة، لذلك لم يتم إنشاؤها مرة أخرى.", $userId);

            return [
                'success' => true,
                'operation' => 'already_exists',
                'old_company_id' => $universityCompanyId,
                'response' => $responseData,
            ];
        }

        self::logToTerminal("✓ تم إرسال الشركة {$company->name} إلى API الجامعة.", $userId);

        return [
            'success' => true,
            'operation' => 'created',
            'old_company_id' => $universityCompanyId,
            'response' => $responseData,
        ];
    }

    public function syncStudents($academicYear, $semesterNo)
    {
        $userId = auth()->id();
        self::logToTerminal("جارٍ بدء مزامنة الطلاب للسنة: {$academicYear} الفصل: {$semesterNo}...", $userId);

        try {
            $url = "https://api-core.ppu.edu/api/DualStudies/getDsStudentsByYear/{$academicYear}/{$semesterNo}";
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

    public function syncMajors(?string $token = null, ?int $userId = null)
    {
        $userId = $userId ?? auth()->id();
        self::logToTerminal('جارٍ بدء مزامنة التخصصات...', $userId);

        try {
            $token = $token ?? $this->getAccessToken();

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
                            'created_by' => $userId ?? auth()->id() ?? 1,
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

        $studentsUrl = "https://api-core.ppu.edu/api/DualStudies/getDsStudentsByYear/{$academicYear}/{$semesterNo}";
        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->withToken($token)
                ->retry(3, 1000, throw: false)
                ->connectTimeout(15)
                ->timeout(60)
                ->get($studentsUrl);
        } catch (\Exception $e) {
            self::logToTerminal('✗ تعذر الاتصال بـ API الطلاب من الجامعة: ' . $e->getMessage(), $userId);
            Log::error('Failed to connect to students API', [
                'url' => $studentsUrl,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            self::logToTerminal('✗ فشل جلب الطلاب من API (كود: ' . $response->status() . ')', $userId);
            Log::error("Failed to fetch students from API", ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        return $response->json('data') ?? [];
    }

    private function fetchStudentPracticalCourses($studentNumber, $academicYear, $semesterNo, string $token, ?int $userId = null): ?array
    {
        $url = "https://api-core.ppu.edu/api/DualStudies/getStudentPracticalCourses/{$studentNumber}";

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->withToken($token)
                ->retry(3, 1000, throw: false)
                ->connectTimeout(15)
                ->timeout(45)
                ->get($url, [
                    'academicYear' => $academicYear,
                    'semesterNo' => $semesterNo,
                ]);
        } catch (\Exception $e) {
            self::logToTerminal("✗ تعذر الاتصال لجلب مساقات الطالب {$studentNumber}: " . $e->getMessage(), $userId);
            Log::error('Failed to connect to student practical courses API', [
                'student_number' => $studentNumber,
                'academic_year' => $academicYear,
                'semester_no' => $semesterNo,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            self::logToTerminal("✗ فشل جلب مساقات الطالب {$studentNumber} (كود: {$response->status()})", $userId);
            Log::error('Failed to fetch student practical courses from API', [
                'student_number' => $studentNumber,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json('data') ?? [];
    }

    private function syncCourseFromApiData(array $courseData, ?int $userId = null): ?Course
    {
        $courseCode = $this->courseCodeFromApiData($courseData);

        if (! $courseCode) {
            return null;
        }

        $courseName = $courseData['courseArabicName']
            ?? $courseData['courseNameAr']
            ?? $courseData['course_name_ar']
            ?? $courseData['courseDisplay']
            ?? $courseCode;

        $courseEnglishName = $courseData['courseEnglishName']
            ?? $courseData['courseNameEn']
            ?? $courseData['course_name_en']
            ?? $courseName;

        return Course::updateOrCreate(
            ['course_code' => $courseCode],
            [
                'hours' => $courseData['hours'] ?? 3,
                'course_type' => CourseType::PRACTICAL,
                'created_by' => $userId ?? auth()->id() ?? 1,
                'ar' => ['name' => $courseName],
                'en' => ['name' => $courseEnglishName],
            ]
        );
    }

    private function courseCodeFromApiData(array $courseData): ?string
    {
        $courseCode = $courseData['courseNo'] ?? $courseData['courseCode'] ?? $courseData['course_code'] ?? null;

        return $courseCode === null ? null : (string) $courseCode;
    }

    private function universityCompanyPayload(Company $company, ?string $password = null, ?int $supervisorId = null): ?array
    {
        $supervisor = $this->companySupervisorForUniversityPayload($company, $supervisorId);
        $branch = $company->branches->first();
        $email = filled($supervisor?->email) ? strtolower(trim((string) $supervisor->email)) : $branch?->email;
        $mobile = $this->normalizeUniversityCompanyPhone($supervisor?->phone ?: $branch?->phone);

        if (blank($email) || blank($mobile)) {
            return null;
        }

        $resolvedPassword = $password ?: $mobile;

        $payload = [
            'caName' => $this->companyName($company, 'ar'),
            'ceName' => $this->companyName($company, 'en'),
            'cpaName' => $supervisor?->name ?: $this->companyName($company, 'ar'),
            'cpeName' => $supervisor?->name_en ?: ($supervisor?->name ?: $this->companyName($company, 'en')),
            'email' => $email,
            'email2' => $email,
            'mobile' => $mobile,
            'phone' => $mobile,
            'pw' => $resolvedPassword,
            'password' => $resolvedPassword,
            'username' => $mobile,
            'userName' => $mobile,
        ];

        if (filled($company->old_company_id)) {
            $payload['oldCompanyId'] = (int) $company->old_company_id;
            $payload['companyId'] = (int) $company->old_company_id;
            $payload['companyID'] = (int) $company->old_company_id;
            $payload['cId'] = (int) $company->old_company_id;
            $payload['c_id'] = (int) $company->old_company_id;
        }

        return $payload;
    }

    private function normalizeUniversityCompanyPhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        return preg_replace('/\s+/', '', trim((string) $phone));
    }

    private function redactUniversityCompanyPayload(array $payload): array
    {
        foreach (['pw', 'password'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '***';
            }
        }

        return $payload;
    }

    private function syncedCompanyWithSameName(Company $company): ?Company
    {
        $names = collect([
            $this->companyName($company, 'ar'),
            $this->companyName($company, 'en'),
        ])
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        return Company::query()
            ->whereKeyNot($company->id)
            ->whereNotNull('old_company_id')
            ->where(function ($query) use ($names) {
                foreach ($names as $name) {
                    $query->orWhereTranslation('name', $name);
                }
            })
            ->first();
    }

    private function companySupervisorForUniversityPayload(Company $company, ?int $supervisorId = null): ?User
    {
        if ($supervisorId) {
            return User::find($supervisorId);
        }

        return $company->companySupervisors()->first();
    }

    private function companyName(Company $company, string $locale): string
    {
        return $company->translate($locale)?->name
            ?: $company->name
            ?: "Company {$company->id}";
    }

    private function companyUsername(Company $company): string
    {
        return Str::slug($this->companyName($company, 'en')) ?: "company-{$company->id}";
    }

    private function universityCompanyIdFromResponse(array $responseData): ?int
    {
        $id = data_get($responseData, 'data.id')
            ?? data_get($responseData, 'data.companyId')
            ?? data_get($responseData, 'data.companyID')
            ?? data_get($responseData, 'data.cId')
            ?? data_get($responseData, 'data.c_id')
            ?? data_get($responseData, 'id')
            ?? data_get($responseData, 'companyId')
            ?? data_get($responseData, 'companyID')
            ?? data_get($responseData, 'cId')
            ?? data_get($responseData, 'c_id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function companyAlreadyExistsResponse(Response $response, array $responseData): bool
    {
        if (! in_array($response->status(), [400, 409, 422], true)) {
            return false;
        }

        return $this->universityCompanyAlreadyExists($responseData)
            || $this->containsAlreadyExistsText($response->body());
    }

    private function universityCompanyAlreadyExists(array $responseData): bool
    {
        $text = collect([
            data_get($responseData, 'status'),
            data_get($responseData, 'message'),
            data_get($responseData, 'msg'),
            data_get($responseData, 'error'),
            data_get($responseData, 'errors'),
        ])
            ->filter()
            ->map(fn($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value)
            ->implode(' ');

        return $this->containsAlreadyExistsText($text);
    }

    private function containsAlreadyExistsText(string $text): bool
    {
        $text = Str::lower($text);

        foreach (['already', 'exist', 'duplicate', 'موجود', 'مضافة', 'مكرر'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function ppuApiUrl(string $path): string
    {
        return rtrim(config('services.ppu_api.base_url', 'https://api-core.ppu.edu'), '/') . '/' . ltrim($path, '/');
    }
}
