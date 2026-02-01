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
            ->withToken('eyJhbGciOiJSUzI1NiIsImtpZCI6ImNhZThlOTMyM2NhMzE0NjRkZDA4OTc5NTc4MTg3NzlkIiwidHlwIjoiYXQrand0In0.eyJuYmYiOjE3Njk5NTYyMjMsImV4cCI6MTc2OTk1OTgyMywiaXNzIjoiaHR0cHM6Ly9teS5wcHUuZWR1IiwiYXVkIjoiRXh0ZXJuYWxBcGlzLmFwaSIsImNsaWVudF9pZCI6IkRTTW9iaWxlLnBwdSIsInN1YiI6IkRTVGVzdGluZyIsImF1dGhfdGltZSI6MTc2OTk1NjIyMywiaWRwIjoibG9jYWwiLCJ1c2VyX25vIjoiMTQ0MDEwMSIsInVzZXJfdHlwZSI6IjEiLCJyb2xlIjoiODMiLCJuYW1lIjoiRFNUZXN0aW5nIiwic2Vzc2lvbl9pZCI6IjM2ZDIxYzdiLTEwZjktNGFiNi05ODgwLWNiNGZmZjcwZWE2OSIsInNjb3BlIjpbImVtYWlsIiwib3BlbmlkIiwicHJvZmlsZSIsInJvbGUiLCJ1c2Vybm8iLCJFeHRlcm5hbEFwaXMuYXBpIiwib2ZmbGluZV9hY2Nlc3MiXSwiYW1yIjpbInB3ZCJdfQ.Rft89G0en6m9W9BmM4KuQF1_AVEgOhv_dnKugriKlqMBz7ghHpFrpnyNeiHFgyaEhXtSs8r8RZwrPGTdEWvd9YUrWAaI5dPcnDTfgtEC7cuk7jzdzAQQ9-d5PFWMjVSEqhC7I1RHZXNmOucHeZ-C3fKIYp3HVeDo3Z4wEGJ-cOxnV6Y6tasXHItV6hwygGjND9W1-cDUICq2FGpp1vYvxJDWwGzYtPG9BcxLU4G87EaZeKZ1_l5-YGJwSRau3CYrST_at4UcujHGEfBaL3tm59s4xpLGsJWW94OLpGI0uzCT7N9NReTL9bYLRDJsYxN_hBJu0T4OGZ4e-vcw47zqnA')
            ->get($url);

        Log::info("Response: " . json_encode($response->json()));

        if ($response->successful()) {
            $students = $response->json('data') ?? [];

            foreach ($students as $studentData) {
                try {
                    ProcessStudentSync::dispatch($studentData);
                    Log::info("Successfully synced student: " . $studentData['studentNo']);
                } catch (\Exception $e) {
                    Log::error("Failed to sync student: " . ($studentData['studentNo'] ?? 'Unknown'), [
                        'error' => $e->getMessage(),
                        'data' => $studentData
                    ]);
                    continue;
                }
            }
            return true;
        }
        return false;
    }

    public function syncMajors()
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])
                ->withToken('eyJhbGciOiJSUzI1NiIsImtpZCI6ImNhZThlOTMyM2NhMzE0NjRkZDA4OTc5NTc4MTg3NzlkIiwidHlwIjoiYXQrand0In0.eyJuYmYiOjE3Njk5NTYyMjMsImV4cCI6MTc2OTk1OTgyMywiaXNzIjoiaHR0cHM6Ly9teS5wcHUuZWR1IiwiYXVkIjoiRXh0ZXJuYWxBcGlzLmFwaSIsImNsaWVudF9pZCI6IkRTTW9iaWxlLnBwdSIsInN1YiI6IkRTVGVzdGluZyIsImF1dGhfdGltZSI6MTc2OTk1NjIyMywiaWRwIjoibG9jYWwiLCJ1c2VyX25vIjoiMTQ0MDEwMSIsInVzZXJfdHlwZSI6IjEiLCJyb2xlIjoiODMiLCJuYW1lIjoiRFNUZXN0aW5nIiwic2Vzc2lvbl9pZCI6IjM2ZDIxYzdiLTEwZjktNGFiNi05ODgwLWNiNGZmZjcwZWE2OSIsInNjb3BlIjpbImVtYWlsIiwib3BlbmlkIiwicHJvZmlsZSIsInJvbGUiLCJ1c2Vybm8iLCJFeHRlcm5hbEFwaXMuYXBpIiwib2ZmbGluZV9hY2Nlc3MiXSwiYW1yIjpbInB3ZCJdfQ.Rft89G0en6m9W9BmM4KuQF1_AVEgOhv_dnKugriKlqMBz7ghHpFrpnyNeiHFgyaEhXtSs8r8RZwrPGTdEWvd9YUrWAaI5dPcnDTfgtEC7cuk7jzdzAQQ9-d5PFWMjVSEqhC7I1RHZXNmOucHeZ-C3fKIYp3HVeDo3Z4wEGJ-cOxnV6Y6tasXHItV6hwygGjND9W1-cDUICq2FGpp1vYvxJDWwGzYtPG9BcxLU4G87EaZeKZ1_l5-YGJwSRau3CYrST_at4UcujHGEfBaL3tm59s4xpLGsJWW94OLpGI0uzCT7N9NReTL9bYLRDJsYxN_hBJu0T4OGZ4e-vcw47zqnA')
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
