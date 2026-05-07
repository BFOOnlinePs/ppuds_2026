<?php

namespace Modules\PPUDS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Jobs\ProcessStudentSync;

class PpuApiService
{

    protected function getAccessToken(): string
    {
        $token = session('keycloak_access_token');
        if (!$token) {
            // هنا يمكنك رمي Exception ليتم التقاطه في الـ Controller
            // أو إرجاع توكن افتراضي (Service Account Token) من الـ .env إذا كان متاحاً
            throw new \Exception('لا يوجد صلاحية للوصول إلى بيانات الجامعة. يرجى تسجيل الدخول عبر بوابة الجامعة.');
        }

        return $token;
    }

    public function syncStudents($academicYear, $semesterNo)
    {
        try {
            $url = "https://api-core.ppu.edu/api/DualStudies/getAllDsStudents/{$academicYear}/{$semesterNo}";
            $token = $this->getAccessToken();

            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->withToken($token)
                ->get($url);

            Log::info("Response: " . json_encode($response->json()));

            if ($response->successful()) {
                $students = $response->json('data') ?? [];

                collect($students)->chunk(50)->each(function ($chunk){
                    foreach ($chunk as $student){
                        try {
                            ProcessStudentSync::dispatch($student);
                        }catch (\Exception $e){
                            Log::error("Failed to dispatch sync job for student: " . ($student['studentNo'] ?? 'Unknown'));
                        }
                    }
                });

                Log::info("Sync dispatched for " . count($students) . " students.");
                return true;
            }

            Log::error("Failed to fetch students from API", ['status' => $response->status(), 'body' => $response->body()]);
            return false;

        } catch (\Exception $e) {
            Log::error("PPU Student Sync Error: " . $e->getMessage());
            return false;
        }
    }

    public function syncMajors()
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])
                ->withToken($token)
                ->connectTimeout(5)
                ->get("https://api-core.ppu.edu/api/DualStudies/getAllDSMajors");

            if ($response->successful()) {
                $majors = $response->json('data') ?? [];

                foreach ($majors as $majorData) {
                    Major::updateOrCreate(
                        ['reference_code' => $majorData['majorNo'], 'created_by' => auth()->id()],
                        [
                            'ar' => ['name' => $majorData['majorArabicName']],
                            'en' => ['name' => $majorData['majorEnglishName']],
                        ]
                    );
                }
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error("PPU Major Sync Error: " . $e->getMessage());
            return false;
        }
    }
}
