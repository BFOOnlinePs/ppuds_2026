<?php

namespace Modules\PPUDS\Exports;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;

class StudentsWithCompaniesExport implements FromGenerator, ShouldAutoSize, WithHeadings
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
            __('Has Company'),
            __('Companies'),
            __('Branches'),
            __('Departments'),
            __('Training Status'),
            __('Courses'),
            __('Semesters'),
            __('Years'),
            __('Supervisors'),
            __('Created At'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'major.translations',
            'user.studentCompanies.branch.translations',
            'user.studentCompanies.company.translations',
            'user.studentCompanies.department.translations',
            'user.studentCompanies.registration.course.translations',
            'user.studentCompanies.registration.supervisor',
        ]);

        foreach ($query->lazy(500) as $studentProfile) {
            yield $this->rowFor($studentProfile);
        }
    }

    protected function rowFor(StudentProfile $studentProfile): array
    {
        $user = $studentProfile->user;
        $studentCompanies = $this->studentCompaniesWithCompanies($user?->studentCompanies ?? collect());
        $hasCompany = $studentCompanies->isNotEmpty();

        return [
            (string) $studentProfile->student_number,
            (string) $user?->name,
            (string) $user?->name_en,
            (string) $user?->email,
            (string) $user?->phone,
            (string) $studentProfile->major?->name,
            $hasCompany ? __('Yes') : __('No'),
            $hasCompany ? $this->joinUnique($studentCompanies, fn (StudentCompany $studentCompany) => $studentCompany->company?->name) : __('No Company Registered'),
            $this->joinUnique($studentCompanies, fn (StudentCompany $studentCompany) => $studentCompany->branch?->name),
            $this->joinUnique($studentCompanies, fn (StudentCompany $studentCompany) => $studentCompany->department?->name),
            $this->joinUnique($studentCompanies, fn (StudentCompany $studentCompany) => $this->statusLabel($studentCompany->status)),
            $this->joinUnique($studentCompanies, fn (StudentCompany $studentCompany) => $studentCompany->registration?->course?->name),
            $this->joinUnique($studentCompanies, fn (StudentCompany $studentCompany) => $this->semesterLabel($studentCompany->registration?->semester)),
            $this->joinUnique($studentCompanies, fn (StudentCompany $studentCompany) => $studentCompany->registration?->year),
            $this->joinUnique($studentCompanies, fn (StudentCompany $studentCompany) => $studentCompany->registration?->supervisor?->name),
            $this->dateTimeValue($studentProfile->created_at),
        ];
    }

    protected function studentCompaniesWithCompanies(Collection $studentCompanies): Collection
    {
        return $studentCompanies
            ->filter(fn (StudentCompany $studentCompany): bool => filled($studentCompany->company_id) && filled($studentCompany->company?->name))
            ->values();
    }

    protected function joinUnique(Collection $studentCompanies, callable $callback): string
    {
        return $studentCompanies
            ->map(fn (StudentCompany $studentCompany): string => trim((string) $callback($studentCompany)))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->implode(', ');
    }

    protected function statusLabel(mixed $status): string
    {
        if ($status instanceof TrainingStatus) {
            return (string) $status->getLabel();
        }

        if (is_numeric($status)) {
            return (string) (TrainingStatus::tryFrom((int) $status)?->getLabel() ?? $status);
        }

        return (string) $status;
    }

    protected function semesterLabel(mixed $semester): string
    {
        if ($semester instanceof SemesterType) {
            return (string) $semester->getLabel();
        }

        if (is_numeric($semester)) {
            return (string) (SemesterType::tryFrom((int) $semester)?->getLabel() ?? $semester);
        }

        return (string) $semester;
    }

    protected function dateTimeValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value;
    }
}
