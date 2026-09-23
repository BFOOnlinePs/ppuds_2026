@php
    use Modules\PPUDS\Entities\Registration;

    $student = $this->student;
    $profile = $student->studentProfile;
    $placements = $this->placements;
    $totals = $this->totals;
    $tabs = $this->tabs();

    // Tints are inline rather than Tailwind colour utilities, same as the
    // evaluation supervisor record page, so every tile renders with its colour.
    $badgeColors = [
        'primary' => '#6366f1',
        'success' => '#10b981',
        'warning' => '#f59e0b',
        'danger' => '#ef4444',
        'gray' => '#6b7280',
    ];

    $cards = [
        [
            'label' => __('Companies'),
            'value' => $totals['companies'],
            'hint' => __('Trainings') . ': ' . $totals['trainings'],
            'icon' => 'solar-city-bold-duotone',
            'color' => '#6366f1',
        ],
        [
            'label' => __('Attendance Days'),
            'value' => $totals['attendance_days'],
            'hint' => __('Required Working Days') . ': ' . $totals['required_days'],
            'icon' => 'solar-calendar-mark-bold-duotone',
            'color' => '#10b981',
        ],
        [
            'label' => __('Actual Working Hours'),
            'value' => $totals['working_hours'],
            'icon' => 'solar-clock-circle-bold-duotone',
            'hint' => __('Daily Reports'),
            'color' => '#0ea5e9',
        ],
        [
            'label' => __('Total Absence Days'),
            'value' => $totals['absence_days'],
            'hint' => __('Unexcused Absence Days') . ': ' . $totals['unexcused_days'],
            'icon' => 'solar-danger-triangle-bold-duotone',
            'color' => '#ef4444',
        ],
        [
            'label' => __('Leave Requests'),
            'value' => $totals['leave_requests'],
            'hint' => __('Leave Request Days') . ': ' . $totals['leave_days'],
            'icon' => 'solar-calendar-date-bold-duotone',
            'color' => '#f59e0b',
        ],
    ];

    $profileRows = [
        __('Student Number') => $profile?->student_number,
        __('Major') => $profile?->major?->name,
        __('Email') => $student->email,
        __('Phone') => $student->phone,
        __('Enrollment Year') => $profile?->enrollment_year,
        __('Semester Level') => $profile?->semester_level,
        __('Tawjihi GPA') => $profile?->tawjihi_gpa,
        __('Date of Birth') => $profile?->dob?->format('Y-m-d'),
        __('Gender') => $profile?->gender?->getLabel(),
    ];
@endphp

