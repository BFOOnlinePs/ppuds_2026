<div class="space-y-6">
    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\DashboardStatsWidget::class)
    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\DashboardVerificationWidget::class)

    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\HomeActionsWidget::class)
    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\AttendanceMapWidget::class)

    {{ $this->form }}
    
    @can('StudentCompany Create')
        @livewire(\Modules\Core\Livewire\Pages\Home\StudentCompanyAssistant::class)
    @endcan

    @if (count($chartWidgets = $this->chartWidgets()))
        <x-filament-widgets::widgets
            :widgets="$chartWidgets"
            :columns="['default' => 1, 'xl' => 2]"
        />
    @endif
</div>
