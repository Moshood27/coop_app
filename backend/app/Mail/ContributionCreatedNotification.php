<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContributionCreatedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $contributions,
        public float $accountTotal,
    ) {
    }

    public function build()
    {
        $appName = config('app.name');

        return $this
            ->subject("Contribution Received - {$appName}")
            ->view('emails.contribution_created', [
                'user' => $this->user,
                'contributions' => $this->contributions,
                'accountTotal' => $this->accountTotal,
                'appName' => $appName,
                'timestamp' => now(),
            ]);
    }
}
