<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccountingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Define all permission slugs used across accounting modules
        $permissions = [
            // Journals & attachments
            'journals.view', 'journals.create', 'journals.submit', 'journals.approve', 'journals.attach',

            // Bank & reconciliation
            'bank_accounts.view', 'bank_accounts.manage',
            'bank_statements.create', 'bank_statements.import', 'bank_statements.automatch',
            'bank_reconciliation.start', 'bank_reconciliation.finalize',

            // Fixed assets
            'fixed_assets.manage_categories', 'fixed_assets.manage_assets', 'fixed_assets.post_depreciation',

            // Fiscal periods
            'periods.view', 'periods.manage', 'periods.close',

            // Recurring journals
            'recurring_journals.view', 'recurring_journals.manage', 'recurring_journals.run',

            // Reports & exports
            'accounting.view_reports', 'accounting.export_reports', 'accounting.view_gl', 'accounting.view_branch_tb',
            'aging.view',

            // FX & Ops
            'fx.realized_posting',
            'ops.year_end_close', 'ops.auto_reverse', 'ops.rebuild_monthly', 'ops.fx_revalue', 'ops.financial_reconcile',

            // Tax
            'tax.manage_rates', 'tax.map_products', 'tax.run_settlement',

            // Inventory
            'inventory.receipts', 'inventory.adjustments', 'inventory.view_stock',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name);
        }

        // Optionally attach to existing roles, if they exist
        $roleMap = [
            'super_admin' => $permissions,
        ];

        foreach ($roleMap as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }
    }
}
