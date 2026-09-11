<?php

namespace App\Console\Commands;

use App\Models\MemberApplication;
use App\Models\User;
use App\Mail\RegistrationGuarantorReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ReconcileLegacyGuarantors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'registration:reconcile-guarantors {--dry-run : Only show potential matches without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile legacy registrations that used string names for guarantors instead of user IDs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $applications = MemberApplication::whereNull('guarantor_id')
            ->where(function ($query) {
                $query->whereNotNull('guarantor_name')
                    ->orWhereNotNull('guarantor_phone');
            })
            ->where(function ($query) {
                $query->whereNull('finalized_at')
                    ->orWhere('guarantor_status', '!=', 'accepted');
            })
            ->get();

        $this->info("Found {$applications->count()} applications to check.");

        $matchedCount = 0;

        foreach ($applications as $app) {
            $guarantor = null;

            // 1. Try matching by phone if available
            if ($app->guarantor_phone) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $app->guarantor_phone);
                if (strlen($cleanPhone) >= 10) {
                    $guarantor = User::where('phone', 'like', "%$cleanPhone%")->first();
                }
            }

            // 2. Try matching by name if no phone match
            if (!$guarantor && $app->guarantor_name) {
                $searchName = strtolower(trim($app->guarantor_name));

                $guarantor = User::where(function ($q) use ($searchName) {
                    $q->whereRaw("LOWER(TRIM(CONCAT_WS(' ', surname, name, other_names))) = ?", [$searchName])
                        ->orWhereRaw("LOWER(TRIM(CONCAT_WS(' ', name, surname))) = ?", [$searchName])
                        ->orWhereRaw("LOWER(TRIM(CONCAT_WS(' ', surname, name))) = ?", [$searchName]);
                })->first();
            }

            if ($guarantor) {
                $this->info("Matched application for {$app->full_name} with guarantor {$guarantor->full_name} ({$guarantor->id})");

                if (!$this->option('dry-run')) {
                    $app->guarantor_id = $guarantor->id;
                    $app->guarantor_status = 'pending';
                    $app->save();
                    // Notification is handled by MemberApplication model hook
                }
                $matchedCount++;
            }
        }

        $this->info("Reconciliation complete. Total matches: {$matchedCount}");

        return 0;
    }
}
