<?php

namespace Modules\Core\Livewire\Pages\Home\Widget;

use Filament\Widgets\Widget;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

class HomeActionsWidget extends Widget
{
    protected int | string | array $columnSpan = 'full';

    protected static string $view = 'core::livewire.pages.home.widget.home-actions-widget';

    public function links(): array
    {
        return collect($this->items())
            ->filter(fn (array $item) => $this->canSee($item))
            ->map(fn (array $item) => $this->formatItem($item))
            ->values()
            ->all();
    }

    private function items(): array
    {
        return [
            [
                'label' => 'Students',
                'route' => 'students.index',
                'permission' => 'Student View List',
                'icon' => 'heroicon-o-academic-cap',
            ],
            [
                'label' => 'Companies',
                'route' => 'companies.index',
                'permission' => 'Company View List',
                'icon' => 'heroicon-o-building-office-2',
            ],
            [
                'label' => 'Attendance And Departure Log',
                'route' => 'student-attendances.index',
                'permission' => 'StudentAttendance View List',
                'icon' => 'heroicon-o-clipboard-document-check',
            ],
            [
                'label' => 'Student Company Registration',
                'route' => 'student-companies.index',
                'permission' => 'StudentCompany View List',
                'icon' => 'heroicon-o-user-plus',
            ],
            [
                'label' => 'Field Visit',
                'route' => 'field-visits.index',
                'permission' => 'FieldVisit View List',
                'icon' => 'heroicon-o-map-pin',
            ],
            [
                'label' => 'Surveys',
                'route' => 'surveys.index',
                'permission' => 'Survey View List',
                'icon' => 'heroicon-o-clipboard-document-list',
            ],
            [
                'label' => 'Announcements',
                'route' => 'announcements.index',
                'permission' => 'Announcement View List',
                'icon' => 'heroicon-o-megaphone',
            ],
            [
                'label' => 'Attendance Map',
                'permission' => 'Attendance Map View',
                'icon' => 'heroicon-o-map',
                'event' => 'open-attendance-map',
            ],
            [
                'label' => 'Training Places',
                'route' => 'branches.index',
                'permission' => 'Branch View List',
                'icon' => 'heroicon-o-map',
            ],
            [
                'label' => 'Attendance And Departure Records',
                'route' => 'student-attendances.index',
                'permission' => 'StudentAttendance View List',
                'icon' => 'heroicon-o-calendar-days',
            ],
            [
                'label' => 'Departure Permission',
                'route' => 'leave-requests.index',
                'permission' => 'LeaveRequest View List',
                'icon' => 'heroicon-o-document-text',
            ],
        ];
    }

    private function canSee(array $item): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $canByPermission = collect(Arr::wrap($item['permission'] ?? []))
            ->contains(fn (string $permission) => $user->can($permission));

        $roles = Arr::wrap($item['roles'] ?? []);
        $canByRole = filled($roles) && $user->hasAnyRole($roles);

        return $canByPermission || $canByRole;
    }

    private function formatItem(array $item): array
    {
        $route = $item['route'] ?? null;
        $event = $item['event'] ?? null;
        $isDisabled = (bool) ($item['disabled'] ?? false);

        if (! $isDisabled && $route) {
            $isDisabled = ! Route::has($route);
        }

        if (! $route && ! $event) {
            $isDisabled = true;
        }

        return [
            ...$item,
            'disabled' => $isDisabled,
            'url' => (! $isDisabled && $route) ? route($route) : null,
        ];
    }
}
