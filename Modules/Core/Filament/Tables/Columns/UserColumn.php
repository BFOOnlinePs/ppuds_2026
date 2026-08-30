<?php

namespace Modules\Core\Filament\Tables\Columns;

use Closure;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Modules\Core\Entities\User;

/**
 * Renders a person in a table cell using the shared avatar + name + subtitle
 * markup from User::userDisplayHtml(), and links the cell to that person's
 * details page.
 *
 * Every table that shows a student or a staff member should use this instead
 * of a plain TextColumn on `*.name`, so the person cell looks and behaves the
 * same everywhere.
 */
class UserColumn extends TextColumn
{
    protected ?Closure $resolveUserUsing = null;

    protected ?Closure $resolveSubtitleUsing = null;

    protected string $placeholderText = '---';

    /**
     * Which details page the cell links to: 'student', 'supervisor' or null
     * for a cell that should not be a link at all.
     */
    protected ?string $linkTarget = 'student';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->html()
            ->getStateUsing(fn (Model $record): HtmlString|string => $this->displayFor($record))
            ->url(fn (Model $record): ?string => $this->detailsUrlFor($record))
            ->extraAttributes(['class' => 'ppuds-user-cell']);
    }

    /**
     * How to get the User out of the table record — e.g.
     * ->user(fn ($record) => $record->studentCompany?->student).
     */
    public function user(Closure $callback): static
    {
        $this->resolveUserUsing = $callback;

        return $this;
    }

    /**
     * Overrides the second line, which defaults to the user's email.
     */
    public function subtitle(Closure $callback): static
    {
        $this->resolveSubtitleUsing = $callback;

        return $this;
    }

    public function linksToStudent(): static
    {
        $this->linkTarget = 'student';

        return $this;
    }

    public function linksToSupervisor(): static
    {
        $this->linkTarget = 'supervisor';

        return $this;
    }

    public function withoutLink(): static
    {
        $this->linkTarget = null;

        return $this;
    }

    public function placeholderText(string $placeholder): static
    {
        $this->placeholderText = $placeholder;

        return $this;
    }

    public function resolveUser(Model $record): ?User
    {
        $user = $this->resolveUserUsing
            ? $this->evaluate($this->resolveUserUsing, ['record' => $record])
            : $record->getAttribute($this->getName());

        return $user instanceof User ? $user : null;
    }

    protected function displayFor(Model $record): HtmlString|string
    {
        $user = $this->resolveUser($record);

        if (! $user) {
            return $this->placeholderText;
        }

        $subtitle = $this->resolveSubtitleUsing
            ? $this->evaluate($this->resolveSubtitleUsing, ['record' => $record, 'user' => $user])
            : null;

        return $user->userDisplayHtml($subtitle);
    }

    protected function detailsUrlFor(Model $record): ?string
    {
        if ($this->linkTarget === null) {
            return null;
        }

        $user = $this->resolveUser($record);

        if (! $user) {
            return null;
        }

        [$route, $permission] = $this->linkTarget === 'supervisor'
            ? ['supervisors.details', 'Supervisor Details List']
            : ['students.details', 'Student Details List'];

        if (! auth()->user()?->can($permission)) {
            return null;
        }

        return route($route, $user->getKey());
    }
}
