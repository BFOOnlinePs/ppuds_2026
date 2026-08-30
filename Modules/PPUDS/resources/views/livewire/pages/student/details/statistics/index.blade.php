@php
    $trainings = $this->trainings;
    $attendance = $this->attendance;
    $fieldVisits = $this->fieldVisits;
    $leaveRequests = $this->leaveRequests;
    $reports = $this->reports;
    $payments = $this->payments;
    $registrations = $this->registrations;
    $workExperience = $this->workExperience;
    $training = $this->currentTraining;

    // Tints are inline rather than Tailwind colour utilities: the compiled
    // stylesheet only ships a handful of colour families, so a class like
    // bg-rose-50 would silently render as no background at all.
    $cards = [
        [
            'label' => __('Trainings'),
            'value' => $trainings['total'],
            'hint' => __('Active') . ': ' . $trainings['active'] . ' · ' . __('Finished') . ': ' . $trainings['finished'],
            'icon' => 'solar-case-round-bold-duotone',
            'color' => '#6366f1',
        ],
        [
            'label' => __('Companies'),
            'value' => $trainings['companies'],
            'hint' => __('Registrations') . ': ' . $registrations['total'],
            'icon' => 'solar-buildings-bold-duotone',
            'color' => '#0ea5e9',
        ],
        [
            'label' => __('Attendance Days'),
            'value' => $attendance['days'],
            'hint' => __('Records') . ': ' . $attendance['records'],
            'icon' => 'solar-calendar-mark-bold-duotone',
            'color' => '#10b981',
        ],
        [
            'label' => __('Working Hours'),
            'value' => number_format($attendance['working_hours'], 2),
            'hint' => __('Open Check Ins') . ': ' . $attendance['open'],
            'icon' => 'solar-clock-circle-bold-duotone',
            'color' => '#14b8a6',
        ],
        [
            'label' => __('Field Visits'),
            'value' => $fieldVisits['total'],
            'hint' => __('Duration (Mins)') . ': ' . $fieldVisits['total_minutes'],
            'icon' => 'solar-map-point-bold-duotone',
            'color' => '#f59e0b',
        ],
        [
            'label' => __('Leave Requests'),
            'value' => $leaveRequests['total'],
            'hint' => __('Pending') . ': ' . $leaveRequests['pending'],
            'icon' => 'solar-calendar-date-bold-duotone',
            'color' => '#f43f5e',
        ],
        [
            'label' => __('Daily Reports'),
            'value' => $reports['total'],
            'hint' => __('Today') . ': ' . $reports['today'],
            'icon' => 'solar-document-text-bold-duotone',
            'color' => '#8b5cf6',
        ],
        [
            'label' => __('Payment'),
            'value' => number_format($payments['total_amount'], 2),
            'hint' => __('Paid') . ': ' . number_format($payments['paid_amount'], 2),
            'icon' => 'solar-wallet-money-bold-duotone',
            'color' => '#84cc16',
        ],
    ];

    $lastAttendance = $attendance['last_at']
        ? \Illuminate\Support\Carbon::parse($attendance['last_at'])->format('Y-m-d')
        : '—';

    $breakdowns = [
        __('Attendance') => [
            __('Approved') => $attendance['approved'],
            __('Discrepancy') => $attendance['discrepancy'],
            __('Undetermined') => $attendance['undetermined'],
            __('Last Attendance') => $lastAttendance,
        ],
        __('Leave Requests') => [
            __('Approved') => $leaveRequests['approved'],
            __('Rejected') => $leaveRequests['rejected'],
            __('Pending') => $leaveRequests['pending'],
        ],
        __('Payment') => [
            __('Paid') => $payments['paid'],
            __('Unpaid') => $payments['unpaid'],
            __('Total') => $payments['total'],
        ],
        __('Registration') => [
            __('University Score') => $registrations['average_university_score'] ?: '—',
            __('Company Score') => $registrations['average_company_score'] ?: '—',
            __('Work Experience') => $workExperience['total'] . ' (' . __('Current') . ': ' . $workExperience['current'] . ')',
        ],
    ];
@endphp

<div class="space-y-6">

    {{-- Most recent placement --}}
    @if ($training)
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-semibold text-gray-500 dark:text-gray-400">
                {{ __('Current Training') }}
            </h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ([
                    __('Company') => $training->company?->name,
                    __('Branch') => $training->branch?->name,
                    __('Department') => $training->department?->name,
                    __('Supervisor') => $training->registration?->supervisor?->name,
                ] as $label => $value)
                    <div class="min-w-0">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ filled($value) ? $value : '—' }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Headline numbers --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
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

    {{-- Detailed breakdowns --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($breakdowns as $title => $rows)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                <dl class="space-y-2">
                    @foreach ($rows as $label => $value)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <dt class="min-w-0 truncate text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="shrink-0 font-semibold text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endforeach
    </div>
</div>
