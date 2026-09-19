<?php

namespace App\Livewire;

use App\Models\Meeting;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Livewire\Component;

class AttendanceQr extends Component
{
    public Meeting $meeting;
    public string $payload = '';
    public bool $qrEnabled = true;
    public array $recentAttendees = [];

    public function mount(Meeting $meeting)
    {
        $this->meeting = $meeting;
        $this->refreshPayload();
        $this->loadRecentAttendees();
    }

    public function getListeners()
    {
        return [
            "echo:attendance-qr.{$this->meeting->id},.refreshed" => 'onQrRefreshed',
            "echo:attendance-qr.{$this->meeting->id},.marked" => 'onAttendanceMarked',
            // Clear UI when meeting is deleted
            "echo:attendance-qr.{$this->meeting->id},.meeting_deleted" => 'onMeetingDeleted',
        ];
    }

    public function onQrRefreshed($data)
    {
        $this->payload = $data['payload'];
    }

    public function onAttendanceMarked($data)
    {
        array_unshift($this->recentAttendees, $data);
        if (count($this->recentAttendees) > 5) {
            array_pop($this->recentAttendees);
        }
    }

    public function onMeetingDeleted($data)
    {
        // Meeting deleted: clear recent list and disable QR on the widget
        $this->recentAttendees = [];
        $this->qrEnabled = false;
        // Optionally, we can dispatch a browser event for UX (non-blocking)
        try {
            $this->dispatchBrowserEvent('meeting-deleted', [
                'meeting_id' => $data['meeting_id'] ?? $this->meeting->id,
                'meeting_name' => $data['meeting_name'] ?? $this->meeting->name,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function loadRecentAttendees()
    {
        $this->recentAttendees = AttendanceRecord::where('meeting_id', $this->meeting->id)
            ->where('status', 'present')
            ->latest('attended_at')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'member_name' => $r->user?->name ?? 'Unknown',
                'attended_at' => $r->attended_at ? $r->attended_at->format('H:i:s') : '--:--:--',
            ])
            ->toArray();
    }

    public function refreshPayload()
    {
        $this->qrEnabled = (bool) \App\Models\Setting::get('attendance_qr_enabled', true);
        if ($this->qrEnabled) {
            $attendanceService = app(AttendanceService::class);
            $this->payload = $attendanceService->getAttendanceQrPayload($this->meeting);
        }
    }

    public function toggleQr()
    {
        \App\Models\Setting::set('attendance_qr_enabled', !$this->qrEnabled);
        $this->refreshPayload();
    }

    public function render()
    {
        return view('livewire.attendance-qr');
    }
}
