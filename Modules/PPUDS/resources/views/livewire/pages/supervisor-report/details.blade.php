@php
    $supervisor = $this->supervisor;
    $summary = $this->summary;

    // Tints are inline rather than Tailwind colour utilities: the compiled
    // stylesheet only ships a handful of colour families, so a class like
    // bg-rose-50 would silently render as no background at all.
    $cards = [
        [
            'label' => __('Supervised Students Count'),
            'value' => $summary['students'],
            'hint' => __('Trainings') . ': ' . $summary['trainings'],
            'icon' => 'solar-users-group-rounded-bold-duotone',
            'color' => '#6366f1',
        ],
        [
            'label' => __('Companies'),
            'value' => $summary['companies'],
            'hint' => __('Visited Students') . ': ' . $summary['visited_students'],
            'icon' => 'solar-buildings-bold-duotone',
            'color' => '#0ea5e9',
        ],
        [
            'label' => __('Field Visits Count'),
            'value' => $summary['field_visits'],
            'hint' => __('Duration (Mins)') . ': ' . $summary['field_visit_minutes'],
            'icon' => 'solar-map-point-bold-duotone',
            'color' => '#f59e0b',
        ],
        [
            'label' => __('Activities Count'),
            'value' => $summary['activities'],
            'hint' => __('Last Activity') . ': ' . ($summary['last_activity_at']
                ? \Illuminate\Support\Carbon::parse($summary['last_activity_at'])->format('Y-m-d')
                : '—'),
            'icon' => 'solar-history-3-bold-duotone',
            'color' => '#8b5cf6',
        ],
    ];

    $lastFieldVisit = $summary['last_field_visit_at']
        ? \Illuminate\Support\Carbon::parse($summary['last_field_visit_at'])->format('Y-m-d')
        : '—';

    $tabs = [
        'students' => ['label' => __('Supervised Students'), 'icon' => 'solar-square-academic-cap-bold-duotone'],
        'field-visits' => ['label' => __('Field Visits By Supervisor'), 'icon' => 'solar-map-point-wave-bold-duotone'],
        'activity' => ['label' => __('Activity Log'), 'icon' => 'solar-history-3-bold-duotone'],
    ];
@endphp

<div class="space-y-6">

    {{-- Who the report is about --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <img src="{{ $supervisor->getAvatarUrlAttribute() }}"
                     alt="{{ $supervisor->name }}"
                     class="h-16 w-16 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-800">

                <div class="min-w-0">
                    <h1 class="truncate text-xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $supervisor->name }}
                    </h1>
                    <div class="truncate text-sm text-gray-500 dark:text-gray-400">
                        {{ $supervisor->email ?: '—' }}
                    </div>
                    <div class="mt-1 truncate text-xs text-gray-400 dark:text-gray-500">
                        {{ $supervisor->roles->pluck('name')->map(fn ($role) => __($role))->implode('، ') ?: '—' }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:text-end">
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Phone') }}</div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $supervisor->phone ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Last Field Visit') }}</div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $lastFieldVisit }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Headline numbers: the summary half of the report --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
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

    {{-- The detailed half: one table per record type --}}
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
            @if ($tab === 'students')
                @livewire(\Modules\PPUDS\Livewire\Pages\Supervisor\Details\StudentCompany\Index::class,
                    ['supervisorId' => $supervisorId],
                    key('supervisor-report-students-' . $supervisorId))
            @elseif ($tab === 'field-visits')
                @livewire(\Modules\PPUDS\Livewire\Pages\Supervisor\Details\FieldVisit\Index::class,
                    ['supervisorId' => $supervisorId],
                    key('supervisor-report-visits-' . $supervisorId))
            @else
                @livewire(\Modules\PPUDS\Livewire\Pages\SupervisorReport\Details\Activity\Index::class,
                    ['supervisorId' => $supervisorId],
                    key('supervisor-report-activity-' . $supervisorId))
            @endif
        </div>
    </div>
</div>
