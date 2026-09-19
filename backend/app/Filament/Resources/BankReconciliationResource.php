<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankReconciliationResource\Pages;
use App\Filament\Resources\BankReconciliationResource\RelationManagers\MatchesRelationManager;
use App\Models\BankReconciliation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class BankReconciliationResource extends Resource
{
    protected static ?string $model = BankReconciliation::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Bank Reconciliations';
    protected static ?int $navigationSort = 62;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable((new BankReconciliation())->getTable());
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        try {
            if (! Schema::hasTable((new BankReconciliation())->getTable())) {
                return BankReconciliation::query()->whereRaw('1 = 0');
            }
        } catch (\Throwable $e) {
            return BankReconciliation::query()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery();
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return ($u?->can('bank_reconciliation.start') || $u?->can('bank_reconciliation.finalize')) ?? false;
    }

    public static function canCreate(): bool
    {
        return optional(auth()->user())->can('bank_reconciliation.start') ?? false;
    }

    public static function canEdit($record): bool
    {
        $u = auth()->user();
        return ($u?->can('bank_reconciliation.start') || $u?->can('bank_reconciliation.finalize')) ?? false;
    }

    public static function canDelete($record): bool
    {
        return optional(auth()->user())->can('bank_reconciliation.finalize') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('bank_account_id')
                    ->label('Bank Account')
                    ->relationship('bankAccount', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DatePicker::make('period_from')->required(),
                Forms\Components\DatePicker::make('period_to')->required(),
                Forms\Components\TextInput::make('ending_balance')->numeric()->required(),
                Forms\Components\Select::make('status')->options([
                    'draft' => 'Draft',
                    'in_progress' => 'In Progress',
                    'finalized' => 'Finalized',
                ])->default('draft'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bankAccount.name')->label('Bank Account')->sortable()->searchable(),
                TextColumn::make('period_from')->date()->label('From')->sortable(),
                TextColumn::make('period_to')->date()->label('To')->sortable(),
                TextColumn::make('ending_balance')->money('ngn', true)->label('Ending Bal'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'info' => 'in_progress',
                        'success' => 'finalized',
                    ])->sortable(),
                TextColumn::make('reconciler.name')->label('Reconciled By')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bank_account_id')->label('Bank Account')->relationship('bankAccount', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            MatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankReconciliations::route('/'),
            'create' => Pages\CreateBankReconciliation::route('/create'),
            'edit' => Pages\EditBankReconciliation::route('/{record}/edit'),
        ];
    }
}
