<?php

namespace Modules\PPUDS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Major;

class ProcessStudentSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $data = $this->data;

        if (!isset($data['studentNo'])) return;

        DB::transaction(function () use ($data) {
            $studentId = $data['studentNo'];
            $email = $studentId . '@ppu.edu.ps';

            $user = User::firstOrNew(['email' => $email]);

            $user->fill([
                'name'      => $data['studentNameArabic'],
                'name_en'   => $data['studentNameEnglish'],
                'email'     => $email,
                'phone'     => $this->sanitizePhone($data['studentMobile'] ?? '00000000'),
                'password'  => Hash::make($studentId),
            ])->save();

            if (method_exists($user, 'assignRole')) {
                $user->assignRole('Student');
            }

            if ($user->wasRecentlyCreated && method_exists($user, 'generateAvatar')) {
                $user->generateAvatar();
            }

            $localMajor = Major::where('reference_code', $data['majorNo'])->first();

            $user->studentProfile()->updateOrCreate(
                ['student_number' => $studentId],
                [
                    'dob'             => isset($data['studentBirthDate']) ? substr($data['studentBirthDate'], 0, 10) : null,
                    'gender'          => ($data['studentSex'] ?? 0) == 0 ? 'Male' : 'Female',
                    'tawjihi_gpa'     => $data['studentTawjihiGrade'],
                    'enrollment_year' => $data['admissionYear'],
                    'semester_level'  => $data['levelSem'],
                    'major_id'        => $localMajor?->id, // Null safe operator
                ]
            );
        });
    }

    /**
     * دالة مساعدة لتنظيف رقم الهاتف داخل الـ Job
     */
    private function sanitizePhone($phone)
    {
        if (!$phone) return '00000000';
        $westernArray = ['0','1','2','3','4','5','6','7','8','9'];
        $easternArray = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $phone = str_replace($easternArray, $westernArray, $phone);
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
