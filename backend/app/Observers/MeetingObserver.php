<?php

namespace App\Observers;

use App\Models\Meeting;
use App\Jobs\AuditMeetingAttendanceJob;

class MeetingObserver
{
    /**
     * Handle the Meeting "updated" event.
     */
    public function updated(Meeting $meeting): void
    {
        // If status changed to 'completed' or 'audited', trigger background audit
        if ($meeting->isDirty('status')) {
            $newStatus = $meeting->status;
            $oldStatus = $meeting->getOriginal('status');

            if (in_array($newStatus, ['completed', 'audited']) && $oldStatus !== 'audited') {
                AuditMeetingAttendanceJob::dispatch($meeting->id);
            }
        }
    }

    /**
     * Handle the Meeting "created" event.
     */
    public function created(Meeting $meeting): void
    {
        // If created directly as completed or audited
        if (in_array($meeting->status, ['completed', 'audited'])) {
            AuditMeetingAttendanceJob::dispatch($meeting->id);
        }
    }
}
