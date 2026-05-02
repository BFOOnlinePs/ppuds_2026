<?php

namespace Modules\PPUDS\Livewire\Pages\Announcement;

use App\View\Components\AppLayout;
use Livewire\Component;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Announcement;

class Details extends Component
{
    public Announcement $announcement;

    public function mount(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function targetRoleLabels(): array
    {
        return collect($this->announcement->target_roles ?? [])
            ->map(fn (string $role) => UserRole::tryFrom($role)?->getLabel() ?? $role)
            ->filter()
            ->values()
            ->all();
    }

    public function isActive(): bool
    {
        return $this->announcement->published_at?->lte(now())
            && ($this->announcement->expires_at === null || $this->announcement->expires_at->gte(now()));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.announcement.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Announcement Details'), 'url' => route('announcements.details', $this->announcement->id)],
            ]
        ]);
    }
}
