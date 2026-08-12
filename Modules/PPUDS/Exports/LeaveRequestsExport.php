<?php

namespace Modules\PPUDS\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\PPUDS\Entities\LeaveRequest;
use Modules\PPUDS\Enums\LeaveRequestStatus;
use Modules\PPUDS\Enums\LeaveRequestType;

class LeaveRequestsExport implements FromGenerator, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Builder $query) {}

    public function headings(): array
    {
        return [
            __('Student Number'),
            __('Student'),
            __('Company'),
            __('Type'),
            __('Start Date'),
            __('End Date'),
            __('Reason'),
            __('Company Status'),
            __('University Status'),
        ];
    }

    public function generator(): Generator
    {
        $query = clone $this->query;

        $query->with([
            'studentCompany.company.translations',
            'studentCompany.student.studentProfile',
        ]);

        foreach ($query->lazy(500) as $leaveRequest) {
            yield $this->rowFor($leaveRequest);
        }
    }

    protected function rowFor(LeaveRequest $leaveRequest): array
    {
        $studentCompany = $leaveRequest->studentCompany;
        $student = $studentCompany?->student;

        return [
            (string) ($student?->studentProfile?->student_number ?? '---'),
            (string) ($student?->name ?? '---'),
            (string) ($studentCompany?->company?->name ?? '---'),
            $this->typeLabel($leaveRequest->type),
            (string) $leaveRequest->start_at?->format('Y-m-d H:i'),
            (string) $leaveRequest->end_at?->format('Y-m-d H:i'),
            (string) ($leaveRequest->reason ?? ''),
            $this->statusLabel($leaveRequest->company_approval),
            $this->statusLabel($leaveRequest->university_approval),
        ];
    }

    protected function typeLabel(mixed $type): string
    {
        if ($type instanceof LeaveRequestType) {
            return (string) $type->getLabel();
        }

        if (is_numeric($type)) {
            return (string) (LeaveRequestType::tryFrom((int) $type)?->getLabel() ?? $type);
        }

        return (string) ($type ?: '---');
    }

    protected function statusLabel(mixed $status): string
    {
        if ($status instanceof LeaveRequestStatus) {
            return (string) $status->getLabel();
        }

        if (is_numeric($status)) {
            return (string) (LeaveRequestStatus::tryFrom((int) $status)?->getLabel() ?? $status);
        }

        return (string) ($status ?: '---');
    }
}
