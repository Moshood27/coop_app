<?php

namespace App\Listeners\Auth;

use App\Notifications\Auth\LoginSuccessfulNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

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

        $ip = $this->request->ip();
        $userAgent = $this->request->userAgent();
        $time = Carbon::now();

        // Check if this device (User Agent) has been used by the user before.
        // We look for 'auth' logs where 'User logged in' was recorded.
        // We fetch recent logs and check in PHP to be more reliable than JSON path matching.
        try {
            $isKnownDevice = Activity::where('log_name', 'auth')
                ->where('subject_id', $user->id)
                ->where('subject_type', $user->getMorphClass())
                ->where('description', 'User logged in')
                ->where('created_at', '<', $time->copy()->subSeconds(10))
                ->latest()
                ->limit(20)
                ->get()
                ->contains(function ($activity) use ($userAgent) {
                    $storedUA = $activity->properties['user_agent'] ?? '';
                    return trim((string)$storedUA) === trim((string)$userAgent);
                });

            if ($isKnownDevice) {
                return;
            }
        } catch (\Throwable $e) {
            // If activity check fails, we proceed with notification to be safe
            \Log::warning("Failed to check known devices for user {$user->id}: " . $e->getMessage());
        }

        try {
            $user->notify(new LoginSuccessfulNotification($ip, $userAgent, $time));
        } catch (\Throwable $e) {
            \Log::error("Failed to send login notification to user {$user->id}: " . $e->getMessage());
        }
    }
}
