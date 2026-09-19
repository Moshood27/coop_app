<?php

namespace App\Console\Commands;

use App\Services\YearEndCloseService;
use Illuminate\Console\Command;

class RunYearEndClose extends Command
{
    protected $signature = 'accounting:year-end-close {period_id : Fiscal period ID} {--dry-run} {--no-close} {--retained= : Retained earnings account code}';
    protected $description = 'Run year-end close: transfer net P&L to retained earnings, optionally close the fiscal period.';

    public function handle(YearEndCloseService $service): int
    {
        $periodId = (int) $this->argument('period_id');
        $dryRun = (bool) $this->option('dry-run');
        $noClose = (bool) $this->option('no-close');
        $retained = $this->option('retained') ?: null;

        try {
            $res = $service->run($periodId, auth()->id() ?? null, $dryRun, !$noClose, $retained);
            $this->info('Income: ' . number_format($res['income'], 2));
            $this->info('Expense: ' . number_format($res['expense'], 2));
            $this->info('Net: ' . number_format($res['net'], 2));
            if (!$dryRun) {
                $this->info('Posted journal ID: ' . ($res['posted_journal_id'] ?? 'N/A'));
            } else {
                $this->warn('Dry-run: no journal posted.');
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
