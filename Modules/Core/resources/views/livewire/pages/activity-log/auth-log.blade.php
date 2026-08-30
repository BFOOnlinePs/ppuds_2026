@php
    $totals = $this->totals;

    // Tints are inline rather than Tailwind colour utilities: the compiled
    // stylesheet only ships a handful of colour families, so a class like
    // bg-rose-50 would silently render as no background at all.
    $cards = [
        [
            'label' => __('Login'),
            'value' => $totals['logins'],
            'hint' => __('Today') . ': ' . $totals['logins_today'],
            'icon' => 'solar-login-3-bold-duotone',
            'color' => '#10b981',
        ],
        [
            'label' => __('Logout'),
            'value' => $totals['logouts'],
            'hint' => __('Total'),
            'icon' => 'solar-logout-3-bold-duotone',
            'color' => '#f59e0b',
        ],
        [
            'label' => __('Failed Login'),
            'value' => $totals['failed'],
            'hint' => __('Today') . ': ' . $totals['failed_today'],
            'icon' => 'solar-shield-warning-bold-duotone',
            'color' => '#f43f5e',
        ],
        [
            'label' => __('Lockout'),
            'value' => $totals['lockouts'],
            'hint' => __('Total'),
            'icon' => 'solar-lock-keyhole-bold-duotone',
            'color' => '#8b5cf6',
        ],
    ];
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            <div class="rounded-xl border border-gray-200 bg-white p-4 transition hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $card['label'] }}
                        </div>
                        <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ number_format($card['value']) }}
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

    {{ $this->table }}
</div>
