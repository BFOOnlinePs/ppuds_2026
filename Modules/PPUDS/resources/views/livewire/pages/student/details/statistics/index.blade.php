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
    $financial = $this->financialRecord;

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

    {{-- Financial record: what each company actually paid the student --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                {{ __('Financial Record') }}
            </h3>

            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span>{{ __('Total Received') }}:</span>
                @forelse ($financial['received'] as $received)
                    <span class="rounded-lg px-2 py-1 font-semibold"
                          style="color: #84cc16; background-color: #84cc161f;">
                        {{ number_format($received['amount'], 2) }} {{ $received['currency'] }}
                    </span>
                @empty
                    <span class="font-semibold text-gray-900 dark:text-gray-100">—</span>
                @endforelse
            </div>
        </div>

        @if (filled($financial['companies']))
            {{-- Per company: how much of it was already received --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-3 py-2 text-start font-medium">{{ __('Company') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Records') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Paid') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Unpaid') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Total') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Currency') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($financial['companies'] as $row)
                            <tr>
                                <td class="px-3 py-2 font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $row['company'] }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $row['records'] }}</td>
                                <td class="px-3 py-2 font-semibold" style="color: #10b981;">
                                    {{ number_format($row['paid'], 2) }}
                                </td>
                                <td class="px-3 py-2 font-semibold" style="color: #f43f5e;">
                                    {{ number_format($row['unpaid'], 2) }}
                                </td>
                                <td class="px-3 py-2 font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($row['total'], 2) }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $row['currency'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- The individual payments behind those totals --}}
            <h4 class="mb-2 mt-4 text-xs font-semibold text-gray-500 dark:text-gray-400">
                {{ __('Payments') }}
            </h4>
            <div class="max-h-96 overflow-x-auto overflow-y-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-3 py-2 text-start font-medium">{{ __('Date') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Company') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Branch') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Payment Value') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Currency') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($financial['entries'] as $payment)
                            @php
                                $statusColor = $payment->status === \Modules\PPUDS\Enums\PaymentStatus::PAID
                                    ? '#10b981'
                                    : '#f43f5e';
                            @endphp
                            <tr>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                    {{ $payment->created_at?->format('Y-m-d') ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                    {{ $payment->studentCompany?->company?->name ?: '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                    {{ $payment->studentCompany?->branch?->name ?: '—' }}
                                </td>
                                <td class="px-3 py-2 font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format((float) $payment->payment_value, 2) }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                    {{ $payment->currency?->name ?: '—' }}
                                </td>
                                <td class="px-3 py-2">
                                    <span class="rounded-lg px-2 py-1 text-xs font-semibold"
                                          style="color: {{ $statusColor }}; background-color: {{ $statusColor }}1f;">
                                        {{ $payment->status?->getLabel() ?? '—' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No records found.') }}</p>
        @endif
    </div>
</div>
