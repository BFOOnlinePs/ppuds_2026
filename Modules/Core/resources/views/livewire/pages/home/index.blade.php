<div class="space-y-6">
    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\DashboardStatsWidget::class)

    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\HomeActionsWidget::class)
    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\AttendanceMapWidget::class)

    @if (count($chartWidgets = $this->chartWidgets()))
        <x-filament-widgets::widgets
            :widgets="$chartWidgets"
            :columns="['default' => 1, 'xl' => 2]"
        />
    @endif

    {{ $this->form }}
</div>
