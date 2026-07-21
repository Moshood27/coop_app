<?php

namespace App\Filament\Widgets;

use App\Models\QardHasan;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentPayouts extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return 'Recent Payouts';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                QardHasan::query()
                    ->whereIn('status', ['active', 'defaulted', 'completed'])
                    ->latest('updated_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('updated_at')
                    ->label('Disbursed')
                    ->since(),
                TextColumn::make('user.full_name')
                    ->label('Member')
                    ->description(fn ($record) => $record->user?->membership_number)
                    ->searchable(['surname', 'name', 'other_names', 'membership_number']),
                TextColumn::make('qard_id_string')
                    ->label('Loan ID')
                    ->searchable(),
                TextColumn::make('principal_amount')
                    ->label('Principal')
                    ->money('ngn', true)
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('ngn', true)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'completed' => 'info',
                        'defaulted' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn ($state) => match ($state) {
                        'active' => 'heroicon-m-check-circle',
                        'completed' => 'heroicon-m-check-badge',
                        'defaulted' => 'heroicon-m-exclamation-triangle',
                        default => 'heroicon-m-clock',
                    }),
            ])
            ->paginated(false);
    }
}
