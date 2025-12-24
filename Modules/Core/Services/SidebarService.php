<?php

namespace Modules\Core\Services;

use Modules\Core\Interfaces\SidebarItemsInterface;

class SidebarService
{
    protected array $items = [];

    public function add(SidebarItemsInterface $item): static
    {
        $this->items[] = $item;
        return $this;
    }

    public function all(): array
    {
        return collect($this->items)
            ->filter(fn($item) => $item->canSee())
            ->map(fn($item) => $item->toArray())
            ->sortBy(fn($item) => $item['sort'] ?? 100)
            ->values()
            ->toArray();
    }

    public function clear(): static
    {
        $this->items = [];
        return $this;
    }
}
