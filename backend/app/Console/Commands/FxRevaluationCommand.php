<?php

namespace App\Console\Commands;

use App\Services\FxRevaluationService;
use Illuminate\Console\Command;

class FxRevaluationCommand extends Command
{
    protected $signature = 'accounting:fx-revalue {--date=} {--currency=} {--dry-run}';
    protected $description = 'Run FX revaluation scaffold for the given date and optional currency.';

    public function handle(FxRevaluationService $svc): int
    {
        $date = $this->option('date') ?: now()->toDateString();
        $ccy = $this->option('currency') ?: null;
        $dry = (bool) $this->option('dry-run');

        $res = $svc->run($date, $ccy, auth()->id() ?? null, $dry);
        foreach ($res as $k => $v) {
            $this->line($k . ': ' . (is_scalar($v) ? $v : json_encode($v)));
        }

        return self::SUCCESS;
    }
}
