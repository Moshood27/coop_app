<?php

return [
    // Global feature toggles. Keep defaults conservative to avoid impacting production
    // until database migrations are applied and features are explicitly enabled.
    'features' => [
        'fiscal_periods' => false,
        'multicurrency' => false,
        'ar_ap' => false,
        'bank_reconciliation' => false,
        'fixed_assets' => false,
        'accruals_deferrals' => false,
        'inventory_cogs' => false,
        'tax_reporting' => false,
        'journal_numbering' => false,
    ],

    // Policy/guard toggles that are safe even without schema changes
    'guards' => [
        // Check open posting period only when period tables exist
        'enforce_open_period' => true,
        // Disallow posting to parent/inactive accounts at service layer
        'prevent_parent_posting' => true,
    ],

    // Optional flags to indicate which migration groups have been applied in production.
    // The app will also auto-detect via Schema::hasTable/hasColumn; these give you manual control.
    'migrations_applied' => [
        'fiscal_periods' => false,
        'multicurrency' => false,
        'ar_ap' => false,
        'banking' => false,
        'fixed_assets' => false,
        'accruals' => false,
        'inventory' => false,
        'tax' => false,
    ],

    // Optional account mappings (IDs from ledger_accounts) used by engines when available.
    // Leave as null if you will configure later or post to different accounts.
    'accounts' => [
        'depreciation_expense_account_id' => null,
        'accumulated_depreciation_account_id' => null,
        'bank_charges_account_id' => null,
        'bank_interest_income_account_id' => null,
        'fx_gain_account_id' => null,
        'fx_loss_account_id' => null,
    ],
];
