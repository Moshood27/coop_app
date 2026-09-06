<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditMeetingAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600;

    protected $meetingId;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $meetingId = null)
    {
        $this->meetingId = $meetingId;
    }

    /**
     * Execute the job.
     */
    public function handle(AttendanceService $attendanceService): void
    {
        $timezone = config('cooperative.timezone', 'Africa/Lagos');
        $now = now($timezone);
        $todayStr = $now->toDateString();
        $nowStr = $now->toTimeString();

        $query = Meeting::query();

        if ($this->meetingId) {
            $query->where('id', $this->meetingId);
            // If specific meeting is provided, allow auditing even if it's already marked as audited
            // (e.g. to re-process if something went wrong)
            $query->whereIn('status', ['completed', 'ongoing', 'audited']);
        } else {
            // Audit meetings that are completed OR ongoing but time has passed
            $query->where(function ($q) use ($todayStr, $nowStr) {
                $q->where('status', 'completed')
                    ->orWhere(function ($sub) use ($todayStr, $nowStr) {
                        $sub->where('status', 'ongoing')
                            ->where(function ($inner) use ($todayStr, $nowStr) {
                                $inner->where('date', '<', $todayStr)
                                    ->orWhere(function ($qq) use ($todayStr, $nowStr) {
                                        $qq->where('date', $todayStr)
                                            ->where('end_time', '<=', $nowStr);
                                    });
                            });
                    });
            });
        }

        $meetings = $query->get();

        if ($meetings->isEmpty()) {
            Log::info("AuditMeetingAttendanceJob: No meetings to audit.");
            return;
        }

        foreach ($meetings as $meeting) {
            Log::info("Auditing meeting via Job: {$meeting->name} (ID: {$meeting->id})");

            $claimed = DB::transaction(function () use ($meeting) {
                $m = Meeting::where('id', $meeting->id)
                    ->whereIn('status', ['completed', 'ongoing', 'audited'])
                    ->lockForUpdate()
                    ->first();

                if ($m) {
                    // Only update status if it's not already audited
                    if ($m->status !== 'audited') {
                        $m->update(['status' => 'audited']);
                    }
                    return true;
                }
                return false;
            });

            if (!$claimed) {
                continue;
            }

            // Define who should have attended (non-admins)
            $userQuery = User::where('is_admin', false);
            if ($meeting->branches()->exists()) {
                $userQuery->whereIn('branch_id', $meeting->branches()->pluck('branches.id'));
            }

            $userQuery->chunkById(100, function ($users) use ($meeting, $attendanceService) {
                foreach ($users as $user) {
                    if ($user->isInNursingMotherGracePeriod()) {
                        continue;
                    }

                    $record = AttendanceRecord::where('meeting_id', $meeting->id)
                        ->where('user_id', $user->id)
                        ->first();

                    if ($record && in_array($record->status, ['excused', 'pending_excuse'])) {
                        continue;
                    }

                    if (!$record || $record->status === 'absent') {
                        $attendanceService->chargeAbsenceFine($user, $meeting, $record);
                    }
                }
            });

            Log::info("Meeting {$meeting->name} (ID: {$meeting->id}) audited successfully via Job.");
        }
    }
}
