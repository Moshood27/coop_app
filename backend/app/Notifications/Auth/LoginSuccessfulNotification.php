<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class LoginSuccessfulNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $ip,
        public string $userAgent,
        public Carbon $time
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];
        if ((bool) ($notifiable->notify_email ?? true) && !empty($notifiable->email) && filter_var(trim($notifiable->email), FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Security Alert: New Login to Your Account')
            ->greeting('Assalāmu ‘alaykum ' . $notifiable->name . ',')
            ->line('Your account was just accessed from a new device.')
            ->line('**Login Details:**')
            ->line('**Time:** ' . $this->time->toDayDateTimeString() . ' (WAT)')
            ->line('**IP Address:** ' . $this->ip)
            ->line('**Device:** ' . $this->userAgent)
            ->line('If this was you, you can safely ignore this email.')
            ->line('If you did not log in, please secure your account immediately by resetting your password or contacting support.')
            ->action('Secure Account', config('app.frontend_url') . '/forgot-password')
            ->line('Stay safe,')
            ->line(config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'time' => $this->time,
        ];
    }
}
