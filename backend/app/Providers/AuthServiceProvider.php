<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciation;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatement;
use App\Models\FiscalPeriod;
use App\Models\LedgerAttachment;
use App\Models\LedgerJournal;
use App\Models\RecurringJournal;
use App\Models\TaxRate;
use App\Policies\AssetCategoryPolicy;
use App\Policies\AssetDepreciationPolicy;
use App\Policies\AssetPolicy;
use App\Policies\BankAccountPolicy;
use App\Policies\BankReconciliationPolicy;
use App\Policies\BankStatementPolicy;
use App\Policies\FiscalPeriodPolicy;
use App\Policies\LedgerAttachmentPolicy;
use App\Policies\LedgerJournalPolicy;
use App\Policies\RecurringJournalPolicy;
use App\Policies\TaxRatePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        LedgerJournal::class => LedgerJournalPolicy::class,
        LedgerAttachment::class => LedgerAttachmentPolicy::class,
        BankAccount::class => BankAccountPolicy::class,
        BankStatement::class => BankStatementPolicy::class,
        BankReconciliation::class => BankReconciliationPolicy::class,
        AssetCategory::class => AssetCategoryPolicy::class,
        Asset::class => AssetPolicy::class,
        AssetDepreciation::class => AssetDepreciationPolicy::class,
        FiscalPeriod::class => FiscalPeriodPolicy::class,
        RecurringJournal::class => RecurringJournalPolicy::class,
        TaxRate::class => TaxRatePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Super Admin shortcut
        Gate::before(function ($user, string $ability) {
            // If the user has the super_admin role, allow all abilities
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return true;
            }
            return null;
        });

        // Named gates for non-model abilities (reports & ops)
        Gate::define('accounting.view_reports', fn ($user) => $user->can('accounting.view_reports'));
        Gate::define('accounting.export_reports', fn ($user) => $user->can('accounting.export_reports'));
        Gate::define('accounting.view_gl', fn ($user) => $user->can('accounting.view_gl'));
        Gate::define('accounting.view_branch_tb', fn ($user) => $user->can('accounting.view_branch_tb'));

        Gate::define('reports.aging', fn ($user) => $user->can('aging.view'));

        Gate::define('ops.year_end_close', fn ($user) => $user->can('ops.year_end_close'));
        Gate::define('ops.auto_reverse', fn ($user) => $user->can('ops.auto_reverse'));
        Gate::define('ops.rebuild_monthly', fn ($user) => $user->can('ops.rebuild_monthly'));
        Gate::define('ops.fx_revalue', fn ($user) => $user->can('ops.fx_revalue'));

        Gate::define('fx.realized_posting', fn ($user) => $user->can('fx.realized_posting'));

        // Tax operations
        Gate::define('tax.map_products', fn ($user) => $user->can('tax.map_products'));
        Gate::define('tax.run_settlement', fn ($user) => $user->can('tax.run_settlement'));
    }
}
