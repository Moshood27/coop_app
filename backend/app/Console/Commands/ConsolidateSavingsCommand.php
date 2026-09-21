<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Scheme;
use App\Models\Contribution;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConsolidateSavingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:consolidate-savings {--rename=Ordinary Savings} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consolidate "Savings" and "Ordinary Savings" schemes into a single "Ordinary Savings" entity.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting consolidation of Savings schemes...");

        $savings = Scheme::where('name', 'Savings')->first();
        $ordinary = Scheme::where('name', 'Ordinary Savings')->first();

        if (!$savings && !$ordinary) {
            $this->error("Neither 'Savings' nor 'Ordinary Savings' scheme found in the database.");
            return 1;
        }

        if ($savings && $ordinary) {
            $this->info("Found both 'Savings' (ID: {$savings->id}) and 'Ordinary Savings' (ID: {$ordinary->id}).");
            
            $savingsCount = Contribution::where('scheme_id', $savings->id)->count();
            $ordinaryCount = Contribution::where('scheme_id', $ordinary->id)->count();
            
            $this->info("Savings has $savingsCount records.");
            $this->info("Ordinary Savings has $ordinaryCount records.");

            if (!$this->option('force') && !$this->confirm("Do you want to merge 'Ordinary Savings' into 'Savings' and delete the redundant one?", true)) {
                $this->warn("Operation cancelled.");
                return 0;
            }

            DB::transaction(function () use ($savings, $ordinary) {
                // 1. Move contributions from the newer/smaller one to the established one
                $count = Contribution::where('scheme_id', $ordinary->id)->update(['scheme_id' => $savings->id]);
                $this->info("Moved $count contributions from ID {$ordinary->id} to ID {$savings->id}.");

                // 2. Delete the redundant scheme
                $ordinary->forceDelete();
                $this->info("Deleted redundant scheme 'Ordinary Savings' (ID: {$ordinary->id}).");
            });
            
            $finalScheme = $savings;
        } else {
            $finalScheme = $savings ?: $ordinary;
            $this->info("Only one scheme found: '{$finalScheme->name}' (ID: {$finalScheme->id}).");
        }

        // 3. Rename to the preferred name
        $targetName = $this->option('rename');
        if ($finalScheme->name !== $targetName) {
            $this->info("Renaming '{$finalScheme->name}' to '$targetName'...");
            $finalScheme->update(['name' => $targetName]);
        }

        // 4. Trigger reconciliation for affected users
        $this->info("Triggering balance reconciliation...");
        $this->call('financials:reconcile', ['--fix' => true]);

        $this->info("Consolidation complete. 'Ordinary Savings' and 'Savings' are now a single entity.");
        return 0;
    }
}
