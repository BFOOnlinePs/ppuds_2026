<?php

namespace Modules\PPUDS\Livewire\Pages\SupervisorReport;

use App\View\Components\AppLayout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\PPUDS\Services\SupervisorReportService;

/**
 * Supervisor report — detailed view for one supervisor: the headline totals,
 * then the records behind them (supervised students, field visits and the
 * full activity log), each in its own tab with its own export and print.
 */
class Details extends Component
{
    public int $supervisorId;

    #[Url(as: 'tab', keep: true)]
    public string $tab = 'students';

    public function mount(User $user): void
    {
        abort_unless(auth()->user()?->can('Supervisor Report View List'), 403);

        $this->supervisorId = $user->id;
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['students', 'field-visits', 'activity'], true)
            ? $tab
            : 'students';
    }

    #[Computed]
    public function supervisor(): User
    {
        return User::with(['roles', 'media'])->findOrFail($this->supervisorId);
    }

    #[Computed]
    public function summary(): array
    {
        return app(SupervisorReportService::class)->summaryFor($this->supervisorId);
    }

    public function render()
    {
        return view('ppuds::livewire.pages.supervisor-report.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Reports'), 'url' => route('reports.index')],
                ['title' => __('Supervisor Report'), 'url' => route('supervisor-reports.index')],
                ['title' => $this->supervisor->name, 'url' => route('supervisor-reports.details', $this->supervisorId)],
            ],
        ]);
    }
}