<div class="space-y-6">

    {{-- Who the record is about --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <img src="{{ $student->getAvatarUrlAttribute() }}"
                     alt="{{ $student->name }}"
                     class="h-16 w-16 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-800">

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $student->name }}
                        </h1>

                        @if (filled($profile?->student_number))
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                  style="color: #6366f1; background-color: #6366f11f;">
                                {{ $profile->student_number }}
                            </span>
                        @endif
                    </div>

                    @if (filled($student->name_en))
                        <div class="truncate text-sm text-gray-500 dark:text-gray-400">
                            {{ $student->name_en }}
                        </div>
                    @endif

                    <div class="truncate text-sm text-gray-500 dark:text-gray-400">
                        {{ $student->email ?: '—' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Everything the profile holds about the student --}}
        <div class="mt-4 grid grid-cols-1 gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-3 dark:border-gray-800">
            @foreach ($profileRows as $label => $value)
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ filled($value) ? $value : '—' }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Headline numbers across every training, not the latest one only --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($cards as $card)
            <div class="rounded-xl border border-gray-200 bg-white p-4 transition hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $card['label'] }}
                        </div>
                        <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $card['value'] }}
                        </div>
                        <div class="mt-1 truncate text-xs text-gray-400 dark:text-gray-500">
                            {{ $card['hint'] }}
                        </div>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                          style="color: {{ $card['color'] }}; background-color: {{ $card['color'] }}1f;">
                        @svg($card['icon'], 'h-6 w-6')
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- The records behind the numbers --}}
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap gap-2 border-b border-gray-100 p-3 dark:border-gray-800">
            @foreach ($tabs as $key => $meta)
                <button type="button"
                        wire:click="selectTab('{{ $key }}')"
                        @class([
                            'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
                            'bg-primary-50 text-primary-700 dark:bg-gray-800 dark:text-white' => $tab === $key,
                            'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800' => $tab !== $key,
                        ])>
                    @svg($meta['icon'], 'h-5 w-5')
                    <span>{{ $meta['label'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="p-3">
            @if ($tab === 'trainings')
                @livewire(\Modules\PPUDS\Livewire\Pages\Student\Details\StudentCompany\Index::class,
                    ['studentId' => $studentId],
                    key('evaluation-supervisor-student-trainings-' . $studentId))

            @elseif ($tab === 'attendance')
                @livewire(\Modules\PPUDS\Livewire\Pages\Student\Details\StudentAttendance\Index::class,
                    ['studentId' => $studentId],
                    key('evaluation-supervisor-student-attendance-' . $studentId))

            @elseif ($tab === 'daily-reports')
                @livewire(\Modules\PPUDS\Livewire\Pages\StudentAttendanceReport\Index::class,
                    ['filters' => ['student_id' => $studentId]],
                    key('evaluation-supervisor-student-daily-reports-' . $studentId))

            @elseif ($tab === 'leave-requests')
                @livewire(\Modules\PPUDS\Livewire\Pages\Student\Details\LeaveRequest\Index::class,
                    ['studentId' => $studentId],
                    key('evaluation-supervisor-student-leave-requests-' . $studentId))

            @elseif ($tab === 'final-report')
                {{-- The final report exactly as the student submitted it, one per registration --}}
                @forelse ($this->finalReports as $registration)
                    @php
                        $report = $registration->finalReport;
                        $placement = $registration->studentCompany;
                        $attachmentUrl = $registration->getFirstMediaUrl('final_file') ?: null;
                        $presentationUrl = $registration->getFirstMediaUrl(Registration::PRESENTATION_COLLECTION) ?: null;
                    @endphp

                    <div class="mb-4 space-y-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ $placement?->company?->name ?: __('Final Report') }}
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                  style="color: {{ $report->isSubmitted() ? '#10b981' : '#f59e0b' }}; background-color: {{ $report->isSubmitted() ? '#10b981' : '#f59e0b' }}1f;">
                                {{ $report->isSubmitted() ? __('Submitted') : __('Not Submitted') }}
                            </span>
                        </div>

                        <div>
                            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ __('Submission Details') }}
                            </div>

                            @if ($placement)
                                @include('ppuds::livewire.pages.final-delivery-report.report-summary', [
                                    'record' => $placement,
                                    'report' => $report,
                                    'attachmentUrl' => $attachmentUrl,
                                    'presentationUrl' => $presentationUrl,
                                ])
                            @else
                                {{-- A registration with no placement row still has a report to read --}}
                                <div class="overflow-x-auto text-sm">
                                    <table class="w-full border-collapse text-start">
                                        <tbody>
                                            @foreach ([
                                                __('Course') => $registration->course?->name,
                                                __('Practical Training Supervisor') => $registration->supervisor?->name,
                                                __('Semester') => $registration->semester?->getLabel(),
                                                __('Year') => $registration->year,
                                                __('Delivery Status') => $report->status?->getLabel(),
                                                __('Submitted At') => $report->submitted_at?->format('Y-m-d H:i'),
                                            ] as $label => $value)
                                                <tr>
                                                    <td class="border px-3 py-2 text-xs font-semibold">{{ $label }}</td>
                                                    <td class="border px-3 py-2">{{ filled($value) ? $value : '---' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        <div>
                            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ __('Training Role Description') }}
                            </div>
                            <div class="prose max-w-none text-sm dark:prose-invert">
                                {!! filled($report->role_description) ? $report->role_description : '---' !!}
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ __('Training Tasks And Skills') }}
                            </div>
                            @include('ppuds::livewire.pages.final-delivery-report.report-details', ['report' => $report])
                        </div>

                        <div>
                            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ __('Summary') }}
                            </div>
                            <div class="prose max-w-none text-sm dark:prose-invert">
                                {!! filled($report->summary) ? $report->summary : '---' !!}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('No final report submitted yet') }}
                    </div>
                @endforelse

            @else
                {{-- Overview: every training on its own, so a student who moved
                     from one company to another is read company by company --}}
                @forelse ($placements as $placement)
                    @php
                        $absence = $this->absence($placement);
                        $leaves = $this->leaves($placement);
                        $statusColor = $badgeColors[$placement->status?->getColor() ?? 'gray'] ?? $badgeColors['gray'];

                        $attendanceFigures = [
                            __('Required Working Days') => $absence['required_working_days'],
                            __('Attendance Days') => (int) $placement->attendance_days,
                            __('Actual Working Hours') => $placement->actual_working_hours ?? 0,
                            __('Total Absence Days') => $absence['total_absence_days'],
                            __('Excused Absence Days') => $absence['excused_absence_days'],
                            __('Unexcused Absence Days') => $absence['unexcused_absence_days'],
                            __('Actual Absence Days') => $absence['actual_absence_days'],
                            __('Leave Request Days') => $absence['leave_request_days'],
                        ];

                        $leaveFigures = [
                            __('Leave Requests') => $leaves['total'],
                            __('Approved') => $leaves['approved'],
                            __('Pending') => $leaves['pending'],
                            __('Rejected') => $leaves['rejected'],
                        ];

                        $gradeFigures = [
                            __('Evaluation Supervisor Grade') => $this->formatGrade($placement->evaluation_score, $this->evaluationMaxGrade()),
                            __('University Supervisor Grade') => $this->formatGrade($placement->supervisor_score, $this->universityMaxGrade()),
                            __('Company Grade') => $this->formatGrade($this->companyGrade($placement), $this->companyMaxGrade()),
                            __('Total Grade') => $this->formatGrade($this->totalGrade($placement), $this->totalMaxGrade()),
                        ];

                        $placementRows = [
                            __('Branch') => $placement->branch?->name,
                            __('Department') => $placement->department?->name,
                            __('Course') => $placement->registration?->course?->name,
                            __('Semester') => $placement->registration?->semester?->getLabel(),
                            __('Year') => $placement->registration?->year,
                            __('Practical Training Supervisor') => $placement->registration?->supervisor?->name,
                            __('Evaluation Supervisor') => $placement->evaluationSupervisor?->name,
                            __('Training Period') => filled($absence['training_start'])
                                ? $absence['training_start'] . ' — ' . $absence['training_end']
                                : null,
                        ];
                    @endphp

                    <div class="mb-4 space-y-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">

                        {{-- Which company this block is about --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-800">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($placement->company_id && auth()->user()->can('Company Details List'))
                                    <a href="{{ route('companies.details', $placement->company_id) }}"
                                       class="font-semibold text-primary-600 hover:underline">
                                        {{ $placement->company?->name ?: '—' }}
                                    </a>
                                @else
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $placement->company?->name ?: '—' }}
                                    </span>
                                @endif

                                @if ($placement->status)
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                          style="color: {{ $statusColor }}; background-color: {{ $statusColor }}1f;">
                                        {{ $placement->status->getLabel() }}
                                    </span>
                                @endif

                                @if ($this->isAssignedToViewer($placement))
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                          style="color: #6366f1; background-color: #6366f11f;">
                                        {{ __('Assigned to you') }}
                                    </span>
                                @endif
                            </div>

                            @can('StudentCompany Details')
                                <a href="{{ route('student-companies.details', $placement->id) }}"
                                   class="text-sm text-primary-600 hover:underline">
                                    {{ __('View Details') }}
                                </a>
                            @endcan
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($placementRows as $label => $value)
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                                    <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ filled($value) ? $value : '—' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Attendance day by day, the same figures as the absence report --}}
                        <div>
                            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ __('Attendance Details') }}
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($attendanceFigures as $label => $value)
                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                                        <div class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">{{ $value }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Leaves --}}
                        <div>
                            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ __('Leave Requests') }}
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($leaveFigures as $label => $value)
                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                                        <div class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">{{ $value }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Grades --}}
                        <div>
                            <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ __('Grades') }}
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($gradeFigures as $label => $value)
                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                                        <div class="mt-1 text-sm font-bold text-gray-900 dark:text-gray-100">{{ $value }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- The absent days themselves, not their count only --}}
                        @foreach ([
                            __('Unexcused Absence Dates') => $absence['unexcused_absence_dates'],
                            __('Excused Absence Dates') => $absence['excused_absence_dates'],
                        ] as $label => $dates)
                            @if (! empty($dates))
                                <div>
                                    <div class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $label }}</div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($dates as $date)
                                            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                                  dir="ltr">
                                                {{ $date }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @empty
                    <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ __('No trainings recorded yet') }}
                    </div>
                @endforelse
            @endif
        </div>
    </div>
</div>
