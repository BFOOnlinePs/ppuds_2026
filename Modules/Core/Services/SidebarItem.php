<?php

namespace Modules\Core\Services;

use Closure;
use Modules\Core\Interfaces\SidebarItemsInterface;

class SidebarItem implements SidebarItemsInterface
{
    protected string $title;
    protected string $icon;
    protected ?string $route = null;
    protected ?array $permissions = [];
    protected array $hiddenRoles = [];
    protected ?string $badge = null;
    protected int $sort = 0;
    protected ?Closure $visibleCallback = null;

    public function __construct(
        string $title,
        string $icon,
        array $permissions,
        string $route,
        int $sort = 1000
    ) {
        $this->title = $title;
        $this->icon = $icon;
        $this->permissions = $permissions;
        $this->route = $route;
        $this->sort = $sort;
//        $this->isActive();
    }

    public function permissions(array $permissions) {
        $this->permissions = $permissions;
        return $this;
    }

    public function hiddenForRoles(array $roles): static
    {
        $this->hiddenRoles = $roles;

        return $this;
    }

    /**
     * شرط إضافي يُقيَّم وقت بناء القائمة، لإخفاء عنصر تحكمه إعدادات النظام
     * وليس صلاحيات المستخدم.
     */
    public function visible(Closure $callback): static
    {
        $this->visibleCallback = $callback;

        return $this;
    }

    public function isActive() {
        return request()->routeIs($this->route);
    }

    public function route(string $route) {
        $this->route = route($route);
        return $this;
    }

    public function canSee() {
        if ($this->visibleCallback !== null && ! ($this->visibleCallback)()) {
            return false;
        }

        if (! empty($this->hiddenRoles) && auth()->check() && auth()->user()->hasAnyRole($this->hiddenRoles)) {
            return false;
        }

        if (empty($this->permissions)) return true;
        foreach ($this->permissions as $perm) {
            if (!auth()->check() || !auth()->user()->can($perm)) {
                return false;
            }
        }
        return true;
    }

    public function toArray() {
        return [
            'title' => $this->title,
            'type' => 'item',
            'icon' => $this->icon,
            'route' => $this->route,
            'permissions' => $this->permissions,
            'hidden_roles' => $this->hiddenRoles,
            'badge' => $this->badge,
            'sort' => $this->sort,
        ];
    }
}
