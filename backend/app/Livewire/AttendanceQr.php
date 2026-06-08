<?php

namespace App\Livewire;

use App\Models\Meeting;
use App\Services\AttendanceService;
use Livewire\Component;

class AttendanceQr extends Component
{
    public Meeting $meeting;
    public string $payload = '';

    public function mount(Meeting $meeting)
    {
        $this->meeting = $meeting;
        $this->refreshPayload();
    }

    public function refreshPayload()
    {
        $attendanceService = app(AttendanceService::class);
        $this->payload = $attendanceService->getAttendanceQrPayload($this->meeting);
    }

    public function render()
    {
        return view('livewire.attendance-qr');
    }
}
