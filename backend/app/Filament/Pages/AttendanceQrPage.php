<?php

namespace App\Filament\Pages;

use App\Models\Meeting;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class AttendanceQrPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $navigationLabel = 'Attendance QR';
    protected static ?string $title = 'Attendance QR Scanner';
    protected static string $view = 'filament.pages.attendance-qr-page';

    public ?int $meetingId = null;

    public function mount()
    {
        $ongoingMeeting = Meeting::where('status', 'ongoing')->first();
        if ($ongoingMeeting) {
            $this->meetingId = $ongoingMeeting->id;
        } else {
            $nextMeeting = Meeting::where('status', 'scheduled')
                ->where('date', '>=', now()->toDateString())
                ->orderBy('date')
                ->orderBy('start_time')
                ->first();
            if ($nextMeeting) {
                $this->meetingId = $nextMeeting->id;
            }
        }
    }

    public function getMeetings(): Collection
    {
        return Meeting::whereIn('status', ['scheduled', 'ongoing'])
            ->orderBy('date', 'desc')
            ->get()
            ->pluck('name', 'id');
    }

    public function getSelectedMeeting(): ?Meeting
    {
        if (!$this->meetingId) {
            return null;
        }

        return Meeting::find($this->meetingId);
    }
}
