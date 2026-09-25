<?php

namespace App\Models\Traits;

use App\Events\UserAccountUpdated;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\Log;

trait MemberNotifications
{
    /**
     * Send a member-facing notification through enabled channels.
     *
     * @param string $title
     * @param string $message
     * @param array $data Optional payload for push/database channels
     * @param array|null $channels Subset of ['database','mail','sms','push']; null = auto from preferences
     * @param bool $broadcast Whether to trigger a real-time UserAccountUpdated event
     */
    public function notifyMember(string $title, string $message, array $data = [], ?array $channels = null, bool $broadcast = true): void
    {
        try {
            if ($broadcast) {
                // Trigger real-time dashboard update
                event(new UserAccountUpdated($this, $message, $data));
            }

            $resolved = $channels ?: [
                ($this->notify_email ? 'mail' : null),
                ($this->notify_sms ? 'sms' : null),
                ($this->notify_push ? 'push' : null),
                'database',
            ];
            $resolved = array_filter($resolved);

            $useMail = in_array('mail', $resolved);
            $useDb = in_array('database', $resolved);
            $usePush = in_array('push', $resolved);
            $useSms = in_array('sms', $resolved);

            // Use queued Laravel notification for all channels
            $this->notify(new GeneralNotification(
                $title,
                $message,
                $data,
                $useMail,
                $useDb,
                $usePush,
                $useSms
            ));
        } catch (\Throwable $e) {
            Log::error("Failed to notify member: " . $e->getMessage());
        }
    }

    public function routeNotificationForMail($notification)
    {
        $email = trim($this->email ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return null;
    }
}
