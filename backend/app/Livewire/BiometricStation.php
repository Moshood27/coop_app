<?php

namespace App\Livewire;

use App\Models\Meeting;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Laragear\WebAuthn\JsonTransport;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreation;
use Laragear\WebAuthn\Attestation\Creator\AttestationCreator;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidation;
use Laragear\WebAuthn\Attestation\Validator\AttestationValidator;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreation;
use Laragear\WebAuthn\Assertion\Creator\AssertionCreator;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidation;
use Laragear\WebAuthn\Assertion\Validator\AssertionValidator;
use Livewire\Component;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class BiometricStation extends Component
{
    public Meeting $meeting;
    public ?int $selectedUserId = null;
    public $search = '';
    public bool $isEnrolling = false;

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

    public function getSelectedUserProperty()
    {
        return $this->selectedUserId ? User::find($this->selectedUserId) : null;
    }

    public function startEnrollment()
    {
        $user = $this->selectedUser;
        if (!$user) return;

        try {
            $attestation = new AttestationCreation($user);
            // We allow duplicates so they can register multiple fingers or on multiple devices (admin's station)
            $attestation->uniqueCredentials = false;

            $options = app(AttestationCreator::class)->send($attestation)->then(fn($c) => $c->json);

            $this->dispatch('start-webauthn-registration', options: $options->toArray());
        } catch (\Exception $e) {
            $this->dispatch('error-occurred');
            Notification::make()->title('Error starting enrollment: ' . $e->getMessage())->danger()->send();
        }
    }

    public function completeEnrollment($data)
    {
        $user = $this->selectedUser;
        if (!$user) return;

        try {
            $attestation = new AttestationValidation($user, new JsonTransport($data));
            $success = app(AttestationValidator::class)->validate($attestation)->then(function ($v) {
                $v->user->addCredential($v);
                return true;
            });

            if ($success) {
                Notification::make()->title('Biometric enrolled successfully for ' . $user->full_name)->success()->send();
                $this->dispatch('enrollment-completed');
            }
        } catch (\Exception $e) {
            $this->dispatch('error-occurred');
            Log::error('WebAuthn Registration Error: ' . $e->getMessage());
            Notification::make()->title('Enrollment failed: ' . $e->getMessage())->danger()->send();
        }
    }

    public function startVerification()
    {
        $user = $this->selectedUser;
        if (!$user) return;

        if ($user->webAuthnCredentials()->count() === 0) {
            Notification::make()->title('User has no biometrics enrolled. Please enroll first.')->warning()->send();
            return;
        }

        try {
            $assertion = new AssertionCreation($user);
            $options = app(AssertionCreator::class)->send($assertion)->then(fn($c) => $c->json);

            $this->dispatch('start-webauthn-verification', options: $options->toArray());
        } catch (\Exception $e) {
            $this->dispatch('error-occurred');
            Notification::make()->title('Error starting verification: ' . $e->getMessage())->danger()->send();
        }
    }

    public function completeVerification($data)
    {
        $user = $this->selectedUser;
        if (!$user) return;

        try {
            $assertion = new AssertionValidation(new JsonTransport($data), $user);
            $success = app(AssertionValidator::class)->validate($assertion)->then(function ($v) {
                return true;
            });

            if ($success) {
                $this->markAttendance($user);
            }
        } catch (\Exception $e) {
            $this->dispatch('error-occurred');
            Log::error('WebAuthn Verification Error: ' . $e->getMessage());
            Notification::make()->title('Verification failed: ' . $e->getMessage())->danger()->send();
        }
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
