<?php

namespace Modules\PPUDS\Exports;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\StudentReport;

/**
 * Daily student reports, shared by the "Today Reports" page and the reports
 * tab shown inside student / placement details.
 */
class StudentReportsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student Name'),
            __('Company'),
            __('Branch'),
            __('Department'),
            __('Attendance Date'),
            __('Report Text'),
            __('Company Feedback'),
            __('Academic Feedback'),
            __('Attachments'),
            __('Location'),
            __('Submitted At'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'media',
            'studentAttendance.studentCompany.student.studentProfile',
            'studentAttendance.studentCompany.company.translations',
            'studentAttendance.studentCompany.branch.translations',
            'studentAttendance.studentCompany.department.translations',
        ]);

        foreach ($query->lazy(500) as $report) {
            yield $this->rowFor($report);
        }
    }

    protected function rowFor(StudentReport $report): array
    {
        $studentCompany = $report->studentAttendance?->studentCompany;
        $student = $studentCompany?->student;

        return [
            (string) ($student?->studentProfile?->student_number ?? '---'),
            (string) ($student?->name ?? '---'),
            (string) ($studentCompany?->company?->name ?? '---'),
            (string) ($studentCompany?->branch?->name ?? '---'),
            (string) ($studentCompany?->department?->name ?? '---'),
            $this->dateValue($report->studentAttendance?->attendance_date),
            $this->plainText($report->report_text),
            $this->plainText($report->company_feedback),
            $this->plainText($report->academic_feedback),
            (string) $report->getMedia('file_report')->count(),
            $this->locationValue($report),
            $this->dateTimeValue($report->created_at),
        ];
    }

    /** Report bodies are rich text; the spreadsheet wants the words only. */
    protected function plainText(?string $value): string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    protected function locationValue(StudentReport $report): string
    {
        if (blank($report->submit_latitude) || blank($report->submit_longitude)) {
            return '---';
        }

        return $report->submit_latitude.', '.$report->submit_longitude;
    }

    protected function dateValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }

    protected function dateTimeValue(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value;
    }
}
