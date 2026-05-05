@php
    $state = $getState() ?? [];

    $totalUsers = max((int) ($state['total_users'] ?? 0), 0);
    $submittedUsers = max((int) ($state['submitted_users'] ?? 0), 0);
    $pendingUsers = max((int) ($state['pending_users'] ?? 0), 0);
    $submittedPercentage = min(max((int) ($state['submitted_percentage'] ?? 0), 0), 100);
    $pendingPercentage = $totalUsers > 0 ? max(100 - $submittedPercentage, 0) : 0;
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="flex h-full min-h-64 flex-col items-center justify-center gap-4 rounded-lg bg-gray-50 p-4 dark:bg-white/5">
        <div
            class="relative h-44 w-44"
            role="img"
            aria-label="{{ __('Submitted Count') }}: {{ $submittedUsers }}. {{ __('Pending Submissions Count') }}: {{ $pendingUsers }}."
        >
            <svg class="h-full w-full -rotate-90" viewBox="0 0 42 42" aria-hidden="true">
                <circle
                    cx="21"
                    cy="21"
                    r="15.91549431"
                    fill="transparent"
                    stroke-width="4"
                    class="stroke-gray-200 dark:stroke-gray-700"
                />

                @if ($totalUsers > 0 && $pendingPercentage > 0)
                    <circle
                        cx="21"
                        cy="21"
                        r="15.91549431"
                        fill="transparent"
                        stroke="#f59e0b"
                        stroke-width="4"
                        stroke-dasharray="{{ $pendingPercentage }} {{ 100 - $pendingPercentage }}"
                        stroke-dashoffset="-{{ $submittedPercentage }}"
                    />
                @endif

                @if ($totalUsers > 0 && $submittedPercentage > 0)
                    <circle
                        cx="21"
                        cy="21"
                        r="15.91549431"
                        fill="transparent"
                        stroke="#16a34a"
                        stroke-width="4"
                        stroke-dasharray="{{ $submittedPercentage }} {{ 100 - $submittedPercentage }}"
                    />
                @endif
            </svg>

            <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                <span class="text-3xl font-bold text-gray-950 dark:text-white">
                    {{ $submittedPercentage }}%
                </span>
                <span class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('Submitted Count') }}
                </span>
            </div>
        </div>

        <div class="grid w-full gap-3 text-sm">
            <div class="flex items-center justify-between gap-3">
                <span class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <span class="h-2.5 w-2.5 rounded-full bg-green-600"></span>
                    {{ __('Submitted Count') }}
                </span>
                <span class="font-semibold text-gray-950 dark:text-white">
                    {{ number_format($submittedUsers) }}
                </span>
            </div>

            <div class="flex items-center justify-between gap-3">
                <span class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                    {{ __('Pending Submissions Count') }}
                </span>
                <span class="font-semibold text-gray-950 dark:text-white">
                    {{ number_format($pendingUsers) }}
                </span>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-gray-200 pt-3 dark:border-white/10">
                <span class="text-gray-600 dark:text-gray-300">
                    {{ __('Total Required Submissions') }}
                </span>
                <span class="font-semibold text-gray-950 dark:text-white">
                    {{ number_format($totalUsers) }}
                </span>
            </div>
        </div>
    </div>
</x-dynamic-component>
