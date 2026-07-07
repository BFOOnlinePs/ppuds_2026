@php
    $records = $this->records();
@endphp

<div class="space-y-6">
    {{ $this->form }}

    @if ($records->count() > 0)
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach ($records as $studentCompany)
                @php
                    $summary = $this->summary($studentCompany);
                    $absenceDates = $summary['absence_dates'] ?? [];
                    $lateAttendances = $summary['late_attendances'] ?? [];
                @endphp

                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $studentCompany->student?->name ?? __('Unknown Student') }}
                                </h3>
                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                    <span>{{ __('Student Number') }}: {{ $studentCompany->student?->studentProfile?->student_number ?? '-' }}</span>
                                    <span>{{ __('Company') }}: {{ $studentCompany->company?->name ?? '-' }}</span>
                                    <span>{{ __('Branch') }}: {{ $studentCompany->branch?->name ?? '-' }}</span>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20">
                                    {{ __('Total Non Compliance Days') }}: {{ (int) ($summary['total_non_compliance_days'] ?? 0) }}
                                </span>
                                <span class="inline-flex items-center rounded-md bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20">
                                    {{ __('Total Late Hours') }}: {{ number_format((float) ($summary['total_late_hours'] ?? 0), 2) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 px-5 py-4">
                        @if ((int) ($summary['total_absence_days'] ?? 0) > 0)
                            <section class="space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ __('Non Attendance') }}
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20">
                                            {{ __('Total Absence Days') }}: {{ (int) ($summary['total_absence_days'] ?? 0) }}
                                        </span>
                                        <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/10 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700">
                                            {{ __('Unexcused Absence Days') }}: {{ (int) ($summary['unexcused_absence_days'] ?? 0) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    @foreach ($absenceDates as $absence)
                                        <span class="inline-flex items-center gap-2 rounded-md border border-red-100 bg-red-50 px-2.5 py-1.5 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                                            <span>{{ $absence['date'] ?? '-' }}</span>
                                            <span class="text-xs text-red-600 dark:text-red-300">{{ $absence['label'] ?? '-' }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if ((int) ($summary['late_days'] ?? 0) > 0)
                            <section class="space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ __('Late Attendance') }}
                                    </h4>
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20">
                                        {{ __('Late Days') }}: {{ (int) ($summary['late_days'] ?? 0) }}
                                    </span>
                                </div>

                                <div class="space-y-2">
                                    @foreach ($lateAttendances as $lateAttendance)
                                        <div class="rounded-md border border-amber-100 bg-amber-50 px-3 py-2 text-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="font-medium text-amber-900 dark:text-amber-100">
                                                    {{ $lateAttendance['date'] ?? '-' }}
                                                </div>
                                                <div class="inline-flex w-fit items-center rounded-md bg-white px-2 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/20">
                                                    {{ __('Duration') }}: {{ $lateAttendance['late_duration'] ?? '-' }}
                                                </div>
                                            </div>

                                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-amber-800 dark:text-amber-200">
                                                <span>{{ __('Expected Check In') }}: {{ $lateAttendance['expected_check_in'] ?? '-' }}</span>
                                                <span>{{ __('Check In Time') }}: {{ $lateAttendance['check_in'] ?? '-' }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div>
            {{ $records->links() }}
        </div>
    @else
        <div class="rounded-lg border border-gray-200 bg-white px-5 py-8 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            {{ __('No non compliance records found') }}
        </div>
    @endif
</div>
