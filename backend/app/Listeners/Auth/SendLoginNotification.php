<?php

namespace App\Listeners\Auth;

use App\Notifications\Auth\LoginSuccessfulNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SendLoginNotification
{
    /**
     * Create the event listener.
     */
    public function __construct(protected Request $request)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        // Only send to non-admin members for now, or all users?
        // The issue says "member", so let's ensure it's a member.
        // Usually fintech apps do it for everyone.

        $ip = $this->request->ip();
        $userAgent = $this->request->userAgent();
        $time = Carbon::now();

        try {
            $user->notify(new LoginSuccessfulNotification($ip, $userAgent, $time));
        } catch (\Throwable $e) {
            \Log::error("Failed to send login notification to user {$user->id}: " . $e->getMessage());
        }
    }
}
