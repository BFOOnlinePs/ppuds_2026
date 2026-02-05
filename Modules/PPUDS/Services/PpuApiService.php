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
    public function syncStudents($academicYear, $semesterNo)
    {
        $url = "https://api-core.ppu.edu/api/DualStudies/getAllDsStudents/{$academicYear}/{$semesterNo}";

        $response = Http::withHeaders(['Accept' => 'application/json'])
            ->withToken('eyJhbGciOiJSUzI1NiIsImtpZCI6ImNhZThlOTMyM2NhMzE0NjRkZDA4OTc5NTc4MTg3NzlkIiwidHlwIjoiYXQrand0In0.eyJuYmYiOjE3NzAyODUwODEsImV4cCI6MTc3MDI4ODY4MSwiaXNzIjoiaHR0cHM6Ly9teS5wcHUuZWR1IiwiYXVkIjoiRXh0ZXJuYWxBcGlzLmFwaSIsImNsaWVudF9pZCI6IkRTTW9iaWxlLnBwdSIsInN1YiI6IkRTVGVzdGluZyIsImF1dGhfdGltZSI6MTc3MDI4NTA4MSwiaWRwIjoibG9jYWwiLCJ1c2VyX25vIjoiMTQ0MDEwMSIsInVzZXJfdHlwZSI6IjEiLCJyb2xlIjoiODMiLCJuYW1lIjoiRFNUZXN0aW5nIiwic2Vzc2lvbl9pZCI6IjBjYmRlZTNmLTU2OTAtNGJkZS1hYWE0LWY5N2RmOGZhNDNhMiIsInNjb3BlIjpbImVtYWlsIiwib3BlbmlkIiwicHJvZmlsZSIsInJvbGUiLCJ1c2Vybm8iLCJFeHRlcm5hbEFwaXMuYXBpIiwib2ZmbGluZV9hY2Nlc3MiXSwiYW1yIjpbInB3ZCJdfQ.mV1uNVB3B4rM_FBgOYuWTL-0hleD06_cJ0WNWv0RZek3tdmdBQ4CxM2JQALbwRZ1TgWcxLV15OmiMN-_6FKXAJ_FuJSgNKr7tzWg-XZBpdqZh8vsCNUpUy9RuG4aSK3Mj_WxigL-oyBS0CTTocgAfwm1u1r1VEm0zDMjWgAUSZKCx_1sjN8aCHPGGrzx3MoM-44bT0L7R_JSmsopINKLYnjb9hlKl01xIa-JMU972WogYd30VM-Bn0yVcrM2pWysdd5vVFq3I6z7QIQA60WdWQwR6yq0wCw0hOlX67NEV2DmENBKZncnnPTWREzZcYulQgy5hJ4ZwVHNuIrWsnEGQA')
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
    }

    public function syncMajors()
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])
                ->withToken('eyJhbGciOiJSUzI1NiIsImtpZCI6ImNhZThlOTMyM2NhMzE0NjRkZDA4OTc5NTc4MTg3NzlkIiwidHlwIjoiYXQrand0In0.eyJuYmYiOjE3NzAyODUwODEsImV4cCI6MTc3MDI4ODY4MSwiaXNzIjoiaHR0cHM6Ly9teS5wcHUuZWR1IiwiYXVkIjoiRXh0ZXJuYWxBcGlzLmFwaSIsImNsaWVudF9pZCI6IkRTTW9iaWxlLnBwdSIsInN1YiI6IkRTVGVzdGluZyIsImF1dGhfdGltZSI6MTc3MDI4NTA4MSwiaWRwIjoibG9jYWwiLCJ1c2VyX25vIjoiMTQ0MDEwMSIsInVzZXJfdHlwZSI6IjEiLCJyb2xlIjoiODMiLCJuYW1lIjoiRFNUZXN0aW5nIiwic2Vzc2lvbl9pZCI6IjBjYmRlZTNmLTU2OTAtNGJkZS1hYWE0LWY5N2RmOGZhNDNhMiIsInNjb3BlIjpbImVtYWlsIiwib3BlbmlkIiwicHJvZmlsZSIsInJvbGUiLCJ1c2Vybm8iLCJFeHRlcm5hbEFwaXMuYXBpIiwib2ZmbGluZV9hY2Nlc3MiXSwiYW1yIjpbInB3ZCJdfQ.mV1uNVB3B4rM_FBgOYuWTL-0hleD06_cJ0WNWv0RZek3tdmdBQ4CxM2JQALbwRZ1TgWcxLV15OmiMN-_6FKXAJ_FuJSgNKr7tzWg-XZBpdqZh8vsCNUpUy9RuG4aSK3Mj_WxigL-oyBS0CTTocgAfwm1u1r1VEm0zDMjWgAUSZKCx_1sjN8aCHPGGrzx3MoM-44bT0L7R_JSmsopINKLYnjb9hlKl01xIa-JMU972WogYd30VM-Bn0yVcrM2pWysdd5vVFq3I6z7QIQA60WdWQwR6yq0wCw0hOlX67NEV2DmENBKZncnnPTWREzZcYulQgy5hJ4ZwVHNuIrWsnEGQA')
                ->connectTimeout(5)
                ->get("https://api-core.ppu.edu/api/DualStudies/getAllDSMajors");

            if ($response->successful()) {
                $majors = $response->json('data') ?? [];

                foreach ($majors as $majorData) {
                    Major::updateOrCreate(
                        ['reference_code' => $majorData['majorNo'], 'created_by'    => auth()->user()->id],
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
