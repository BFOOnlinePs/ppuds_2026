<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentAttendance;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\AttendanceStatus;

class StudentAttendanceReportExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Company Name'),
            __('Branch'),
            __('Date'),
            __('Check In'),
            __('Check Out'),
            __('Status'),
            __('Notes'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with($query->getModel() instanceof StudentCompany
            ? ['company.translations', 'branch.translations', 'student.studentProfile']
            : ['studentCompany.company.translations', 'studentCompany.branch.translations', 'studentCompany.student.studentProfile']);

        foreach ($query->lazy(500) as $record) {
            yield $this->rowFor($record);
        }
    }

    protected function rowFor(StudentAttendance|StudentCompany $record): array
    {
        $isAbsentRecord = $record instanceof StudentCompany;
        $studentCompany = $isAbsentRecord ? $record : $record->studentCompany;
        $student = $isAbsentRecord ? $record->student : $studentCompany?->student;

        return [
            (string) ($student?->studentProfile?->student_number ?? '---'),
            (string) ($student?->name ?? '---'),
            (string) ($studentCompany?->company?->name ?? '---'),
            (string) ($studentCompany?->branch?->name ?? '---'),
            $isAbsentRecord ? now()->toDateString() : (string) $record->attendance_date?->toDateString(),
            $isAbsentRecord ? '---' : (string) ($record->check_in?->format('H:i') ?? '---'),
            $isAbsentRecord ? '---' : (string) ($record->check_out?->format('H:i') ?? '---'),
            $isAbsentRecord ? __('Absent') : $this->statusLabel($record->status),
            $isAbsentRecord ? '' : (string) ($record->description ?? ''),
        ];
    }

    protected function statusLabel(mixed $status): string
    {
        if ($status instanceof AttendanceStatus) {
            return (string) $status->getLabel();
        }

        if (is_numeric($status)) {
            return (string) (AttendanceStatus::tryFrom((int) $status)?->getLabel() ?? $status);
        }

        return (string) ($status ?: '---');
    }
}
