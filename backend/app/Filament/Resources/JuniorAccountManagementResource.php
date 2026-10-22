<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JuniorAccountManagementResource\Pages;
use App\Models\JuniorAccount;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JuniorAccountManagementResource extends Resource
{
    protected static ?string $model = JuniorAccount::class;

    protected static ?string $navigationGroup = 'Core Cooperative';

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Junior Accounts';

    protected static ?string $slug = 'junior-account-management';

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_junior_account');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_junior_account');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('update_junior_account');
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('delete_junior_account');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Junior Account Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Parent/Guardian')
                            ->relationship('parent', 'name')
                            ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name} {$record->surname} ({$record->member_id})")
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('child_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('child_dob')
                            ->label('Child Date of Birth')
                            ->required(),
                        Forms\Components\TextInput::make('balance')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DatePicker::make('locked_until')
                            ->label('Withdrawal Locked Until'),
                        Forms\Components\TextInput::make('purpose')
                            ->required()
                            ->maxLength(255)
                            ->default('Education'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('child_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->formatStateUsing(fn ($state, JuniorAccount $record) => $record->parent ? "{$record->parent->name} {$record->parent->surname}" : 'N/A')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance')
                    ->money('ngn', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('locked_until')
                    ->date()
                    ->sortable()
                    ->color(fn ($state) => $state && $state->isFuture() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('purpose'),
            ])
            ->filters([
                Tables\Filters\Filter::make('locked')
                    ->query(fn (Builder $query): Builder => $query->where('locked_until', '>', now())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJuniorAccounts::route('/'),
            'create' => Pages\CreateJuniorAccount::route('/create'),
            'edit' => Pages\EditJuniorAccount::route('/{record}/edit'),
        ];
    }
}
