<?php

namespace App\Filament\Resources\UserResource\Traits;

use App\Models\User;
use App\Models\Scheme;
use App\Models\Contribution;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Services\AdministrativeChargeService;
use App\Services\ChatService;
use App\Services\TakafulService;
use App\Services\AttendanceService;
use App\Notifications\WellnessCheckNotification;
use App\Models\ShariahAudit;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Filament\Resources\ChatRoomResource;

trait HasTableActions
{
    public static function getTableActions(): array
    {
        return [
            Action::make('printInfo')
                ->label('Print Info')
                ->icon('heroicon-o-printer')
                ->action(function (User $record) {
                    $pdf = Pdf::loadView('pdfs.bulk_membership_applications', ['users' => [$record]])->setPaper('a4');
                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, Str::replace(['/', '\\'], '_', "member-{$record->membership_number}.pdf"));
                }),
            Tables\Actions\EditAction::make(),
            Action::make('wellnessCheck')
                ->label('Send Wellness Check')
                ->icon('heroicon-o-heart')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Send Wellness Check')
                ->modalDescription('Are you sure you want to send a wellness check notification to this member?')
                ->modalSubmitActionLabel('Yes, send it')
                ->action(function (User $record) {
                    $record->notify(new WellnessCheckNotification());
                    $record->update(['wellness_check_notified_at' => now()]);

                    Notification::make()
                        ->title('Wellness check notification sent.')
                        ->success()
                        ->send();
                }),
            Action::make('chat')
                ->label('Chat')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->action(function (User $record, ChatService $chatService) {
                    $room = $chatService->getOrCreatePrivateRoom(auth()->user(), $record);
                    return redirect(ChatRoomResource::getUrl('chat', ['record' => $room]));
                }),
            Tables\Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()->hasRole('super_admin')),
            Tables\Actions\RestoreAction::make()
                ->visible(fn () => auth()->user()->hasRole('super_admin')),
            Tables\Actions\ForceDeleteAction::make()
                ->visible(fn () => auth()->user()->hasRole('super_admin')),
            Action::make('creditWallet')
                ->label('Credit Wallet')
                ->icon('heroicon-o-banknotes')
                ->form([
                    Tables\Forms\Components\TextInput::make('amount')
                        ->label('Amount to credit')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0.01)
                        ->required()
                        ->prefix('₦'),
                    Tables\Forms\Components\TextInput::make('note')
                        ->label('Note')
                        ->maxLength(255)
                        ->placeholder('Optional reason'),
                ])
                ->action(function (User $record, array $data) {
                    try {
                        $amount = (float) ($data['amount'] ?? 0);
                        $result = app(AdministrativeChargeService::class)->applyManualTransaction($record, $amount, 'credit', $data['note'] ?? null);

                        $actualAmount = $result['actual_amount'];
                        $maintenanceCharge = $result['maintenance_charge'];
                        $adminChargeDeducted = $result['admin_charge_deducted'];
                        $newBalance = $result['new_balance'];

                        Notification::make()
                            ->title('Wallet credited successfully')
                            ->body("Principal: ₦" . number_format($amount, 2) .
                                  ($maintenanceCharge > 0 ? ". Maintenance charge of ₦" . number_format($maintenanceCharge, 2) . " deducted." : "") .
                                  ($adminChargeDeducted > 0 ? ". Pending admin charge of ₦" . number_format($adminChargeDeducted, 2) . " was also deducted." : ""))
                            ->success()
                            ->send();

                        DB::afterCommit(function () use ($record, $amount, $actualAmount, $maintenanceCharge, $adminChargeDeducted, $data, $newBalance) {
                            ShariahAudit::log(auth()->user(), 'credit_wallet_manual', [
                                'user_id' => $record->id,
                                'gross_amount' => $amount,
                                'actual_credit' => $actualAmount,
                                'maintenance_charge' => $maintenanceCharge,
                                'admin_charge_deducted' => $adminChargeDeducted,
                                'note' => $data['note'] ?? null,
                                'new_balance' => $newBalance,
                            ]);

                            $msg = "Your wallet has been credited with ₦" . number_format($actualAmount, 2);
                            if ($maintenanceCharge > 0) {
                                $msg .= " after a maintenance charge of ₦" . number_format($maintenanceCharge, 2);
                            }
                            if ($adminChargeDeducted > 0) {
                                $msg .= ". An outstanding administrative charge of ₦" . number_format($adminChargeDeducted, 2) . " was also deducted.";
                            }
                            $msg .= ". New balance: ₦" . number_format($newBalance, 2);
                            if (!empty($data['note'])) {
                                $msg .= ". Note: " . $data['note'];
                            }

                            $record->notifyMember(
                                'Wallet Credited',
                                $msg,
                                [
                                    'type' => 'wallet_credit',
                                    'gross_amount' => (float) $amount,
                                    'actual_amount' => (float) $actualAmount,
                                    'maintenance_charge' => (float) $maintenanceCharge,
                                    'admin_charge_deducted' => (float) $adminChargeDeducted,
                                    'balance' => (float) $newBalance,
                                    'note' => $data['note'] ?? null,
                                ]
                            );
                        });
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Action failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->color('success')
                ->requiresConfirmation(),
            Action::make('debitWallet')
                ->label('Debit Wallet')
                ->icon('heroicon-o-minus-circle')
                ->form([
                    Tables\Forms\Components\TextInput::make('amount')
                        ->label('Amount to debit')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0.01)
                        ->required()
                        ->prefix('₦'),
                    Tables\Forms\Components\TextInput::make('note')
                        ->label('Note')
                        ->maxLength(255)
                        ->placeholder('Reason for manual debit'),
                ])
                ->action(function (User $record, array $data) {
                    try {
                        $amount = (float) ($data['amount'] ?? 0);
                        $result = app(AdministrativeChargeService::class)->applyManualTransaction($record, $amount, 'debit', $data['note'] ?? null);

                        $actualAmount = $result['actual_amount'];
                        $maintenanceCharge = $result['maintenance_charge'];
                        $adminChargeDeducted = $result['admin_charge_deducted'];
                        $newBalance = $result['new_balance'];

                        Notification::make()
                            ->title('Wallet debited successfully')
                            ->body("Principal: ₦" . number_format($amount, 2) .
                                  ($maintenanceCharge > 0 ? ". Maintenance charge of ₦" . number_format($maintenanceCharge, 2) . " added to debit." : "") .
                                  ($adminChargeDeducted > 0 ? ". Pending admin charge of ₦" . number_format($adminChargeDeducted, 2) . " was also deducted." : ""))
                            ->success()
                            ->send();

                        DB::afterCommit(function () use ($record, $amount, $actualAmount, $maintenanceCharge, $adminChargeDeducted, $data, $newBalance) {
                            ShariahAudit::log(auth()->user(), 'debit_wallet_manual', [
                                'user_id' => $record->id,
                                'gross_amount' => $amount,
                                'actual_debit' => $actualAmount,
                                'maintenance_charge' => $maintenanceCharge,
                                'admin_charge_deducted' => $adminChargeDeducted,
                                'note' => $data['note'] ?? null,
                                'new_balance' => $newBalance,
                            ]);

                            $msg = "Your wallet has been debited by ₦" . number_format($actualAmount, 2);
                            if ($maintenanceCharge > 0) {
                                $msg .= " (includes ₦" . number_format($maintenanceCharge, 2) . " maintenance charge)";
                            }
                            if ($adminChargeDeducted > 0) {
                                $msg .= ". An outstanding administrative charge of ₦" . number_format($adminChargeDeducted, 2) . " was also deducted.";
                            }
                            $msg .= ". New balance: ₦" . number_format($newBalance, 2);
                            if (!empty($data['note'])) {
                                $msg .= ". Note: " . $data['note'];
                            }

                            $record->notifyMember(
                                'Wallet Debited',
                                $msg,
                                [
                                    'type' => 'wallet_debit',
                                    'gross_amount' => (float) $amount,
                                    'actual_amount' => (float) $actualAmount,
                                    'maintenance_charge' => (float) $maintenanceCharge,
                                    'admin_charge_deducted' => (float) $adminChargeDeducted,
                                    'balance' => (float) $newBalance,
                                    'note' => $data['note'] ?? null,
                                ]
                            );
                        });
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Action failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->color('danger')
                ->requiresConfirmation(),
            Action::make('debitScheme')
                ->label('Debit Scheme')
                ->icon('heroicon-o-minus-circle')
                ->form([
                    Tables\Forms\Components\Select::make('scheme_id')
                        ->label('Scheme')
                        ->options(Scheme::getSortedOptions(activeOnly: true))
                        ->required()
                        ->searchable(),
                    Tables\Forms\Components\TextInput::make('amount')
                        ->label('Amount to debit')
                        ->numeric()
                        ->minValue(0.01)
                        ->required()
                        ->prefix('₦'),
                    Tables\Forms\Components\Select::make('reason')
                        ->label('Reason')
                        ->options([
                            'loan_repayment' => 'Loan Repayment',
                            'rule_violation' => 'Cooperative Rule Violation',
                            'other' => 'Other',
                        ])
                        ->required()
                        ->live(),
                    Tables\Forms\Components\Select::make('qard_hasan_id')
                        ->label('Select Loan')
                        ->options(fn (User $record) => $record->qardHasans()
                            ->whereIn('status', ['active', 'defaulted'])
                            ->get()
                            ->mapWithKeys(fn ($loan) => [$loan->id => "{$loan->qard_id_string} (Bal: ₦" . number_format($loan->principal_amount - $loan->paid_amount, 2) . ")"]))
                        ->visible(fn (callable $get) => $get('reason') === 'loan_repayment')
                        ->required(fn (callable $get) => $get('reason') === 'loan_repayment'),
                    Tables\Forms\Components\Textarea::make('note')
                        ->label('Note')
                        ->maxLength(255)
                        ->required()
                        ->placeholder('Enter detailed reason for this debit'),
                ])
                ->action(function (User $record, array $data) {
                    $scheme = Scheme::find($data['scheme_id']);
                    $amount = (float) $data['amount'];
                    $reason = $data['reason'];
                    $note = $data['note'];

                    $columnMap = [
                        'Savings' => 'ordinary_savings',
                        'Ordinary Savings' => 'ordinary_savings',
                        'Shares' => 'shares_capital',
                        'Share Capital' => 'shares_capital',
                        'Development' => 'development_fund_balance',
                        'Building' => 'building_balance',
                        'AGM' => 'agm_balance',
                        'Loan Repayment' => 'loan_repayment_balance',
                        'Fine' => 'fine_balance',
                        'Welfare' => 'welfare_balance',
                        'Lateness' => 'lateness_balance',
                        'Stationery' => 'stationery_balance',
                        'Loan Form' => 'loan_form_balance',
                        'Others' => 'others_balance',
                        'ID Card' => 'id_card_balance',
                        'Emergency' => 'emergency_balance',
                        'Entrance' => 'entrance_balance',
                        'H Savings' => 'h_savings_balance',
                        'Investment' => 'investment_balance',
                        'Group Savings' => 'group_savings_balance',
                        'Special Savings' => 'special_savings_balance',
                        'Takaful' => 'takaful_balance',
                        'Digital Gold' => 'gold_balance',
                    ];

                    $currentBalance = 0;
                    if (isset($columnMap[$scheme->name])) {
                        $column = $columnMap[$scheme->name];
                        $currentBalance = (float) $record->$column;
                    } else {
                        $currentBalance = (float) $record->contributions()
                            ->where('scheme_id', $scheme->id)
                            ->where('status', 'success')
                            ->sum('amount');
                    }

                    if ($currentBalance < $amount) {
                        Notification::make()
                            ->title('Insufficient scheme balance')
                            ->body("The user only has ₦" . number_format($currentBalance, 2) . " in their {$scheme->name} scheme.")
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        DB::transaction(function () use ($record, $scheme, $amount, $reason, $data, $note) {
                            $contribution = Contribution::create([
                                'user_id' => $record->id,
                                'scheme_id' => $scheme->id,
                                'amount' => -$amount,
                                'reference' => 'DEBIT-' . strtoupper($reason) . '-' . time(),
                                'status' => 'success',
                                'category' => $reason === 'loan_repayment' ? 'loan_repayment' : 'debit',
                                'note' => $note,
                                'qard_hasan_id' => $reason === 'loan_repayment' ? $data['qard_hasan_id'] : null,
                                'paid_at' => now(),
                            ]);

                            $record->syncSchemeBalance($scheme->name);

                            if ($reason === 'loan_repayment') {
                                $loan = QardHasan::find($data['qard_hasan_id']);
                                QardHasanRepayment::create([
                                    'qard_hasan_id' => $loan->id,
                                    'amount' => $amount,
                                    'payment_method' => 'scheme_debit',
                                    'reference' => 'SCHEME-DEBIT-' . $contribution->id,
                                    'status' => 'success',
                                    'paid_at' => now(),
                                    'notes' => 'Settled via scheme debit: ' . $note,
                                ]);

                                $loan->paid_amount = (float) $loan->paid_amount + $amount;
                                if ($loan->paid_amount >= $loan->principal_amount) {
                                    $loan->status = 'completed';
                                }
                                $loan->save();
                            }

                            ShariahAudit::log(auth()->user(), 'scheme_debit', [
                                'user_id' => $record->id,
                                'scheme_id' => $scheme->id,
                                'scheme_name' => $scheme->name,
                                'amount' => $amount,
                                'reason' => $reason,
                                'note' => $note,
                                'contribution_id' => $contribution->id,
                            ]);

                            $msg = "Your {$scheme->name} has been debited with ₦" . number_format($amount, 2) . " for " . str_replace('_', ' ', $reason) . ".";
                            if (!empty($note)) {
                                $msg .= " Note: " . $note;
                            }

                            $record->notifyMember(
                                'Scheme Debited',
                                $msg,
                                [
                                    'type' => 'scheme_debit',
                                    'scheme' => $scheme->name,
                                    'amount' => $amount,
                                    'reason' => $reason,
                                    'note' => $note,
                                ]
                            );
                        });

                        Notification::make()
                            ->title('Scheme debited successfully')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Action failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->color('danger')
                ->requiresConfirmation(),
            Action::make('markDeceased')
                ->label('Mark Deceased')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->form([
                    Tables\Forms\Components\DateTimePicker::make('date')->label('Date')->native(false)->seconds(false),
                ])
                ->requiresConfirmation()
                ->action(function (User $record, array $data) {
                    $date = $data['date'] ?? null;
                    $record->deceased_at = $date ?: now();
                    $record->save();

                    ShariahAudit::log(auth()->user(), 'mark_member_deceased', [
                        'user_id' => $record->id,
                        'deceased_at' => $record->deceased_at,
                    ]);

                    $svc = app(TakafulService::class);
                    $summary = $svc->settleMemberLoans($record, 'deceased');
                    Notification::make()
                        ->title('Member marked deceased; settlement attempted')
                        ->body('Total settled: ₦'.number_format((float) ($summary['total_settled'] ?? 0), 2).'. Pool after: ₦'.number_format((float) ($summary['pool_after'] ?? 0), 2))
                        ->success()
                        ->send();
                }),
            Action::make('markMajorLoss')
                ->label('Mark Major Loss')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->form([
                    Tables\Forms\Components\DateTimePicker::make('date')->label('Date')->native(false)->seconds(false),
                ])
                ->requiresConfirmation()
                ->action(function (User $record, array $data) {
                    $date = $data['date'] ?? null;
                    $record->major_loss_at = $date ?: now();
                    $record->save();

                    ShariahAudit::log(auth()->user(), 'mark_member_major_loss', [
                        'user_id' => $record->id,
                        'major_loss_at' => $record->major_loss_at,
                    ]);

                    $svc = app(TakafulService::class);
                    $summary = $svc->settleMemberLoans($record, 'major_loss');
                    Notification::make()
                        ->title('Member marked major loss; settlement attempted')
                        ->body('Total settled: ₦'.number_format((float) ($summary['total_settled'] ?? 0), 2).'. Pool after: ₦'.number_format((float) ($summary['pool_after'] ?? 0), 2))
                        ->success()
                        ->send();
                }),
            Action::make('viewPassbook')
                ->label('View Passbook')
                ->icon('heroicon-o-eye')
                ->color('success')
                ->form([
                    Tables\Forms\Components\Select::make('year')
                        ->options(array_combine(range(now()->year, now()->year - 5), range(now()->year, now()->year - 5)))
                        ->default(now()->year)
                        ->required(),
                ])
                ->url(fn (User $record, array $data) => route('admin.view.passbook', ['user' => $record->id, 'year' => $data['year'] ?? now()->year]))
                ->openUrlInNewTab(),
            Action::make('printPassbook')
                ->label('Print Passbook')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->form([
                    Tables\Forms\Components\Select::make('year')
                        ->options(array_combine(range(now()->year, now()->year - 5), range(now()->year, now()->year - 5)))
                        ->default(now()->year)
                        ->required(),
                ])
                ->url(fn (User $record, array $data) => route('admin.print.passbook', ['user' => $record->id, 'year' => $data['year'] ?? now()->year]))
                ->openUrlInNewTab(),
            Action::make('downloadEnrolmentForm')
                ->label('Download Enrolment Form')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->action(fn (User $record) => response()->streamDownload(function () use ($record) {
                    echo Pdf::loadView('pdfs.membership_application', ['application' => $record])->output();
                }, "enrolment-form-{$record->id}.pdf")),
            Action::make('downloadImamAttestation')
                ->label('Download Imam Attestation')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(fn (User $record) => response()->streamDownload(function () use ($record) {
                    echo Pdf::loadView('pdfs.imam_attestation', ['application' => $record])->output();
                }, "imam-attestation-{$record->id}.pdf")),
            Action::make('downloadGuarantorTestimony')
                ->label('Download Guarantor Testimony')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->visible(fn (User $record) => $record->guarantor_signature_path !== null)
                ->action(fn (User $record) => response()->streamDownload(function () use ($record) {
                    echo Pdf::loadView('pdfs.guarantor_testimony', ['application' => $record])->output();
                }, "guarantor-testimony-{$record->id}.pdf")),
            Action::make('reset2fa')
                ->label('Reset 2FA')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (User $record) => $record->hasEnabledTwoFactor())
                ->requiresConfirmation()
                ->action(function (User $record) {
                    $record->disableTwoFactorAuthentication();

                    ShariahAudit::log(auth()->user(), 'reset_user_2fa', [
                        'user_id' => $record->id,
                        'email' => $record->email,
                    ]);

                    Notification::make()
                        ->title('2FA Reset Successfully')
                        ->success()
                        ->send();
                }),
            Action::make('markAsNeedy')
                ->label('Mark as Zakat Eligible')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (User $record) => !$record->badges()->where('badge_type', 'zakat_needy')->exists())
                ->action(function (User $record) {
                    $record->badges()->create([
                        'badge_type' => 'zakat_needy',
                        'name' => 'Zakat Eligible (Needy)',
                        'description' => 'This member has been verified as eligible for Zakat distribution within the cooperative.',
                        'earned_at' => now(),
                    ]);

                    ShariahAudit::log(auth()->user(), 'user_marked_zakat_needy', [
                        'user_id' => $record->id,
                    ]);

                    Notification::make()
                        ->title('Member marked as Zakat eligible')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
            Action::make('unmarkAsNeedy')
                ->label('Remove Zakat Eligibility')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (User $record) => $record->badges()->where('badge_type', 'zakat_needy')->exists())
                ->action(function (User $record) {
                    $record->badges()->where('badge_type', 'zakat_needy')->delete();

                    ShariahAudit::log(auth()->user(), 'user_unmarked_zakat_needy', [
                        'user_id' => $record->id,
                    ]);

                    Notification::make()
                        ->title('Zakat eligibility removed')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
            Action::make('verifyKyc')
                ->label('Verify KYC')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->visible(fn (User $record) => $record->bvn_verified_at === null)
                ->form([
                    Tables\Forms\Components\TextInput::make('bvn')
                        ->label('BVN')
                        ->length(11)
                        ->numeric()
                        ->password()
                        ->revealable(fn () => auth()->user()->hasRole('super_admin'))
                        ->default(fn (User $record) => $record->bvn),
                ])
                ->action(function (User $record, array $data) {
                    $record->bvn = $data['bvn'];
                    $record->bvn_verified_at = now();
                    $record->save();

                    ShariahAudit::log(auth()->user(), 'manual_kyc_verify', [
                        'user_id' => $record->id,
                        'bvn' => $record->bvn,
                    ]);

                    Notification::make()
                        ->title('KYC Verified')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
            Action::make('chargeFine')
                ->label('Charge Manual Fine')
                ->icon('heroicon-o-plus-circle')
                ->color('warning')
                ->form([
                    Tables\Forms\Components\TextInput::make('amount')
                        ->label('Fine Amount')
                        ->numeric()
                        ->prefix('₦')
                        ->required(),
                    Tables\Forms\Components\TextInput::make('note')
                        ->label('Reason')
                        ->required()
                        ->placeholder('e.g. Conduct unbecoming'),
                ])
                ->action(function (User $record, array $data) {
                    $record->increment('outstanding_fines', (float) $data['amount']);

                    ShariahAudit::log(auth()->user(), 'manual_fine_charged', [
                        'user_id' => $record->id,
                        'amount' => (float) $data['amount'],
                        'reason' => $data['note'],
                    ]);

                    Notification::make()
                        ->title('Fine charged successfully')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
            Action::make('payFines')
                ->label('Record Fine Payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (User $record) => (float)$record->outstanding_fines > 0)
                ->form([
                    Tables\Forms\Components\TextInput::make('amount')
                        ->label('Amount Paid')
                        ->numeric()
                        ->prefix('₦')
                        ->default(fn (User $record) => (float)$record->outstanding_fines)
                        ->required(),
                    Tables\Forms\Components\TextInput::make('note')
                        ->label('Note')
                        ->placeholder('e.g. Paid in cash at the office'),
                ])
                ->action(function (User $record, array $data) {
                    DB::transaction(function () use ($record, $data) {
                        $amount = (float) $data['amount'];

                        Contribution::create([
                            'user_id' => $record->id,
                            'amount' => $amount,
                            'category' => 'fine',
                            'status' => 'success',
                            'reference' => 'MANUAL_FINE_' . Str::random(8),
                            'paid_at' => now(),
                        ]);

                        ShariahAudit::log(auth()->user(), 'manual_fine_payment_recorded', [
                            'user_id' => $record->id,
                            'amount' => $amount,
                            'note' => $data['note'] ?? '',
                        ]);
                    });

                    Notification::make()
                        ->title('Fine payment recorded')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
            Action::make('waiveFines')
                ->label('Waive All Fines')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (User $record) => (float)$record->outstanding_fines > 0)
                ->action(function (User $record) {
                    app(AttendanceService::class)->waiveAllFines($record);

                    ShariahAudit::log(auth()->user(), 'manual_fine_waiver', [
                        'user_id' => $record->id,
                        'waived_amount' => (float) $record->getOriginal('outstanding_fines'),
                    ]);

                    Notification::make()
                        ->title('Fines waived successfully')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
            Action::make('settleAdminCharge')
                ->label('Settle Admin Charge')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (User $record) => (float)$record->admin_charge_balance > 0)
                ->form([
                    Tables\Forms\Components\TextInput::make('amount')
                        ->label('Amount to Settle')
                        ->numeric()
                        ->prefix('₦')
                        ->default(fn (User $record) => (float)$record->admin_charge_balance)
                        ->required(),
                    Tables\Forms\Components\Placeholder::make('wallet_balance')
                        ->label('Available Wallet Balance')
                        ->content(fn (User $record) => '₦' . number_format($record->balance, 2)),
                ])
                ->action(function (User $record, array $data) {
                    try {
                        $service = app(AdministrativeChargeService::class);
                        $service->settleAdminChargeManually($record, (float)$data['amount']);

                        Notification::make()
                            ->title('Administrative charge settled')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation(),
            Action::make('clearPaystackDVA')
                ->label('Clear Paystack DVA')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clear Paystack Virtual Account')
                ->modalDescription('Are you sure you want to clear the Paystack virtual account and all related records (including Autosave) for this user?')
                ->visible(fn (User $record) => $record->virtualAccount?->paystack_customer_code !== null || $record->virtualAccount?->dva_account_number !== null || $record->autosave_enabled)
                ->action(function (User $record) {
                    // Clear User fields
                    $userUpdate = ['autosave_enabled' => false];
                    foreach (['paystack_customer_code', 'paystack_authorization_code', 'dva_account_number', 'dva_bank_name', 'dva_account_name'] as $col) {
                        if (\Illuminate\Support\Facades\Schema::hasColumn('users', $col)) {
                            $userUpdate[$col] = null;
                        }
                    }
                    $record->update($userUpdate);

                    // Clear Virtual Account fields
                    if ($record->virtualAccount) {
                        $record->virtualAccount->update([
                            'paystack_customer_code' => null,
                            'paystack_authorization_code' => null,
                            'dva_account_number' => null,
                            'dva_bank_name' => null,
                            'dva_account_name' => null,
                            'dva_verification_meta' => null,
                        ]);
                    }

                    ShariahAudit::log(auth()->user(), 'paystack_dva_cleared', [
                        'user_id' => $record->id,
                        'details' => 'Paystack record and autosave cleared',
                    ]);

                    Notification::make()
                        ->title('Paystack record cleared')
                        ->success()
                        ->send();
                }),
        ];
    }
}
