<?php

namespace App\Filament\Resources\UserResource\Traits;

use Filament\Tables;
use Illuminate\Support\HtmlString;
use App\Models\User;

trait HasTableColumns
{
    public static function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('surname')
                ->searchable()
                ->sortable()
                ->formatStateUsing(fn ($state, User $record) => new HtmlString("
                    <div class='flex items-center gap-2'>
                        <div class='flex flex-col'>
                            <span class='font-bold text-gray-900 dark:text-gray-100'>{$state} {$record->name}</span>
                            <span class='text-xs text-gray-500'>{$record->membership_number}</span>
                        </div>
                    </div>
                ")),
            Tables\Columns\TextColumn::make('name')
                ->label('First Name')
                ->searchable()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('email')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('phone')
                ->searchable(),
            Tables\Columns\TextColumn::make('branch.name')
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('balance')
                ->label('Wallet')
                ->money('NGN')
                ->sortable(),
            Tables\Columns\TextColumn::make('ordinary_savings')
                ->label('Savings')
                ->money('NGN')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('shares_capital')
                ->label('Shares')
                ->money('NGN')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('loan_repayment_balance')
                ->label('Loan Repay')
                ->money('NGN')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('total_contribution_balance')
                ->label('Total Bal')
                ->money('NGN')
                ->state(function (User $record): float {
                    return (float) $record->ordinary_savings +
                        (float) $record->shares_capital +
                        (float) $record->development_fund_balance +
                        (float) $record->building_balance +
                        (float) $record->agm_balance +
                        (float) $record->loan_repayment_balance +
                        (float) $record->fine_balance +
                        (float) $record->welfare_balance +
                        (float) $record->lateness_balance +
                        (float) $record->stationery_balance +
                        (float) $record->loan_form_balance +
                        (float) $record->others_balance +
                        (float) $record->id_card_balance +
                        (float) $record->emergency_balance +
                        (float) $record->entrance_balance +
                        (float) $record->h_savings_balance +
                        (float) $record->investment_balance +
                        (float) $record->group_savings_balance +
                        (float) $record->special_savings_balance +
                        (float) $record->takaful_balance +
                        (float) $record->gold_balance;
                })
                ->sortable()
                ->toggleable(),
            Tables\Columns\TextColumn::make('outstanding_fines')
                ->label('Fines')
                ->money('NGN')
                ->color('danger')
                ->sortable(),
            Tables\Columns\TextColumn::make('audited_attendance_count')
                ->label('Att.')
                ->badge()
                ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                ->sortable(),
            Tables\Columns\SelectColumn::make('approval_status')
                ->label('Status')
                ->options([
                    'pending' => 'Pending',
                    'recommended' => 'Recommended',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])
                ->selectablePlaceholder(false)
                ->sortable(),
            Tables\Columns\IconColumn::make('is_admin')
                ->label('Admin')
                ->boolean()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('last_activity_at')
                ->label('Last Activity')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
