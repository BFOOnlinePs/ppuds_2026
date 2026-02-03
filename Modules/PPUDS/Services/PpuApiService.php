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
            ->withToken('eyJhbGciOiJSUzI1NiIsImtpZCI6ImNhZThlOTMyM2NhMzE0NjRkZDA4OTc5NTc4MTg3NzlkIiwidHlwIjoiYXQrand0In0.eyJuYmYiOjE3NzAwMjIwMDYsImV4cCI6MTc3MDAyNTYwNiwiaXNzIjoiaHR0cHM6Ly9teS5wcHUuZWR1IiwiYXVkIjoiRXh0ZXJuYWxBcGlzLmFwaSIsImNsaWVudF9pZCI6IkRTTW9iaWxlLnBwdSIsInN1YiI6IkRTVGVzdGluZyIsImF1dGhfdGltZSI6MTc3MDAyMjAwNiwiaWRwIjoibG9jYWwiLCJ1c2VyX25vIjoiMTQ0MDEwMSIsInVzZXJfdHlwZSI6IjEiLCJyb2xlIjoiODMiLCJuYW1lIjoiRFNUZXN0aW5nIiwic2Vzc2lvbl9pZCI6IjUwOTI3ZGUyLWU2YzUtNDJlZS04NWE1LTc0NzA2NTBkMjQ0MyIsInNjb3BlIjpbImVtYWlsIiwib3BlbmlkIiwicHJvZmlsZSIsInJvbGUiLCJ1c2Vybm8iLCJFeHRlcm5hbEFwaXMuYXBpIiwib2ZmbGluZV9hY2Nlc3MiXSwiYW1yIjpbInB3ZCJdfQ.MxYAoFiyVS6w_WHWPwbjJFvw2_nqFGb-tM-rhYutSGCg560pkLemVyA89QBZUjzLSjGmXpZRnzgtfYkT5MeMvow-jg3WJVbsE93jWPoywFx2kZUGduSutaI7fZ06KIVuRvvURwcffRknTS5hABsoFkLHvIEBfdqt9sG1why39mbperjna9ssSRQMhl6B9BMT5BLq4wb-cyfo2UB136-7dJHJkCzDimWPzAvaGz5tD66fqGCM3XXTbjA14KVYy9G_wu3JRfiEH1juLfds3bSwdshboL6VLgpZrOIsu-6R1c12wkmDUxkX-zhkyunhlfcbIRyIoNbv9riSYVKHqpMbqA')
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
                ->withToken('eyJhbGciOiJSUzI1NiIsImtpZCI6ImNhZThlOTMyM2NhMzE0NjRkZDA4OTc5NTc4MTg3NzlkIiwidHlwIjoiYXQrand0In0.eyJuYmYiOjE3NzAwMjIwMDYsImV4cCI6MTc3MDAyNTYwNiwiaXNzIjoiaHR0cHM6Ly9teS5wcHUuZWR1IiwiYXVkIjoiRXh0ZXJuYWxBcGlzLmFwaSIsImNsaWVudF9pZCI6IkRTTW9iaWxlLnBwdSIsInN1YiI6IkRTVGVzdGluZyIsImF1dGhfdGltZSI6MTc3MDAyMjAwNiwiaWRwIjoibG9jYWwiLCJ1c2VyX25vIjoiMTQ0MDEwMSIsInVzZXJfdHlwZSI6IjEiLCJyb2xlIjoiODMiLCJuYW1lIjoiRFNUZXN0aW5nIiwic2Vzc2lvbl9pZCI6IjUwOTI3ZGUyLWU2YzUtNDJlZS04NWE1LTc0NzA2NTBkMjQ0MyIsInNjb3BlIjpbImVtYWlsIiwib3BlbmlkIiwicHJvZmlsZSIsInJvbGUiLCJ1c2Vybm8iLCJFeHRlcm5hbEFwaXMuYXBpIiwib2ZmbGluZV9hY2Nlc3MiXSwiYW1yIjpbInB3ZCJdfQ.MxYAoFiyVS6w_WHWPwbjJFvw2_nqFGb-tM-rhYutSGCg560pkLemVyA89QBZUjzLSjGmXpZRnzgtfYkT5MeMvow-jg3WJVbsE93jWPoywFx2kZUGduSutaI7fZ06KIVuRvvURwcffRknTS5hABsoFkLHvIEBfdqt9sG1why39mbperjna9ssSRQMhl6B9BMT5BLq4wb-cyfo2UB136-7dJHJkCzDimWPzAvaGz5tD66fqGCM3XXTbjA14KVYy9G_wu3JRfiEH1juLfds3bSwdshboL6VLgpZrOIsu-6R1c12wkmDUxkX-zhkyunhlfcbIRyIoNbv9riSYVKHqpMbqA')
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
