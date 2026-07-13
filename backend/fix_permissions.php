<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Artisan;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Fixing mark_attendance permission...\n";

try {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $permissionName = 'mark_attendance';
    $guardName = 'web';

    $permission = Permission::findOrCreate($permissionName, $guardName);

    echo "Permission '{$permissionName}' ensured.\n";

    $roles = ['super_admin', 'Chairman', 'Branch Manager', 'Clerk'];

    foreach ($roles as $roleName) {
        $role = Role::where('name', $roleName)->where('guard_name', $guardName)->first();
        if ($role) {
            $role->givePermissionTo($permission);
            echo "Assigned to role: {$roleName}\n";
        } else {
            echo "Role not found: {$roleName}\n";
        }
    }

    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    echo "Permission cache cleared.\n";

    // Also try to run the artisan command to clear cache
    Artisan::call('permission:cache-reset');
    echo "Artisan permission:cache-reset executed.\n";

    echo "DONE.\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
