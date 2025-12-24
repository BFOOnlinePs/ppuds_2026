<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// use Modules\Core\Database\Factories\UserModuleFactory;

class UserModule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'module_name',
        'package_source',
        'is_active',
        'module_settings',
        'permissions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'module_settings' => 'array',
        'permissions' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
        'module_settings' => '{}',
        'permissions' => '[]'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function systemModule(): BelongsTo
    {
        return $this->belongsTo(SystemModule::class, 'module_name', 'name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForModule(Builder $query, string $moduleName): Builder
    {
        return $query->where('module_name', $moduleName);
    }

    public function scopeFromPackage(Builder $query, string $packageSource): Builder
    {
        return $query->where('package_source', $packageSource);
    }

        public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    public function addPermission(string $permission): self
    {
        $permissions = $this->permissions ?? [];

        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
        }

        return $this;
    }

    public function removePermission(string $permission): self
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_values(array_filter($permissions, fn($p) => $p !== $permission));

        $this->update(['permissions' => $permissions]);
        return $this;
    }

    public function getDisplayName(): string
    {
        // جرب جلب الاسم من SystemModule أولاً
        if ($this->systemModule) {
            return $this->systemModule->display_name;
        }

        // إذا لم يوجد، استخدم mapping ثابت
        return $this->getModuleDisplayNameMapping()[$this->module_name] ?? ucfirst($this->module_name);
    }

    public function getIcon(): string
    {
        if ($this->systemModule && $this->systemModule->icon) {
            return $this->systemModule->icon;
        }

        return $this->getModuleIconMapping()[$this->module_name] ?? 'square';
    }

    public function getDescription(): string
    {
        if ($this->systemModule && $this->systemModule->description) {
            return $this->systemModule->description;
        }

        return $this->getModuleDescriptionMapping()[$this->module_name] ?? '';
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function activate(): self
    {
        $this->update(['is_active' => true]);
        return $this;
    }

    // protected static function newFactory(): UserModuleFactory
    // {
    //     // return UserModuleFactory::new();
    // }
}
