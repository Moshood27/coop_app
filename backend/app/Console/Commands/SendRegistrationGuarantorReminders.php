<?php

namespace App\Console\Commands;

use App\Models\MemberApplication;
use App\Mail\RegistrationGuarantorReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendRegistrationGuarantorReminders extends Command
{
    protected $signature = 'registration:remind-guarantors';

    protected $description = 'Send daily email reminders to pending registration guarantors';

    public function handle(): int
    {
        $pendingApplications = MemberApplication::whereNotNull('guarantor_id')
            ->where('guarantor_status', 'pending')
            ->whereNull('finalized_at')
            ->where(function ($query) {
                $query->whereNull('last_guarantor_reminder_sent_at')
                      ->orWhere('last_guarantor_reminder_sent_at', '<=', Carbon::now()->subDay());
            })
            ->get();

        $count = 0;
        foreach ($pendingApplications as $app) {
            $guarantor = $app->guarantor;
            if ($guarantor && $guarantor->email) {
                Mail::to($guarantor->email)->send(new RegistrationGuarantorReminder($app, $guarantor));
                $app->update(['last_guarantor_reminder_sent_at' => now()]);
                $count++;
            }
        }

        $this->info("Sent {$count} registration guarantor reminders.");
        return self::SUCCESS;
    }
}
