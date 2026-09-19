<?php

namespace App\Filament\Resources\BankStatementResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('txn_date')
                    ->label('Date')
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255),
                Forms\Components\TextInput::make('reference')
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'new' => 'New',
                        'matched' => 'Matched',
                        'ignored' => 'Ignored',
                    ])->default('new'),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('txn_date')->date()->label('Date')->sortable(),
                TextColumn::make('description')->limit(40)->searchable(),
                TextColumn::make('reference')->toggleable(),
                TextColumn::make('amount')->money('ngn', true)->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'new',
                        'success' => 'matched',
                        'warning' => 'ignored',
                    ])->sortable(),
                TextColumn::make('matched_journal_id')->label('Journal #')->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
