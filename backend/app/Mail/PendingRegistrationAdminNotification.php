<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PendingRegistrationAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function build()
    {
        return $this
            ->subject('Action Required: New Membership Registration Pending Approval - ' . $this->user->full_name)
            ->markdown('emails.admin.pending_registration', [
                'user' => $this->user,
                'url' => config('app.url') . '/admin/member-applications'
            ]);
    }
}
