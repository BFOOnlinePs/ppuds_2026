@php
    use Modules\PPUDS\Enums\SemesterType;

    $registration = $record->registration;
    $student = $record->student;

    $semester = $registration?->semester;
    $semesterLabel = $semester instanceof SemesterType
        ? $semester->getLabel()
        : (is_numeric($semester) ? (SemesterType::tryFrom((int) $semester)?->getLabel() ?? $semester) : $semester);

    $rows = [
        __('Student Name') => $student?->name,
        __('Student Number') => $student?->studentProfile?->student_number,
        __('Company') => $record->company?->name,
        __('Branch') => $record->branch?->name,
        __('Department') => $record->department?->name,
        __('University Supervisor') => $registration?->supervisor?->name,
        __('Semester') => $semesterLabel,
        __('Year') => $registration?->year,
        __('Delivery Status') => $report->status?->getLabel(),
        __('Submitted At') => $report->submitted_at?->format('Y-m-d H:i'),
        __('Last Updated') => $report->updated_at?->format('Y-m-d H:i'),
    ];
@endphp

<div class="space-y-4 text-sm">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-start">
            <tbody>
                @foreach ($rows as $label => $value)
                    <tr>
                        <td class="border px-3 py-2 text-xs font-semibold">{{ $label }}</td>
                        <td class="border px-3 py-2">{{ filled($value) ? $value : '---' }}</td>
                    </tr>
                @endforeach

                <tr>
                    <td class="border px-3 py-2 text-xs font-semibold">{{ __('Attachment') }}</td>
                    <td class="border px-3 py-2">
                        @if ($attachmentUrl)
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener noreferrer" class="text-primary-600">
                                {{ __('View File') }}
                            </a>
                        @else
                            <span class="text-gray-500">{{ __('No attachment') }}</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
