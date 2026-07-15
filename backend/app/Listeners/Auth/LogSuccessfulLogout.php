<?php

namespace App\Listeners\Auth;

use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

class LogSuccessfulLogout
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
    public function handle(Logout $event): void
    {
        if ($event->user) {
            activity('auth')
                ->performedOn($event->user)
                ->causedBy($event->user)
                ->withProperties([
                    'ip' => $this->request->ip(),
                    'user_agent' => $this->request->userAgent(),
                ])
                ->log('User logged out');

            // Clear last activity to ensure they don't show up in "Online Members" widget fallback
            if (method_exists($event->user, 'updateQuietly')) {
                $event->user->updateQuietly(['last_activity_at' => null]);
            }
        }
    }
}
