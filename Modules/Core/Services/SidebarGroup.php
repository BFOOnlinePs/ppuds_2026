<?php

namespace Modules\Core\Services;

use Modules\Core\Interfaces\SidebarItemsInterface;

class SidebarGroup implements SidebarItemsInterface
{
    protected string $title;
    protected string $icon;
    protected array $children = [];
    protected array $permissions = [];
    protected array $exceptRoles = [];
    protected int $sort = 1000;


    public function __construct(
        string $title,
        string $icon,
        array $permissions = [],
        int $sort = 1000
    ) {
        $this->title = $title;
        $this->icon = $icon;
        $this->permissions = $permissions;
        $this->sort = $sort;
    }

    public function add(SidebarItemsInterface $item): static
    {
        $this->children[] = $item;
        return $this;
    }


    public function permissions(array $permissions): static
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function exceptRoles(array $roles): static
    {
        $this->exceptRoles = $roles;

        return $this;
    }

    public function canSee(): bool
    {
        if (! empty($this->exceptRoles) && auth()->check() && auth()->user()->hasAnyRole($this->exceptRoles)) {
            return false;
        }

        if (empty($this->permissions)) {
            return empty($this->children) || collect($this->children)->contains(fn($item) => $item->canSee());
        }

        foreach ($this->permissions as $perm) {
            if (!auth()->check() || !auth()->user()->can($perm)) {
                return false;
            }
        }
        return true;
    }

    public function toArray(): array
    {
        return [
            'type' => 'group',
            'title' => $this->title,
            'icon' => $this->icon,
            'permissions' => $this->permissions,
            'sort' => $this->sort,
            'children' => collect($this->children)
                ->filter(fn($item) => $item->canSee())
                ->map(fn($item) => $item->toArray())
//                ->sortBy(fn($item) => $item['sort'] ?? 100)
                ->values()
                ->toArray(),
        ];
    }
}
