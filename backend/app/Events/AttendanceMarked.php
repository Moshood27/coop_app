<?php

namespace App\Events;

use App\Models\AttendanceRecord;
use App\Models\Meeting;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceMarked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public AttendanceRecord $record
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('attendance-qr.' . $this->meeting->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'marked';
    }

    public function broadcastWith(): array
    {
        return [
            'member_name' => $this->record->user?->name ?? 'Member',
            'attended_at' => $this->record->attended_at ? $this->record->attended_at->format('H:i:s') : now()->format('H:i:s'),
        ];
    }
}
