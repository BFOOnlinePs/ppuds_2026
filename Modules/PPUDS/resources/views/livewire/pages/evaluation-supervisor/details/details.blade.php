@php
    $supervisor = $this->supervisor;
    $summary = $this->summary;

    // Tints are inline rather than Tailwind colour utilities, same as the
    // supervisor report page, so every tile renders with its intended colour.
    $cards = [
        [
            'label' => __('Assigned Students'),
            'value' => $summary['students'],
            'hint' => __('Companies') . ': ' . $summary['companies'],
            'icon' => 'solar-users-group-rounded-bold-duotone',
            'color' => '#6366f1',
        ],
        [
            'label' => __('Graded'),
            'value' => $summary['graded'],
            'hint' => __('Trainings') . ': ' . $summary['trainings'],
            'icon' => 'solar-check-circle-bold-duotone',
            'color' => '#10b981',
        ],
        [
            'label' => __('Pending Grading'),
            'value' => $summary['pending'],
            'hint' => __('Completion') . ': ' . $summary['completion'] . '%',
            'icon' => 'solar-hourglass-bold-duotone',
            'color' => '#f59e0b',
        ],
        [
            'label' => __('Average Grade'),
            'value' => $summary['average'] ?? '—',
            'hint' => __('Out of :max', ['max' => $summary['max_grade']]),
            'icon' => 'solar-medal-star-bold-duotone',
            'color' => '#0ea5e9',
        ],
    ];

    $progressColor = $summary['trainings'] > 0 && $summary['pending'] === 0 ? '#10b981' : '#f59e0b';

    $tabs = [
        'students' => ['label' => __('Supervised Students'), 'icon' => 'solar-square-academic-cap-bold-duotone'],
    ];

    if ($this->canViewActivity()) {
        $tabs['activity'] = ['label' => __('Activity Log'), 'icon' => 'solar-history-3-bold-duotone'];
    }
@endphp

<div class="space-y-6">

    {{-- Who the record is about --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <img src="{{ $supervisor->getAvatarUrlAttribute() }}"
                     alt="{{ $supervisor->name }}"
                     class="h-16 w-16 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-800">

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $supervisor->name }}
                        </h1>

                        <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                              style="color: #6366f1; background-color: #6366f11f;">
                            {{ __('Evaluation Supervisor') }}
                        </span>

                        @if ($supervisor->trashed())
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                  style="color: #ef4444; background-color: #ef44441f;">
                                {{ __('Deleted') }}
                            </span>
                        @endif
                    </div>

                    @if (filled($supervisor->name_en))
                        <div class="truncate text-sm text-gray-500 dark:text-gray-400">
                            {{ $supervisor->name_en }}
                        </div>
                    @endif

                    <div class="truncate text-sm text-gray-500 dark:text-gray-400">
                        {{ $supervisor->email ?: '—' }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:text-end">
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Phone') }}</div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100" dir="ltr">{{ $supervisor->phone ?: '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('Created At') }}</div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $supervisor->created_at?->format('Y-m-d') ?? '—' }}</div>
                </div>
            </div>
        </div>

        {{-- Grading progress across every assigned training --}}
        <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('Grading Progress') }}</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ $summary['graded'] }} / {{ $summary['trainings'] }}
                    <span class="text-gray-400 dark:text-gray-500">({{ $summary['completion'] }}%)</span>
                </span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div class="h-full rounded-full transition-all"
                     style="width: {{ $summary['completion'] }}%; background-color: {{ $progressColor }};"></div>
            </div>
        </div>
    </div>

    {{-- Headline numbers --}}
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
            @if ($tab === 'activity' && $this->canViewActivity())
                @livewire(\Modules\PPUDS\Livewire\Pages\SupervisorReport\Details\Activity\Index::class,
                    ['supervisorId' => $supervisorId],
                    key('evaluation-supervisor-activity-' . $supervisorId))
            @else
                @livewire(\Modules\PPUDS\Livewire\Pages\EvaluationSupervisor\Details\StudentCompany\Index::class,
                    ['evaluationSupervisorId' => $supervisorId],
                    key('evaluation-supervisor-students-' . $supervisorId))
            @endif
        </div>
    </div>
</div>
