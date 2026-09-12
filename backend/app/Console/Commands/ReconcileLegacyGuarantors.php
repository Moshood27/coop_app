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
        $this->info("Starting comprehensive reconciliation of guarantor data...");

        $applications = MemberApplication::all();
        $this->info("Found {$applications->count()} applications to check.");

        $matchedCount = 0;
        $updatedAppCount = 0;
        $syncedUserCount = 0;

        foreach ($applications as $app) {
            $changed = false;

            // 1. Try to find guarantor_id if missing
            if (empty($app->guarantor_id)) {
                $guarantor = MemberApplication::findGuarantorMatch($app->guarantor_phone, $app->guarantor_name);
                if ($guarantor) {
                    $app->guarantor_id = $guarantor->id;
                    $changed = true;
                    $this->info("Matched application for {$app->full_name} with guarantor {$guarantor->full_name} ({$guarantor->id})");
                    $matchedCount++;
                }
            }

            // 2. Reconcile status
            if ($app->guarantor_id) {
                // If there is no digital signature path, it should be pending so they can sign it
                // unless it was explicitly declined.
                if (empty($app->guarantor_signature_path)) {
                    if ($app->guarantor_status !== 'pending' && $app->guarantor_status !== 'declined') {
                        $app->guarantor_status = 'pending';
                        $changed = true;
                    }
                } else {
                    // Has signature path, so it should be accepted
                    if ($app->guarantor_status !== 'accepted') {
                        $app->guarantor_status = 'accepted';
                        if (!$app->guarantor_responded_at) {
                            $app->guarantor_responded_at = $app->admission_date ?: $app->updated_at;
                        }
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                if (!$this->option('dry-run')) {
                    $app->saveQuietly(); // Use saveQuietly to avoid triggering the 'saved' hook reminder
                    $updatedAppCount++;
                } else {
                    $this->info("Would update application {$app->id} ({$app->full_name}) to status: {$app->guarantor_status}");
                }
            }

            // 3. Sync to User table if application is approved and linked to a user
            if ($app->user_id) {
                $user = User::find($app->user_id);
                if ($user) {
                    $userChanges = false;

                    if ($user->guarantor_id !== $app->guarantor_id) {
                        $user->guarantor_id = $app->guarantor_id;
                        $userChanges = true;
                    }
                    if ($user->guarantor_status !== $app->guarantor_status) {
                        $user->guarantor_status = $app->guarantor_status;
                        $userChanges = true;
                    }
                    if ($user->guarantor_responded_at != $app->guarantor_responded_at) {
                        $user->guarantor_responded_at = $app->guarantor_responded_at;
                        $userChanges = true;
                    }
                    if ($user->guarantor_signature_path !== $app->guarantor_signature_path) {
                        $user->guarantor_signature_path = $app->guarantor_signature_path;
                        $userChanges = true;
                    }

                    if ($userChanges) {
                        if (!$this->option('dry-run')) {
                            $user->save();
                            $syncedUserCount++;
                        } else {
                            $this->info("Would sync guarantor data to User {$user->id} ({$user->full_name})");
                        }
                    }
                }
            }
        }

        $this->info("Reconciliation complete.");
        $this->info("Matched Guarantors: {$matchedCount}");
        $this->info("Updated Applications: {$updatedAppCount}");
        $this->info("Synced Users: {$syncedUserCount}");

        return 0;
    }
}
