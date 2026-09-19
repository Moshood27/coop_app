<?php

namespace App\Filament\Resources\BankReconciliationResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'matches';

    public function canCreate(): bool
    {
        // Matches are typically created by the reconciliation service; keep read-only in UI
        return false;
    }

    public function form(Form $form): Form
    {
        return $form; // Not used (read-only)
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line.txn_date')->date()->label('Date')->sortable(),
                TextColumn::make('line.description')->label('Statement Line')->limit(40)->searchable(),
                TextColumn::make('journal.number')->label('Journal #')->toggleable(),
                TextColumn::make('match_amount')->money('ngn', true)->label('Amount')->sortable(),
                Tables\Columns\BadgeColumn::make('method')
                    ->colors([
                        'info' => 'auto',
                        'secondary' => 'manual',
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
