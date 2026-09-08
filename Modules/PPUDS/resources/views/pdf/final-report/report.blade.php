@extends('core::pdf.app')

@php
    use Modules\PPUDS\Enums\FinalReportItemType;

    $student = $registration?->student;
    $studentCompany = $registration?->studentCompany;

    $contributions = $report->items->where('type', FinalReportItemType::CONTRIBUTION)->values();
    $difficulties = $report->items->where('type', FinalReportItemType::DIFFICULTY)->values();

    $submissionDetails = [
        __('Student Name') => $student?->name,
        __('Student Number') => $student?->studentProfile?->student_number,
        __('Course') => $registration?->course?->name,
        __('Company') => $studentCompany?->company?->name,
        __('Branch') => $studentCompany?->branch?->name,
        __('Department') => $studentCompany?->department?->name,
        __('University Supervisor') => $registration?->supervisor?->name,
        __('Semester') => $registration?->semester?->getLabel(),
        __('Year') => $registration?->year,
        __('Delivery Status') => $report->status?->getLabel(),
        __('Submitted At') => $report->submitted_at?->format('Y-m-d H:i'),
        __('Last Updated') => $report->updated_at?->format('Y-m-d H:i'),
    ];
@endphp

@section('content')

    <div class="header-title">
        <h2>{{ __('Final Report') }}</h2>
        <p>{{ __('Printed At') }}: {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    <h3>{{ __('Submission Details') }}</h3>

    <table dir="rtl">
        <tbody>
            @foreach ($submissionDetails as $label => $value)
                <tr>
                    <th style="width: 30%">{{ $label }}</th>
                    <td>{{ filled($value) ? $value : '---' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>{{ __('Training Role Description') }}</h3>

    <div>
        @if (filled($report->role_description))
            {!! str($report->role_description)->sanitizeHtml() !!}
        @else
            ---
        @endif
    </div>

    <h3>{{ __('Training Tasks') }}</h3>

    <table dir="rtl">
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th style="width: 25%">{{ __('Training Task') }}</th>
                <th style="width: 30%">{{ __('Task Details') }}</th>
                <th style="width: 20%">{{ __('Work Duration In Days And Hours') }}</th>
                <th style="width: 20%">{{ __('Notes') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report->tasks as $task)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $task->task_name }}</td>
                    <td>{{ $task->task_details ?: '---' }}</td>
                    <td>{{ $task->work_duration ?: '---' }}</td>
                    <td>{{ $task->notes ?: '---' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px;">
                        {{ __('No records found.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h3>{{ __('Skills Gained From Training') }}</h3>

    <table dir="rtl">
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th style="width: 35%">{{ __('Skill') }}</th>
                <th style="width: 20%">{{ __('Mastery Percentage') }}</th>
                <th style="width: 40%">{{ __('Notes') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report->skills as $skill)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $skill->skill }}</td>
                    <td>{{ $skill->mastery_percentage !== null ? $skill->mastery_percentage.'%' : '---' }}</td>
                    <td>{{ $skill->notes ?: '---' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px;">
                        {{ __('No records found.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @foreach ([__('Key Contributions') => $contributions, __('Training Difficulties') => $difficulties] as $title => $items)
        <h3>{{ $title }}</h3>

        <table dir="rtl">
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td style="width: 5%">{{ $loop->iteration }}</td>
                        <td>{{ $item->content }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" style="text-align: center; padding: 20px;">
                            {{ __('No records found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <h3>{{ __('Summary') }}</h3>

    <div>
        @if (filled($report->summary))
            {!! str($report->summary)->sanitizeHtml() !!}
        @else
            ---
        @endif
    </div>
@endsection
