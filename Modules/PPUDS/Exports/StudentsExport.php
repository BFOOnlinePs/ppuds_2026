<?php

namespace Modules\PPUDS\Exports;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Enums\CvStatus;
use Modules\PPUDS\Enums\StudentGender;

class StudentsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Arabic Name'),
            __('English Name'),
            __('Email'),
            __('Phone'),
            __('Major'),
            __('Enrollment Year'),
            __('Semester Level'),
            __('Date of Birth'),
            __('Gender'),
            __('Tawjihi GPA'),
            __('CV Status'),
            __('LinkedIn'),
            __('Behance'),
            __('GitHub'),
            __('Roles'),
            __('Created At'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'user.roles',
            'major.translations',
        ]);

        foreach ($query->lazy(500) as $studentProfile) {
            yield $this->rowFor($studentProfile);
        }
    }

    protected function rowFor(StudentProfile $studentProfile): array
    {
        $user = $studentProfile->user;

        return [
            (string) $studentProfile->student_number,
            (string) $user?->name,
            (string) $user?->name_en,
            (string) $user?->email,
            (string) $user?->phone,
            (string) $studentProfile->major?->name,
            (string) $studentProfile->enrollment_year,
            (string) $studentProfile->semester_level,
            $this->dateValue($studentProfile->dob),
            $this->genderLabel($studentProfile->gender),
            (string) $studentProfile->tawjihi_gpa,
            $this->cvStatusLabel($studentProfile->cv_status),
            (string) $studentProfile->linkedin_url,
            (string) $studentProfile->behance_url,
            (string) $studentProfile->github_url,
            $user?->roles?->pluck('name')->implode(', ') ?? '',
            $this->dateTimeValue($studentProfile->created_at),
        ];
    }

    protected function dateValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }

    protected function dateTimeValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value;
    }

    protected function genderLabel(mixed $gender): string
    {
        if ($gender instanceof StudentGender) {
            return (string) $gender->getLabel();
        }

        if (is_numeric($gender)) {
            return (string) (StudentGender::tryFrom((int) $gender)?->getLabel() ?? $gender);
        }

        return match (strtolower((string) $gender)) {
            'male' => __('Male'),
            'female' => __('Female'),
            default => (string) $gender,
        };
    }

    protected function cvStatusLabel(mixed $status): string
    {
        if ($status instanceof CvStatus) {
            return (string) $status->getLabel();
        }

        if (is_numeric($status)) {
            return (string) (CvStatus::tryFrom((int) $status)?->getLabel() ?? $status);
        }

        return match (strtolower((string) $status)) {
            'pending' => __('Pending'),
            'accepted' => __('Accepted'),
            'rejected' => __('Rejected'),
            default => (string) $status,
        };
    }
}
