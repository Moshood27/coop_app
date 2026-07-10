<?php

namespace App\Filament\Widgets;

use App\Models\WalletTransaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentWalletActivity extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return 'Recent Wallet Activity';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WalletTransaction::query()->latest()->limit(10)
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->since(),
                TextColumn::make('user.full_name')
                    ->label('Member')
                    ->description(fn ($record) => $record->user?->membership_number)
                    ->searchable(['surname', 'name', 'other_names']),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'credit' => 'heroicon-m-arrow-down-left',
                        'debit' => 'heroicon-m-arrow-up-right',
                        default => 'heroicon-m-minus',
                    }),
                TextColumn::make('amount')
                    ->money('ngn', true),
                TextColumn::make('source')
                    ->label('Source/Reason'),
                TextColumn::make('reference')
                    ->label('Ref')
                    ->searchable(),
            ])
            ->paginated(false);
    }
}
