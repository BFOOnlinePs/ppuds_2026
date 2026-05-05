<div>
    @if (count($chartWidgets = $this->chartWidgets()))
        <x-filament-widgets::widgets
            :widgets="$chartWidgets"
            :columns="['default' => 1, 'xl' => 2, '2xl' => 3]"
        />
    @else
        <x-filament::section :heading="__('Answer Results')">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('No multiple choice questions found') }}
            </div>
        </x-filament::section>
    @endif
</div>
