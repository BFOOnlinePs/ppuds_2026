<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Enums\UserRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $roles = [
        UserRole::COMPANY_SUPERVISOR->value,
        'Company Manager',
        'مدير الشركة',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permission = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'Company View List')
            ->first();

        if (! $permission) {
            return;
        }

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->roles)
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'Company View List')
            ->first();

        if (! $permission) {
            return;
        }

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->roles)
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
