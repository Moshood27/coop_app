<?php

namespace App\Events;

use App\Models\Meeting;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceQrRefreshed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public string $payload
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('attendance-qr.' . $this->meeting->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'refreshed';
    }
}
