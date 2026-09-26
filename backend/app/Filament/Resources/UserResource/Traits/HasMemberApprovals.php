<?php

namespace App\Filament\Resources\UserResource\Traits;

use App\Models\User;
use App\Models\MemberApplication;
use App\Models\ShariahAudit;
use App\Models\Setting;
use App\Mail\NewMemberWelcome;
use App\Mail\MemberApplicationRejected;
use App\Utils\SecurityUtils;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

trait HasMemberApprovals
{
    public static function getApprovalActions(): array
    {
        return [
            Action::make('approveMember')
                ->label('Approve Member')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (User $record) => $record->approval_status !== 'approved' && !$record->is_admin)
                ->requiresConfirmation()
                ->action(function (User $record) {
                    $record->approval_status = 'approved';
                    if (empty($record->admission_date)) $record->admission_date = now();
                    if (empty($record->admission_officer_name)) $record->admission_officer_name = auth()->user()->name;
                    $record->save();

                    // Sync with application if exists
                    MemberApplication::where('email', $record->email)->update([
                        'approval_status' => 'approved',
                        'user_id' => $record->id,
                        'finalized_at' => now(),
                    ]);

                    ShariahAudit::log(auth()->user(), 'approve_member_manually', [
                        'user_id' => $record->id,
                        'email' => $record->email,
                    ]);

                    // Send welcome email
                    try {
                        if ($email = SecurityUtils::filterEmail($record->email)) {
                            Mail::to($email)->send(new NewMemberWelcome($record));
                        }
                        $record->notifyMember(
                            "Membership Approved",
                            "Assalāmu ‘alaykum {$record->name}, your membership has been approved. You can now log in to the app.",
                            ['type' => 'membership_approved']
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to send welcome notification', ['error' => $e->getMessage()]);
                    }

                    Notification::make()
                        ->title('Member Approved Successfully')
                        ->success()
                        ->send();
                }),
            Action::make('rejectMember')
                ->label('Reject Member')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (User $record) => $record->approval_status !== 'approved' && !$record->is_admin)
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason for rejection')
                        ->required()
                        ->maxLength(1000),
                ])
                ->requiresConfirmation()
                ->action(function (User $record, array $data) {
                    $record->approval_status = 'rejected';
                    $record->officer_recommendation = $data['reason'];
                    $record->save();

                    // Sync with application if exists
                    $application = MemberApplication::where('email', $record->email)->first();
                    if ($application) {
                        $application->update([
                            'approval_status' => 'rejected',
                            'officer_recommendation' => $data['reason'],
                            'finalized_at' => now(),
                        ]);
                    }

                    ShariahAudit::log(auth()->user(), 'reject_member_manually', [
                        'user_id' => $record->id,
                        'reason' => $data['reason'],
                    ]);

                    // Send rejection email
                    try {
                        if ($email = SecurityUtils::filterEmail($record->email)) {
                            Mail::to($email)->send(new MemberApplicationRejected($application ?? $record, $data['reason']));
                        }
                        $record->notifyMember(
                            "Membership Application Rejected",
                            "Regrettably, your membership application has been rejected. Reason: " . $data['reason'],
                            ['type' => 'membership_rejected']
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to send rejection notification', ['error' => $e->getMessage()]);
                    }

                    Notification::make()
                        ->title('Member Rejected')
                        ->danger()
                        ->send();
                }),
            Action::make('approveNursingMotherGrace')
                ->label('Approve Nursing Mother Grace')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (User $record) => $record->nursing_mother_status === 'pending')
                ->requiresConfirmation()
                ->action(function (User $record) {
                    $months = (int) Setting::get('nursing_mother_grace_period_months', 3);
                    $record->nursing_mother_status = 'approved';
                    $record->nursing_mother_grace_until = now()->addMonths($months);
                    $record->save();

                    $record->notifyMember(
                        "Nursing Mother Grace Approved",
                        "Assalāmu ‘alaykum, your nursing mother grace application has been approved. You are exempt from attendance fines until " . $record->nursing_mother_grace_until->toDateString() . ".",
                        ['type' => 'nursing_mother_grace_approved']
                    );

                    Notification::make()
                        ->title('Nursing Mother Grace Approved')
                        ->success()
                        ->send();
                }),
            Action::make('rejectNursingMotherGrace')
                ->label('Reject Nursing Mother Grace')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (User $record) => $record->nursing_mother_status === 'pending')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                ])
                ->requiresConfirmation()
                ->action(function (User $record, array $data) {
                    $record->nursing_mother_status = 'rejected';
                    $record->save();

                    $record->notifyMember(
                        "Nursing Mother Grace Application Rejected",
                        "Your nursing mother grace application was not approved. Reason: " . $data['reason'],
                        ['type' => 'nursing_mother_grace_rejected']
                    );

                    Notification::make()
                        ->title('Nursing Mother Grace Rejected')
                        ->danger()
                        ->send();
                }),
        ];
    }
}
