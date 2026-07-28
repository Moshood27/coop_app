<?php

namespace App\Listeners\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;

class LogLockout
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
    public function handle(Lockout $event): void
    {
        $identity = $event->request->membership_number
            ?? ($event->request->email
            ?? ($event->request->phone ?? 'unknown'));

        \App\Services\SecurityLogger::logVelocityAlert('login', (string) $identity);

        activity('security')
            ->withProperties([
                'ip' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
                'identity' => $identity,
            ])
            ->log('User account locked out due to multiple failed attempts');
    }
}
