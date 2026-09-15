<div>
    @if ($this->canViewCharts())
        <div class="mb-4 flex flex-wrap items-center justify-end gap-2">
            {{ $this->exportStatisticsAction }}
        </div>

        @if (count($chartWidgets = $this->chartWidgets()))
            <x-filament-widgets::widgets
                :widgets="$chartWidgets"
                :columns="['default' => 1]"
            />
        @else
            <x-filament::section :heading="__('Answer Results')">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('No multiple choice questions found') }}
                </div>
            </x-filament::section>
        @endif

        <x-filament-actions::modals />
    @endif
</div>
