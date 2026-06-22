<?php

namespace Modules\Core\Livewire\Pages\Home\Widget;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Settings\GeneralSettings;

class DashboardVerificationWidget extends Widget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'core::livewire.pages.home.widget.dashboard-verification-widget';

    private const VIEW_PERMISSION = 'Dashboard Statistics Verification View';

    private const PREVIEW_LIMIT = 8;

    public function sections(): array
    {
        if (! $this->canViewVerification()) {
            return [];
        }

        return [
            $this->enrolledStudentsWithoutCompanySection(),
        ];
    }

    private function enrolledStudentsWithoutCompanySection(): array
    {
        $query = $this->enrolledStudentsWithoutCompanyQuery();

        return [
            'title' => __('Enrolled Students Without Company'),
            'count' => (clone $query)->count(),
            'icon' => 'heroicon-o-clipboard-document-list',
            'color' => 'warning',
            'url' => $this->routeUrl('student-companies.add', [], 'StudentCompany Create')
                ?? $this->routeUrl('registrations.index', ['without_company' => 1], 'Registration View List'),
            'rows' => (clone $query)
                ->latest('id')
                ->limit(self::PREVIEW_LIMIT)
                ->get()
                ->map(fn(Registration $registration): array => [
                    'title' => $registration->student?->name ?: __('Unknown Student'),
                    'subtitle' => $registration->student?->studentProfile?->student_number ?: $registration->student?->email,
                    'meta' => $this->registrationMeta($registration),
                    'url' => $this->registrationUrl($registration),
                ])
                ->all(),
            'empty' => __('No enrolled students are missing companies'),
        ];
    }

    private function enrolledStudentsWithoutCompanyQuery(): Builder
    {
        return $this->currentRegistrationsQuery()
            ->with(['student.studentProfile.major', 'course', 'supervisor'])
            ->whereNotIn(
                'id',
                StudentCompany::query()
                    ->whereNotNull('company_id')
                    ->select('registration_id')
            );
    }

    private function currentRegistrationsQuery(): Builder
    {
        return Registration::query()
            ->where(fn(Builder $query) => $this->applyCurrentSemester($query));
    }

    private function applyCurrentSemester(Builder $query): void
    {
        $settings = app(GeneralSettings::class);

        $query
            ->where('semester', $settings->semester_type->value)
            ->where('year', $settings->year);

        if ($this->shouldScopeToSupervisor()) {
            $query->where('supervisor_id', auth()->id());
        }
    }

    private function shouldScopeToSupervisor(): bool
    {
        return auth()->user()?->hasAnyRole($this->supervisorScopedRoles())
            && ! auth()->user()?->hasAnyRole([
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
            ]);
    }

    private function supervisorScopedRoles(): array
    {
        return [
            UserRole::PRACTICAL_TRAINING_SUPERVISOR->value,
            'Academic Supervisor',
            'University Supervisor',
        ];
    }

    private function canViewVerification(): bool
    {
        $user = auth()->user();

        return $user && $user->can(self::VIEW_PERMISSION);
    }

    private function registrationMeta(Registration $registration): string
    {
        return collect([
            $registration->course?->name,
            $registration->semester?->getLabel(),
            $registration->year,
        ])->filter()->implode(' - ');
    }

    private function studentUrl(?int $studentId): ?string
    {
        if (! $studentId) {
            return null;
        }

        return $this->routeUrl('students.details', ['user' => $studentId], 'Student Details List')
            ?? $this->routeUrl('students.index', [], 'Student View List');
    }

    private function registrationUrl(Registration $registration): ?string
    {
        return $this->routeUrl('student-companies.add', ['registration_id' => $registration->id], 'StudentCompany Create')
            ?? $this->routeUrl('registrations.edit', ['registration' => $registration], 'Registration Update')
            ?? $this->studentUrl($registration->student_id)
            ?? $this->routeUrl('registrations.index', ['without_company' => 1], 'Registration View List');
    }

    private function routeUrl(string $route, array $parameters = [], ?string $permission = null): ?string
    {
        $user = auth()->user();

        if (! $user || ! Route::has($route)) {
            return null;
        }

        if ($permission && ! $user->can($permission)) {
            return null;
        }

        return route($route, $parameters);
    }
}
