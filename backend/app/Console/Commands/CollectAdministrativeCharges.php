<?php

namespace App\Console\Commands;

use App\Services\AdministrativeChargeService;
use Illuminate\Console\Command;

class CollectAdministrativeCharges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin-charges:collect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect monthly administrative charges (accrue new and settle outstanding)';

    /**
     * Execute the console command.
     */
    public function handle(AdministrativeChargeService $service)
    {
        $this->info('--- Starting Administrative Charge Collection ---');

        // 1. Process Monthly Accruals
        $this->comment('Step 1: Accruing monthly charges for this month...');
        $accrualStats = $service->processMonthlyCharges();

        if (isset($accrualStats['status']) && $accrualStats['status'] === 'disabled') {
            $this->warn('Monthly charges are disabled in settings. Skipping accrual.');
        } else {
            $this->line("Users Processed: {$accrualStats['total_users']}");
            $this->line("Charges Accrued: {$accrualStats['accrued']}");
        }

        $this->newLine();

        // 2. Settle Outstanding Balances
        $this->comment('Step 2: Settling outstanding balances from wallet funds...');
        $settleStats = $service->settleAllOutstandingCharges();

        $this->line("Users with balance checked: {$settleStats['total_users_checked']}");
        $this->line("Users settled/partially paid: {$settleStats['settled_users']}");
        $this->line("Total amount collected: ₦" . number_format($settleStats['total_deducted_amount'], 2));

        $this->info('--- Administrative Charge Collection Completed ---');

        return 0;
    }
}
