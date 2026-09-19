<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeetingDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $meetingId,
        public string $meetingName
    ) {}

    public function broadcastOn(): array
    {
        // Broadcast to the same channel family used by attendance updates
        return [
            new Channel('attendance-qr.' . $this->meetingId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'meeting_deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'meeting_id' => $this->meetingId,
            'meeting_name' => $this->meetingName,
        ];
    }
}
