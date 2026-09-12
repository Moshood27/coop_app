<?php

use App\Models\MemberApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__.'/backend/vendor/autoload.php';
$app = require_once __DIR__.'/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting comprehensive reconciliation of guarantor data...\n";

$applications = MemberApplication::all();
echo "Found {$applications->count()} applications to check.\n";

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
            echo "Matched application for {$app->fullName} with guarantor {$guarantor->fullName} ({$guarantor->id})\n";
            $matchedCount++;
        }
    }

    // 2. Reconcile status for already approved members
    if ($app->admission_date || $app->approval_status === 'approved' || !empty($app->guarantor_signature_path)) {
        if ($app->guarantor_status !== 'accepted') {
            $app->guarantor_status = 'accepted';
            if (!$app->guarantor_responded_at) {
                $app->guarantor_responded_at = $app->admission_date ?: $app->updated_at;
            }
            $changed = true;
        }
    } elseif ($app->guarantor_id && ($app->guarantor_status === 'none' || empty($app->guarantor_status))) {
        $app->guarantor_status = 'pending';
        $changed = true;
    }

    if ($changed) {
        $app->saveQuietly();
        $updatedAppCount++;
    }

    // 3. Sync to User table
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
                $user->save();
                $syncedUserCount++;
            }
        }
    }
}

echo "Reconciliation complete.\n";
echo "Matched Guarantors: {$matchedCount}\n";
echo "Updated Applications: {$updatedAppCount}\n";
echo "Synced Users: {$syncedUserCount}\n";
