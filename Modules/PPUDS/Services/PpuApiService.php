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
            ->withToken('eyJhbGciOiJSUzI1NiIsImtpZCI6ImNhZThlOTMyM2NhMzE0NjRkZDA4OTc5NTc4MTg3NzlkIiwidHlwIjoiYXQrand0In0.eyJuYmYiOjE3NzA0Njc4NDEsImV4cCI6MTc3MDQ3MTQ0MSwiaXNzIjoiaHR0cHM6Ly9teS5wcHUuZWR1IiwiYXVkIjoiRXh0ZXJuYWxBcGlzLmFwaSIsImNsaWVudF9pZCI6IkRTTW9iaWxlLnBwdSIsInN1YiI6IkRTVGVzdGluZyIsImF1dGhfdGltZSI6MTc3MDQ2Nzg0MSwiaWRwIjoibG9jYWwiLCJ1c2VyX25vIjoiMTQ0MDEwMSIsInVzZXJfdHlwZSI6IjEiLCJyb2xlIjoiODMiLCJuYW1lIjoiRFNUZXN0aW5nIiwic2Vzc2lvbl9pZCI6Ijc5MmRhYjBiLWI1YjktNGVjYy05OTNjLTdkNmU2MTQ3ZTA1NiIsInNjb3BlIjpbImVtYWlsIiwib3BlbmlkIiwicHJvZmlsZSIsInJvbGUiLCJ1c2Vybm8iLCJFeHRlcm5hbEFwaXMuYXBpIiwib2ZmbGluZV9hY2Nlc3MiXSwiYW1yIjpbInB3ZCJdfQ.tp__GZ2HzxF3uswU-X5pHdb3KCN6T1Kbb519F_pMiHEkH2DfrRMZzUf630JqobogPAibqlILFgeUa06BMUnjztPhnEpmIZ8VEzGimqPWICtD6b2oYIO1YMZAq4kV9YDUqGcOnBvzutof_jVvqWNIKVAgN379yG5c_VA2vD55VkL0MDXydRvd_49NeIXKjAtgF5tti3K2lNoXTYTWw43OMCOvH2uni8YdfpvXiEYWp-G_jt1Lzlak_bdppeRxHWD_mQjSgRLkAGrTrRTtd3lwthc9WWQTz-n-9hORj_Gg3gRa-dUWoWBPZav4OjW9hrbTey3jVp1a1GMn48pyOq9sJw')
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
                ->withToken('eyJhbGciOiJSUzI1NiIsImtpZCI6ImNhZThlOTMyM2NhMzE0NjRkZDA4OTc5NTc4MTg3NzlkIiwidHlwIjoiYXQrand0In0.eyJuYmYiOjE3NzA0Njc4NDEsImV4cCI6MTc3MDQ3MTQ0MSwiaXNzIjoiaHR0cHM6Ly9teS5wcHUuZWR1IiwiYXVkIjoiRXh0ZXJuYWxBcGlzLmFwaSIsImNsaWVudF9pZCI6IkRTTW9iaWxlLnBwdSIsInN1YiI6IkRTVGVzdGluZyIsImF1dGhfdGltZSI6MTc3MDQ2Nzg0MSwiaWRwIjoibG9jYWwiLCJ1c2VyX25vIjoiMTQ0MDEwMSIsInVzZXJfdHlwZSI6IjEiLCJyb2xlIjoiODMiLCJuYW1lIjoiRFNUZXN0aW5nIiwic2Vzc2lvbl9pZCI6Ijc5MmRhYjBiLWI1YjktNGVjYy05OTNjLTdkNmU2MTQ3ZTA1NiIsInNjb3BlIjpbImVtYWlsIiwib3BlbmlkIiwicHJvZmlsZSIsInJvbGUiLCJ1c2Vybm8iLCJFeHRlcm5hbEFwaXMuYXBpIiwib2ZmbGluZV9hY2Nlc3MiXSwiYW1yIjpbInB3ZCJdfQ.tp__GZ2HzxF3uswU-X5pHdb3KCN6T1Kbb519F_pMiHEkH2DfrRMZzUf630JqobogPAibqlILFgeUa06BMUnjztPhnEpmIZ8VEzGimqPWICtD6b2oYIO1YMZAq4kV9YDUqGcOnBvzutof_jVvqWNIKVAgN379yG5c_VA2vD55VkL0MDXydRvd_49NeIXKjAtgF5tti3K2lNoXTYTWw43OMCOvH2uni8YdfpvXiEYWp-G_jt1Lzlak_bdppeRxHWD_mQjSgRLkAGrTrRTtd3lwthc9WWQTz-n-9hORj_Gg3gRa-dUWoWBPZav4OjW9hrbTey3jVp1a1GMn48pyOq9sJw')
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
