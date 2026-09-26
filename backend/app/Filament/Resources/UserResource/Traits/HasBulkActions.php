<?php

namespace App\Filament\Resources\UserResource\Traits;

use App\Models\ShariahAudit;
use App\Services\AttendanceService;
use Filament\Tables;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;

trait HasBulkActions
{
    public static function getBulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\BulkAction::make('printForms')
                    ->label('Print Enrolment Forms')
                    ->icon('heroicon-o-printer')
                    ->action(fn (Collection $records) => response()->streamDownload(function () use ($records) {
                        $sortedRecords = $records->sortBy('name', SORT_NATURAL);
                        echo Pdf::loadView('pdfs.bulk_membership_applications', ['users' => $sortedRecords])->setPaper('a4')->output();
                    }, "bulk-enrolment-forms.pdf")),
                Tables\Actions\BulkAction::make('waiveFinesBulk')
                    ->label('Waive Fines')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $service = app(AttendanceService::class);
                        $count = 0;
                        foreach ($records as $record) {
                            if ((float)$record->outstanding_fines > 0) {
                                $service->waiveAllFines($record);

                                ShariahAudit::log(auth()->user(), 'bulk_fine_waiver', [
                                    'user_id' => $record->id,
                                    'waived_amount' => (float) $record->getOriginal('outstanding_fines'),
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title("Fines waived for {$count} members")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\BulkAction::make('clearPaystackDVABulk')
                    ->label('Clear Paystack DVA')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Clear Virtual Accounts')
                    ->modalDescription('Are you sure you want to clear the Paystack virtual accounts and all related records (including Autosave) for the selected users?')
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            // Clear User fields
                            $userUpdate = ['autosave_enabled' => false];
                            foreach (['paystack_customer_code', 'paystack_authorization_code', 'dva_account_number', 'dva_bank_name', 'dva_account_name'] as $col) {
                                if (Schema::hasColumn('users', $col)) {
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

                            ShariahAudit::log(auth()->user(), 'bulk_paystack_dva_cleared', [
                                'user_id' => $record->id,
                                'details' => 'Paystack record and autosave cleared bulk',
                            ]);
                            $count++;
                        }

                        Notification::make()
                            ->title("Paystack records cleared for {$count} members")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
            ]),
        ];
    }
}
