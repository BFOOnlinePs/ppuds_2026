<div class="space-y-6">
    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\DashboardStatsWidget::class)

    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\HomeActionsWidget::class)

    @can('Company Update')
        @livewire(\Modules\Core\Livewire\Pages\Home\Widget\UnsupervisedStudentsWidget::class)
    @endcan

    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\DashboardVerificationWidget::class)

    @livewire(\Modules\Core\Livewire\Pages\Home\Widget\AttendanceMapWidget::class)

    {{ $this->form }}
</div>
