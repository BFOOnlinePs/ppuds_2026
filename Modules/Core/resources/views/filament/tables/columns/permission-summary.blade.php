@php
    $summary = $getState() ?? [];
    $groups = $summary['groups'] ?? [];
    $total = $summary['total'] ?? 0;
    $groupCount = $summary['group_count'] ?? count($groups);
    $moreGroups = $summary['more_groups'] ?? 0;
@endphp

<div class="flex max-w-5xl flex-col gap-2">
    @if ($total === 0)
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('No permissions assigned') }}
        </span>
    @else
        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $total }}</span>
            <span>{{ __('Permissions') }}</span>
            <span class="text-gray-300 dark:text-gray-600">/</span>
            <span>{{ $groupCount }} {{ __('Groups') }}</span>
        </div>

        <div class="flex flex-wrap gap-1.5">
            @foreach ($groups as $group)
                <span
                    class="inline-flex items-center gap-1.5 rounded-md border border-orange-100 bg-orange-50 px-2 py-1 text-xs font-medium text-orange-700 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-300"
                >
                    <span>{{ $group['label'] }}</span>
                    <span class="rounded bg-white/80 px-1.5 py-0.5 text-[11px] leading-none text-orange-600 dark:bg-white/10 dark:text-orange-200">
                        {{ $group['count'] }}
                    </span>
                </span>
            @endforeach

            @if ($moreGroups > 0)
                <span
                    class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
                >
                    +{{ $moreGroups }} {{ __('more groups') }}
                </span>
            @endif
        </div>
    @endif
</div>
