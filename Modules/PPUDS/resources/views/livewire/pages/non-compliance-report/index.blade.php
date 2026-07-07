@php
    $records = $this->records();
@endphp

<div class="space-y-6">
    {{ $this->form }}

    @if ($records->count() > 0)
        <div class="grid grid-cols-1 gap-4 2xl:grid-cols-2">
            @foreach ($records as $studentCompany)
                @php
                    $summary = $this->summary($studentCompany);
                    $absenceDates = $summary['absence_dates'] ?? [];
                    $lateAttendances = $summary['late_attendances'] ?? [];
                @endphp

                <article class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <div class="space-y-4">
                            <div>
                                <h3 class="whitespace-normal break-words text-lg font-semibold leading-7 text-gray-950 dark:text-white">
                                    {{ $studentCompany->student?->name ?? __('Unknown Student') }}
                                </h3>

                                <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-gray-600 dark:text-gray-300 sm:grid-cols-3">
                                    <div class="min-w-0">
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ __('Student Number') }}</div>
                                        <div class="break-words font-medium text-gray-700 dark:text-gray-200">
                                            {{ $studentCompany->student?->studentProfile?->student_number ?? '-' }}
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ __('Company') }}</div>
                                        <div class="break-words font-medium text-gray-700 dark:text-gray-200">
                                            {{ $studentCompany->company?->name ?? '-' }}
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs text-gray-400 dark:text-gray-500">{{ __('Branch') }}</div>
                                        <div class="break-words font-medium text-gray-700 dark:text-gray-200">
                                            {{ $studentCompany->branch?->name ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <span class="inline-flex items-center justify-center rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20">
                                    {{ __('Total Non Compliance Days') }}: {{ (int) ($summary['total_non_compliance_days'] ?? 0) }}
                                </span>
                                <span class="inline-flex items-center justify-center rounded-md bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20">
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

                                <div class="grid max-h-72 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($absenceDates as $absence)
                                        <span class="flex min-w-0 items-center justify-between gap-3 rounded-md border border-red-100 bg-red-50 px-2.5 py-1.5 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                                            <span class="font-medium">{{ $absence['date'] ?? '-' }}</span>
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

                                <div class="max-h-80 space-y-2 overflow-y-auto">
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

        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <x-filament::pagination
                :paginator="$records"
                extreme-links
            />
        </div>
    @else
        <div class="rounded-lg border border-gray-200 bg-white px-5 py-8 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            {{ __('No non compliance records found') }}
        </div>
    @endif
</div>
