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
            ->withToken('eyJhbGciOiJSUzI1NiIsImtpZCI6ImNhZThlOTMyM2NhMzE0NjRkZDA4OTc5NTc4MTg3NzlkIiwidHlwIjoiYXQrand0In0.eyJuYmYiOjE3NzA0NzMyNzIsImV4cCI6MTc3MDQ3Njg3MiwiaXNzIjoiaHR0cHM6Ly9teS5wcHUuZWR1IiwiYXVkIjoiRXh0ZXJuYWxBcGlzLmFwaSIsImNsaWVudF9pZCI6IkRTTW9iaWxlLnBwdSIsInN1YiI6IkRTVGVzdGluZyIsImF1dGhfdGltZSI6MTc3MDQ3MzI3MiwiaWRwIjoibG9jYWwiLCJ1c2VyX25vIjoiMTQ0MDEwMSIsInVzZXJfdHlwZSI6IjEiLCJyb2xlIjoiODMiLCJuYW1lIjoiRFNUZXN0aW5nIiwic2Vzc2lvbl9pZCI6ImMwZjgwOGY5LWMzYjMtNDNiNy1hODZiLWE0MWE5YjRiNmViOSIsInNjb3BlIjpbImVtYWlsIiwib3BlbmlkIiwicHJvZmlsZSIsInJvbGUiLCJ1c2Vybm8iLCJFeHRlcm5hbEFwaXMuYXBpIiwib2ZmbGluZV9hY2Nlc3MiXSwiYW1yIjpbInB3ZCJdfQ.yO2Tq1N9kVsQ6_ZE5jtaLPNokz6SWWI-v--ciGx2wWTVQawCar1WCSUHEHBTr81TwHs_ywrBd6DcGVT79HTofYOmYx52Sx3yIQaywqHMS-9A_2lrcLJEKq3y3qif9gDWXzrQQqPB2rRB_S43ifLBveLtWYUhjr_KZoo-NCe9w2n39SnZ-jp2w8q7jYqoKDO4pJbJ0YMAkAVWvDRb2BGWcH9mzAYsWs2Ma8iEHydhVqyIP4vAZZNaeqO9ezYDP96AfEsvsPW3nuBUKEJ8DY5OqAdTkU75I0oN_AFAMqUjkgo4qdKfsmInpxzRG5GORJgadirzwC0WZAqz1Bx2GE1xAw')
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
                ->withToken('eyJhbGciOiJSUzI1NiIsImtpZCI6ImNhZThlOTMyM2NhMzE0NjRkZDA4OTc5NTc4MTg3NzlkIiwidHlwIjoiYXQrand0In0.eyJuYmYiOjE3NzA0NzMyNzIsImV4cCI6MTc3MDQ3Njg3MiwiaXNzIjoiaHR0cHM6Ly9teS5wcHUuZWR1IiwiYXVkIjoiRXh0ZXJuYWxBcGlzLmFwaSIsImNsaWVudF9pZCI6IkRTTW9iaWxlLnBwdSIsInN1YiI6IkRTVGVzdGluZyIsImF1dGhfdGltZSI6MTc3MDQ3MzI3MiwiaWRwIjoibG9jYWwiLCJ1c2VyX25vIjoiMTQ0MDEwMSIsInVzZXJfdHlwZSI6IjEiLCJyb2xlIjoiODMiLCJuYW1lIjoiRFNUZXN0aW5nIiwic2Vzc2lvbl9pZCI6ImMwZjgwOGY5LWMzYjMtNDNiNy1hODZiLWE0MWE5YjRiNmViOSIsInNjb3BlIjpbImVtYWlsIiwib3BlbmlkIiwicHJvZmlsZSIsInJvbGUiLCJ1c2Vybm8iLCJFeHRlcm5hbEFwaXMuYXBpIiwib2ZmbGluZV9hY2Nlc3MiXSwiYW1yIjpbInB3ZCJdfQ.yO2Tq1N9kVsQ6_ZE5jtaLPNokz6SWWI-v--ciGx2wWTVQawCar1WCSUHEHBTr81TwHs_ywrBd6DcGVT79HTofYOmYx52Sx3yIQaywqHMS-9A_2lrcLJEKq3y3qif9gDWXzrQQqPB2rRB_S43ifLBveLtWYUhjr_KZoo-NCe9w2n39SnZ-jp2w8q7jYqoKDO4pJbJ0YMAkAVWvDRb2BGWcH9mzAYsWs2Ma8iEHydhVqyIP4vAZZNaeqO9ezYDP96AfEsvsPW3nuBUKEJ8DY5OqAdTkU75I0oN_AFAMqUjkgo4qdKfsmInpxzRG5GORJgadirzwC0WZAqz1Bx2GE1xAw')
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
