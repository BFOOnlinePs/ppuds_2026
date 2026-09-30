@php($groups = $this->groups())

<x-filament-widgets::widget>
    @if ($groups->isNotEmpty())
        <section class="overflow-hidden rounded-lg border border-danger-300 bg-danger-50 shadow-sm dark:border-danger-500/30 dark:bg-danger-500/10">
            <div class="flex items-start gap-3 border-b border-danger-200 px-4 py-3 dark:border-danger-500/20">
                @svg('heroicon-o-exclamation-triangle', 'h-6 w-6 shrink-0 text-danger-600 dark:text-danger-400')

                <div class="min-w-0">
                    <h2 class="flex flex-wrap items-center gap-2 text-base font-semibold text-danger-800 dark:text-danger-300">
                        {{ __('Students Without A Company Supervisor') }}
                        <span class="rounded-full bg-danger-600 px-2 py-0.5 text-xs font-bold text-white">
                            {{ number_format($groups->sum(fn (array $group): int => $group['students']->count())) }}
                        </span>
                    </h2>
                    <p class="mt-1 text-sm text-danger-700 dark:text-danger-400">
                        {{ __('These placements are not visible to any company supervisor. Assign a supervisor to restore access.') }}
                    </p>
                </div>
            </div>

            <div class="divide-y divide-danger-200 dark:divide-danger-500/20">
                @foreach ($groups as $group)
                    <div
                        wire:key="unsupervised-{{ $group['key'] }}"
                        class="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $group['company'] ?: '—' }} › {{ $group['branch'] ?: '—' }} › {{ $group['department'] ?: '—' }}
                            </p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                {{ $group['students']->implode(', ') }}
                            </p>
                        </div>

                        @if ($group['supervisors']->isNotEmpty())
                            <div class="flex shrink-0 items-center gap-2">
                                <x-filament::input.wrapper class="min-w-[12rem]">
                                    <x-filament::input.select wire:model="selectedSupervisors.{{ $group['key'] }}">
                                        <option value="">{{ __('Select Supervisor') }}</option>
                                        @foreach ($group['supervisors'] as $supervisorId => $supervisorName)
                                            <option value="{{ $supervisorId }}">{{ $supervisorName }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>

                                <x-filament::button
                                    color="danger"
                                    wire:click="assign('{{ $group['key'] }}')"
                                    wire:target="assign('{{ $group['key'] }}')"
                                >
                                    {{ __('Assign') }}
                                </x-filament::button>
                            </div>
                        @else
                            {{-- الشركة بلا أي مشرف: الإضافة من شاشة مشرفي الشركات تطلب القسم مباشرة --}}
                            <a
                                href="{{ route('company-supervisors.index') }}"
                                class="inline-flex shrink-0 items-center gap-1 rounded-md bg-white px-3 py-2 text-sm font-semibold text-danger-700 ring-1 ring-danger-300 transition hover:bg-danger-100 dark:bg-gray-900 dark:text-danger-300"
                            >
                                @svg('heroicon-o-user-plus', 'h-4 w-4')
                                {{ __('Add Company Supervisor') }}
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @else
        {{-- سطر صغير دائم الظهور: يؤكد أن الفحص يعمل وأن لا طالب ضائع --}}
        <div class="flex items-center gap-2 rounded-lg border border-success/30 bg-success-light px-4 py-3 text-sm font-medium text-success dark:bg-success-dark-light">
            @svg('heroicon-o-check-circle', 'h-5 w-5 shrink-0')
            {{ __('All current students have a company supervisor') }}
        </div>
    @endif
</x-filament-widgets::widget>
