<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionName = 'mark_attendance';
        $guardName = 'web';

        // Ensure the permission exists
        $permission = Permission::findOrCreate($permissionName, $guardName);

        // Roles to assign the permission to
        $roles = ['super_admin', 'Chairman', 'Branch Manager', 'Clerk'];

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', $guardName)->first();
            if ($role) {
                $role->givePermissionTo($permission);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionName = 'mark_attendance';
        $guardName = 'web';

        $permission = Permission::where('name', $permissionName)->where('guard_name', $guardName)->first();

        if ($permission) {
            $permission->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
