@php
    $groups = $summary['groups'] ?? [];
    $total = $summary['total'] ?? 0;
    $groupCount = $summary['group_count'] ?? count($groups);
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <span class="rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
            {{ $total }} {{ __('Permissions') }}
        </span>
        <span>{{ $groupCount }} {{ __('Groups') }}</span>
    </div>

    @if ($total === 0)
        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            {{ __('No permissions assigned') }}
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($groups as $group)
                <section class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $group['label'] }}
                        </h3>
                        <span class="rounded-md bg-orange-50 px-2 py-1 text-xs font-medium text-orange-700 dark:bg-orange-500/10 dark:text-orange-300">
                            {{ $group['count'] }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($group['actions'] as $action)
                            <span class="rounded-md bg-gray-50 px-2 py-1 text-xs text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                {{ $action }}
                            </span>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</div>
