<?php

namespace App\Http\Controllers\Api;

use App\Events\AttendanceMarked;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\AttendanceRecord;
use App\Models\WalletTransaction;
use App\Models\User;
use App\Models\Setting;
use App\Services\GeoService;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    protected GeoService $geoService;
    protected AttendanceService $attendanceService;

    public function __construct(GeoService $geoService, AttendanceService $attendanceService)
    {
        $this->geoService = $geoService;
        $this->attendanceService = $attendanceService;
    }

    public function current(Request $request)
    {
        $user = $request->user();

        // Ensure meetings statuses are up-to-date for accurate "auto-start/stop"
        $this->syncStatuses();

        // Find ongoing meeting
        $meeting = Meeting::where('status', 'ongoing')
            ->where(function ($query) use ($user) {
                $query->whereDoesntHave('branches')
                    ->orWhereHas('branches', function ($q) use ($user) {
                        $q->where('branches.id', $user->branch_id);
                    });
            })
            ->first();

        // If no ongoing, find the next scheduled one
        if (!$meeting) {
            $meeting = Meeting::where('status', 'scheduled')
                ->where(function ($query) use ($user) {
                    $query->whereDoesntHave('branches')
                        ->orWhereHas('branches', function ($q) use ($user) {
                            $q->where('branches.id', $user->branch_id);
                        });
                })
                ->orderBy('date', 'asc')
                ->orderBy('start_time', 'asc')
                ->first();
        }

        if (!$meeting) {
            return response()->json([
                'meeting' => null,
                'attendance_record' => null,
                'message' => 'No active or upcoming meeting found'
            ]);
        }

        $record = $user->attendanceRecords()->where('meeting_id', $meeting->id)->first();

        $timezone = config('cooperative.timezone', 'Africa/Lagos');
        $now = now($timezone);
        $startTime = \Carbon\Carbon::parse($meeting->date->format('Y-m-d') . ' ' . $meeting->start_time, $timezone);
        $graceMinutes = $meeting->grace_period_minutes ?: config('cooperative.attendance.grace_period_minutes', 0);
        $lateAt = $startTime->copy()->addMinutes($graceMinutes);

        return response()->json([
            'meeting' => $meeting,
            'attendance_record' => $record,
            'in_grace_period' => $user->isInNursingMotherGracePeriod(),
            'server_time' => $now->toIso8601String(),
            'late_at' => $lateAt->toIso8601String(),
            'is_currently_late' => $now->greaterThan($lateAt),
            'fine_amount' => $meeting->apology_fine_amount ?? config('cooperative.attendance.apology_fine', 100),
        ]);
    }

    public function history(Request $request)
    {
        $user = $request->user();
        $history = $user->attendanceRecords()
            ->with('meeting')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($history);
    }

    private function syncStatuses()
    {
        $timezone = config('cooperative.timezone', 'Africa/Lagos');
        $now = now($timezone);
        $todayStr = $now->toDateString();
        $nowStr = $now->toTimeString();

        // Start meetings that should be ongoing
        Meeting::where('status', 'scheduled')
            ->where('date', '<=', $todayStr)
            ->where('start_time', '<=', $nowStr)
            ->where('end_time', '>', $nowStr)
            ->update(['status' => 'ongoing']);

        // End meetings that should be completed
        $completedCount = Meeting::whereIn('status', ['scheduled', 'ongoing'])
            ->where(function ($query) use ($todayStr, $nowStr) {
                $query->where('date', '<', $todayStr)
                    ->orWhere(function ($q) use ($todayStr, $nowStr) {
                        $q->where('date', $todayStr)
                            ->where('end_time', '<=', $nowStr);
                    });
            })
            ->update(['status' => 'completed']);

        if ($completedCount > 0) {
            // Auditing can be heavy, so we don't call it synchronously here.
            // It is already handled by the scheduled UpdateMeetingStatusesCommand (every minute)
           // Artisan::call('app:audit-attendance');


            // or AuditAttendanceCommand (hourly).
        }
    }

    public function markAttendance(Request $request, Meeting $meeting)
    {
        $request->validate([
            'pin' => 'nullable|string', // pin is optional if qr_token is provided
            'qr_token' => 'nullable|string',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'device_uuid' => 'required|string',
        ]);

        if ($meeting->status !== 'ongoing') {
            return response()->json(['message' => 'Meeting is not ongoing'], 400);
        }

        // Validate either PIN or QR Token
        if ($request->filled('qr_token') && Setting::get('attendance_qr_enabled', true)) {
            $cacheKey = "meeting_{$meeting->id}_qr_token";
            $storedToken = \Illuminate\Support\Facades\Cache::get($cacheKey);

            if (!$storedToken || $storedToken !== $request->qr_token) {
                return response()->json(['message' => 'Invalid or expired QR code'], 400);
            }

            // Atomically consume the token to prevent race conditions
            $pulledToken = \Illuminate\Support\Facades\Cache::pull($cacheKey);
            if ($pulledToken !== $request->qr_token) {
                return response()->json(['message' => 'QR code already used by another member'], 400);
            }

            // Successfully used QR token, refresh it for the next person
            $this->attendanceService->refreshAttendanceQrToken($meeting);
        } else {
            if (Setting::get('attendance_pin_enabled', true)) {
                if (!$request->filled('pin')) {
                    return response()->json(['message' => 'Either PIN or QR code is required'], 400);
                }
                if ($meeting->pin !== $request->pin) {
                    return response()->json(['message' => 'Invalid PIN'], 400);
                }
            }
        }

        if (is_null($meeting->venue_lat) || is_null($meeting->venue_lng)) {
             return response()->json(['message' => 'Meeting venue location not set by admin'], 400);
        }

        // Check distance
        $distance = $this->geoService->calculateDistance(
            (float) $meeting->venue_lat,
            (float) $meeting->venue_lng,
            (float) $request->lat,
            (float) $request->lng
        );

        $radius = (int) ($meeting->radius_meters ?: config('cooperative.attendance.radius_meters', 100));
        if ($distance > $radius) {
            return response()->json([
                'message' => 'You are too far from the venue. You must be within ' . $radius . ' meters. Current distance: ' . round($distance, 2) . 'm.',
                'distance' => round($distance, 2) . 'm'
            ], 400);
        }

        $user = $request->user();

        // Check for existing record to see if they were already excused or pending
        $existingRecord = AttendanceRecord::where('user_id', $user->id)
            ->where('meeting_id', $meeting->id)
            ->first();

        $isExempt = $user->isInNursingMotherGracePeriod() || ($existingRecord && in_array($existingRecord->status, ['excused', 'pending_excuse']));

        // One Person, One Vote: Check if this phone has already been used by someone else for THIS meeting
        $alreadyUsed = AttendanceRecord::where('meeting_id', $meeting->id)
            ->where('device_uuid', $request->device_uuid)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($alreadyUsed) {
            return response()->json([
                'message' => 'This device has already been used to mark attendance for another member in this meeting.'
            ], 403);
        }

        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'meeting_id' => $meeting->id],
            [
                'status' => 'present',
                'attended_at' => now(),
                'lat' => $request->lat,
                'lng' => $request->lng,
                'device_uuid' => $request->device_uuid,
            ]
        );

        // Silent failure for broadcasting
        try {
            broadcast(new AttendanceMarked($meeting, $record));
        } catch (\Throwable $e) {
            \Log::warning('Broadcasting attendance marked failed: ' . $e->getMessage());
        }

        $message = 'Attendance marked successfully';
        if ($this->attendanceService->isLate($meeting, $record->attended_at)) {
            // Check if user is exempt from fines (pregnancy grace or excused/pending)
            if ($isExempt) {
                $message .= '. You were late, but no fine was charged due to your status.';
            } else {
                $this->attendanceService->chargeLatenessFine($user, $meeting);
                $fineAmount = $meeting->apology_fine_amount ?? config('cooperative.attendance.apology_fine', 100);
                $message .= '. You were late and charged a lateness fine of ' . number_format($fineAmount) . '.';
            }
        }

        return response()->json(['message' => $message, 'record' => $record]);
    }

    public function searchMembers(Request $request)
    {
        if (!$request->user()->hasPermissionTo('mark_attendance')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = $request->get('q');
        $meetingId = $request->get('meeting_id');

        if (strlen($query) < 2) {
             return response()->json([]);
        }

        $userQuery = User::where('is_admin', false)
            ->where('is_defaulter', false);

        // Filter by meeting branches if meeting_id is provided
        if ($meetingId) {
            $meeting = Meeting::find($meetingId);
            if ($meeting && $meeting->branches()->exists()) {
                $branchIds = $meeting->branches()->pluck('branches.id');
                $userQuery->whereIn('branch_id', $branchIds);
            }
        }

        $users = $userQuery->where(function($q) use ($query) {
                $q->where('surname', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('other_names', 'like', "%{$query}%")
                    ->orWhere('membership_number', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->when($meetingId, function($q) use ($meetingId) {
                $q->withExists(['attendanceRecords as is_present' => function($q) use ($meetingId) {
                    $q->where('meeting_id', $meetingId)->where('status', 'present');
                }]);
            })
            ->limit(20)
            ->get(['id', 'surname', 'name', 'other_names', 'membership_number', 'phone', 'branch_id']);

        $users->makeHidden(['permission_names']);

        return response()->json($users);
    }

    public function markMemberAttendance(Request $request, Meeting $meeting)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        if (!$request->user()->hasPermissionTo('mark_attendance')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($meeting->status !== 'ongoing') {
            return response()->json(['message' => 'Meeting is not ongoing'], 400);
        }

        // Geofencing check for the Admin/Officer
        if (is_null($meeting->venue_lat) || is_null($meeting->venue_lng)) {
             return response()->json(['message' => 'Meeting venue location not set by admin'], 400);
        }

        $distance = $this->geoService->calculateDistance(
            (float) $meeting->venue_lat,
            (float) $meeting->venue_lng,
            (float) $request->lat,
            (float) $request->lng
        );

        $radius = (int) ($meeting->radius_meters ?: config('cooperative.attendance.radius_meters', 100));
        if ($distance > $radius) {
            return response()->json([
                'message' => 'You (Admin) are too far from the venue. You must be within ' . $radius . ' meters to mark attendance for others. Current distance: ' . round($distance, 2) . 'm.',
                'distance' => round($distance, 2) . 'm'
            ], 400);
        }

        $targetUser = User::findOrFail($request->user_id);

        // Check if already marked to avoid confusion and double marking
        $existing = AttendanceRecord::where('user_id', $targetUser->id)
            ->where('meeting_id', $meeting->id)
            ->where('status', 'present')
            ->first();

        if ($existing) {
             return response()->json([
                'message' => 'Attendance is already marked as present for ' . $targetUser->full_name,
                'record' => $existing
            ]);
        }

        // Optional: Check branch eligibility
        if ($meeting->branches()->exists()) {
            $isEligible = $meeting->branches()->where('branches.id', $targetUser->branch_id)->exists();
            if (!$isEligible) {
                return response()->json(['message' => 'Member is not eligible for this meeting (Branch mismatch)'], 400);
            }
        }

        try {
            $record = AttendanceRecord::updateOrCreate(
                ['user_id' => $targetUser->id, 'meeting_id' => $meeting->id],
                [
                    'status' => 'present',
                    'attended_at' => now(),
                    'verified_biometrically' => false,
                    'device_uuid' => 'marked_by_admin_' . $request->user()->id,
                ]
            );

            // Silent failure for broadcasting
            try {
                broadcast(new AttendanceMarked($meeting, $record));
            } catch (\Throwable $e) {
                \Log::warning('Broadcasting attendance marked failed: ' . $e->getMessage());
            }

            // Silent failure for notification
            try {
                $targetUser->notifyMember(
                    "Attendance Marked",
                    "Your attendance for '{$meeting->name}' has been marked by an authorized officer.",
                    ['type' => 'attendance_marked', 'meeting_id' => (string) $meeting->id],
                    ['push', 'database']
                );
            } catch (\Throwable $e) {
                \Log::warning('Notifying member attendance marked failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Attendance successfully marked for ' . $targetUser->full_name,
                'record' => $record
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to mark attendance: ' . $e->getMessage()], 500);
        }
    }

    public function getAttendanceQrPayload(Meeting $meeting)
    {
        // Only admins can see this
        if (!auth()->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payload = $this->attendanceService->getAttendanceQrPayload($meeting);

        return response()->json(['payload' => $payload]);
    }

    /**
     * Get WebAuthn options for marking attendance.
     */
    public function biometricOptions(AssertionRequest $request)
    {
        return $request->toVerify($request->user());
    }

    /**
     * Mark attendance using biometric verification.
     */
    public function markAttendanceBiometric(AssertedRequest $request, Meeting $meeting)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'device_uuid' => 'required|string',
        ]);

        if ($meeting->status !== 'ongoing') {
            return response()->json(['message' => 'Meeting is not ongoing'], 400);
        }

        if (!auth('web')->getProvider()->validateCredentials($request->user(), $request->validated())) {
            return response()->json(['message' => 'Biometric verification failed'], 422);
        }

        if (is_null($meeting->venue_lat) || is_null($meeting->venue_lng)) {
            return response()->json(['message' => 'Meeting venue location not set by admin'], 400);
        }

        // Check distance
        $distance = $this->geoService->calculateDistance(
            (float) $meeting->venue_lat,
            (float) $meeting->venue_lng,
            (float) $request->lat,
            (float) $request->lng
        );

        $radius = (int) ($meeting->radius_meters ?: config('cooperative.attendance.radius_meters', 100));
        if ($distance > $radius) {
            return response()->json([
                'message' => 'You are too far from the venue. You must be within ' . $radius . ' meters. Current distance: ' . round($distance, 2) . 'm.',
                'distance' => round($distance, 2) . 'm'
            ], 400);
        }

        $user = $request->user();

        // Check for existing record
        $existingRecord = AttendanceRecord::where('user_id', $user->id)
            ->where('meeting_id', $meeting->id)
            ->first();

        $isExempt = $user->isInNursingMotherGracePeriod() || ($existingRecord && in_array($existingRecord->status, ['excused', 'pending_excuse']));

        // One Person, One Vote: Check if this phone has already been used
        $alreadyUsed = AttendanceRecord::where('meeting_id', $meeting->id)
            ->where('device_uuid', $request->device_uuid)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($alreadyUsed) {
            return response()->json([
                'message' => 'This device has already been used to mark attendance for another member in this meeting.'
            ], 403);
        }

        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'meeting_id' => $meeting->id],
            [
                'status' => 'present',
                'attended_at' => now(),
                'lat' => $request->lat,
                'lng' => $request->lng,
                'device_uuid' => $request->device_uuid,
                'verified_biometrically' => true, // Set the flag
            ]
        );

        // Silent failure for broadcasting
        try {
            broadcast(new AttendanceMarked($meeting, $record));
        } catch (\Throwable $e) {
            \Log::warning('Broadcasting attendance marked failed: ' . $e->getMessage());
        }

        $message = 'Attendance marked successfully via Biometrics';
        if ($this->attendanceService->isLate($meeting, $record->attended_at)) {
            if ($isExempt) {
                $message .= '. You were late, but no fine was charged due to your status.';
            } else {
                $this->attendanceService->chargeLatenessFine($user, $meeting);
                $fineAmount = $meeting->apology_fine_amount ?? config('cooperative.attendance.apology_fine', 100);
                $message .= '. You were late and charged a lateness fine of ' . number_format($fineAmount) . '.';
            }
        }

        return response()->json(['message' => $message, 'record' => $record]);
    }

    /**
     * Mark attendance via BLE Beacon.
     */
    public function markAttendanceBeacon(Request $request, Meeting $meeting)
    {
        $request->validate([
            'beacon_uuid' => 'required|string',
            'beacon_major' => 'nullable|integer',
            'beacon_minor' => 'nullable|integer',
            'device_uuid' => 'required|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        if ($meeting->status !== 'ongoing') {
            return response()->json(['message' => 'Meeting is not ongoing'], 400);
        }

        // Verify beacon details match the meeting's configured beacon
        if (strcasecmp($meeting->beacon_uuid, $request->beacon_uuid) !== 0) {
            return response()->json(['message' => 'Invalid beacon for this meeting'], 400);
        }

        if ($meeting->beacon_major !== null && $meeting->beacon_major != $request->beacon_major) {
            return response()->json(['message' => 'Invalid beacon major ID'], 400);
        }

        if ($meeting->beacon_minor !== null && $meeting->beacon_minor != $request->beacon_minor) {
            return response()->json(['message' => 'Invalid beacon minor ID'], 400);
        }

        $user = $request->user();

        // Check for existing record
        $existingRecord = AttendanceRecord::where('user_id', $user->id)
            ->where('meeting_id', $meeting->id)
            ->first();

        $isExempt = $user->isInNursingMotherGracePeriod() || ($existingRecord && in_array($existingRecord->status, ['excused', 'pending_excuse']));

        // Device binding check
        $alreadyUsed = AttendanceRecord::where('meeting_id', $meeting->id)
            ->where('device_uuid', $request->device_uuid)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($alreadyUsed) {
            return response()->json(['message' => 'This device has already been used for another member.'], 403);
        }

        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'meeting_id' => $meeting->id],
            [
                'status' => 'present',
                'attended_at' => now(),
                'lat' => $request->lat,
                'lng' => $request->lng,
                'device_uuid' => $request->device_uuid,
                'verified_via_beacon' => true,
            ]
        );

        // Silent failure for broadcasting
        try {
            broadcast(new AttendanceMarked($meeting, $record));
        } catch (\Throwable $e) {
            \Log::warning('Broadcasting attendance marked failed: ' . $e->getMessage());
        }

        $message = 'Attendance marked successfully via Beacon';
        if ($this->attendanceService->isLate($meeting, $record->attended_at)) {
            if (!$isExempt) {
                $this->attendanceService->chargeLatenessFine($user, $meeting);
                $fineAmount = $meeting->apology_fine_amount ?? config('cooperative.attendance.apology_fine', 100);
                $message .= '. You were late and charged a lateness fine of ' . number_format($fineAmount) . '.';
            }
        }

        return response()->json(['message' => $message, 'record' => $record]);
    }

    /**
     * Submit an excuse pre-emptively or for lateness.
     */
    public function submitExcuse(Request $request, Meeting $meeting)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'type' => 'required|string|in:medical,work,travel,other',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $user = $request->user();

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('excuses', 'public');
        }

        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'meeting_id' => $meeting->id],
            [
                'status' => 'pending_excuse',
                'excuse_reason' => $request->reason,
                'excuse_type' => $request->type,
                'excuse_proof_path' => $proofPath,
            ]
        );

        return response()->json([
            'message' => 'Excuse submitted successfully and is pending review.',
            'record' => $record
        ]);
    }

    /**
     * Sync attendance records that were captured offline.
     */
    public function syncOfflineAttendance(Request $request)
    {
        $request->validate([
            'records' => 'required|array',
            'records.*.meeting_id' => 'required|exists:meetings,id',
            'records.*.attended_at' => 'required|date',
            'records.*.lat' => 'required|numeric',
            'records.*.lng' => 'required|numeric',
            'records.*.device_uuid' => 'required|string',
            'records.*.verification_type' => 'required|string|in:pin,qr,biometric,beacon',
        ]);

        $user = $request->user();
        $syncedCount = 0;
        $errors = [];

        foreach ($request->records as $index => $data) {
            $meeting = Meeting::find($data['meeting_id']);

            // Security check: Don't allow syncing for meetings too far in the past?
            // Or just rely on device_uuid and user_id uniqueness.

            // Check for existing record
            $existing = AttendanceRecord::where('user_id', $user->id)
                ->where('meeting_id', $meeting->id)
                ->first();

            if ($existing && $existing->status === 'present') {
                continue; // Already present
            }

            // Check device binding
            $alreadyUsed = AttendanceRecord::where('meeting_id', $meeting->id)
                ->where('device_uuid', $data['device_uuid'])
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($alreadyUsed) {
                $errors[] = "Record #{$index}: Device already used by another member.";
                continue;
            }

            $record = AttendanceRecord::updateOrCreate(
                ['user_id' => $user->id, 'meeting_id' => $meeting->id],
                [
                    'status' => 'present',
                    'attended_at' => $data['attended_at'],
                    'lat' => $data['lat'],
                    'lng' => $data['lng'],
                    'device_uuid' => $data['device_uuid'],
                    'is_offline_sync' => true,
                    'verified_biometrically' => $data['verification_type'] === 'biometric',
                    'verified_via_beacon' => $data['verification_type'] === 'beacon',
                ]
            );

            // Trigger fine if late
            if ($this->attendanceService->isLate($meeting, $record->attended_at)) {
                if (!$user->isInNursingMotherGracePeriod() && $record->status !== 'excused') {
                    $this->attendanceService->chargeLatenessFine($user, $meeting);
                }
            }

            $syncedCount++;
        }

        return response()->json([
            'message' => "Successfully synced {$syncedCount} records.",
            'errors' => $errors
        ]);
    }
}
