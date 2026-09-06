<?php

namespace App\Jobs;

use App\Services\AdministrativeChargeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAdministrativeChargesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AdministrativeChargeService $service): void
    {
        Log::info('Starting background processing of administrative charges...');

        $stats = $service->processMonthlyCharges();

        Log::info('Background processing of administrative charges completed.', $stats);

        $service->settleAllOutstandingCharges();

        Log::info('Background settlement of outstanding charges completed.');
    }
}
