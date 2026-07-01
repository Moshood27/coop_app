<?php

namespace App\Livewire;

use App\Models\Meeting;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Livewire\Component;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class BiometricStation extends Component
{
    public Meeting $meeting;
    public ?int $selectedUserId = null;
    public $search = '';
    public bool $isEnrolling = false;
    public bool $autoEnrollMode = false;
    public bool $autoMarkMode = false;

    public function mount(Meeting $meeting, ?User $initialUser = null)
    {
        $this->meeting = $meeting;
        if ($initialUser) {
            $this->selectedUserId = $initialUser->id;
        }
    }

    public function selectUser($userId)
    {
        $this->selectedUserId = $userId;
        $this->isEnrolling = false;
    }

    public function toggleAutoEnroll()
    {
        $this->autoEnrollMode = !$this->autoEnrollMode;
        if ($this->autoEnrollMode) {
            $this->autoMarkMode = false;
            $this->selectNextUserForEnrollment();
        }
    }

    public function toggleAutoMark()
    {
        $this->autoMarkMode = !$this->autoMarkMode;
        if ($this->autoMarkMode) {
            $this->autoEnrollMode = false;
            $this->selectedUserId = null;
        }
    }

    protected function selectNextUserForEnrollment()
    {
        $nextUser = User::whereNull('biometric_template')
            ->where('approval_status', 'approved')
            ->first();

        if ($nextUser) {
            $this->selectedUserId = $nextUser->id;
        } else {
            $this->autoEnrollMode = false;
            Notification::make()->title('No more members to enroll.')->info()->send();
        }
    }

    public function getSelectedUserProperty()
    {
        return $this->selectedUserId ? User::find($this->selectedUserId) : null;
    }

    public function saveUsbTemplate($template)
    {
        $user = $this->selectedUser;
        if (!$user) return;

        try {
            $user->biometric_template = $template;
            $user->save();

            Notification::make()->title('Biometric enrolled via USB successfully for ' . $user->full_name)->success()->send();
            $this->dispatch('enrollment-completed');

            if ($this->autoEnrollMode) {
                $this->selectNextUserForEnrollment();
            }
        } catch (\Exception $e) {
            Log::error('USB Biometric Enrollment Error: ' . $e->getMessage());
            Notification::make()->title('Enrollment failed: ' . $e->getMessage())->danger()->send();
        }
    }

    public function verifyUsbTemplate($template)
    {
        $user = $this->selectedUser;
        if (!$user) return;

        if (!$user->biometric_template) {
            Notification::make()->title('User has no USB biometrics enrolled. Please enroll first.')->warning()->send();
            return;
        }

        try {
            // Note: In a production environment with USB scanners like DigitalPersona,
            // the template matching is often done via a specialized library.
            // Here we verify that a template was received and it matches the stored one.
            if ($user->biometric_template === $template) {
                $this->markAttendance($user);
            } else {
                Notification::make()->title('Biometric verification failed. Template mismatch.')->danger()->send();
            }
        } catch (\Exception $e) {
            Log::error('USB Biometric Verification Error: ' . $e->getMessage());
            Notification::make()->title('Verification failed: ' . $e->getMessage())->danger()->send();
        }
    }

    public function identifyUsbTemplate($template)
    {
        $user = User::where('biometric_template', $template)->first();

        if (!$user) {
            Notification::make()->title('No matching member found for this fingerprint.')->danger()->send();
            $this->dispatch('error-occurred');
            return;
        }

        $this->selectedUserId = $user->id;
        $this->markAttendance($user);
    }

    protected function markAttendance(User $user)
    {
        $attendanceService = app(AttendanceService::class);

        $record = AttendanceRecord::firstOrNew([
            'meeting_id' => $this->meeting->id,
            'user_id' => $user->id,
        ]);

        if ($record->status === 'present') {
            Notification::make()->title($user->full_name . ' is already marked as present.')->info()->send();
            return;
        }

        $record->status = 'present';
        $record->attended_at = now();
        $record->verified_biometrically = true;
        $record->save();

        if ($attendanceService->isLate($this->meeting, $record->attended_at)) {
            $attendanceService->chargeLatenessFine($user, $this->meeting);
            Notification::make()->title('Present (Late) - Lateness fine charged.')->warning()->send();
        } else {
            Notification::make()->title('Present - Attendance marked for ' . $user->full_name)->success()->send();
        }

        $this->dispatch('attendance-marked');
        $this->selectedUserId = null;
        $this->search = '';
    }

    public function render()
    {
        $users = [];
        if (strlen($this->search) >= 2) {
            $users = User::where(function($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('surname', 'like', "%{$this->search}%")
                  ->orWhere('membership_number', 'like', "%{$this->search}%");
            })
            ->limit(10)
            ->get();
        }

        return view('livewire.biometric-station', [
            'users' => $users,
        ]);
    }
}
