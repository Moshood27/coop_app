<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LedgerAccountResource\Pages;
use App\Models\LedgerAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class LedgerAccountResource extends Resource
{
    protected static ?string $model = LedgerAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Ledger Accounts';
    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->required()
                    ->options([
                        'asset' => 'Asset',
                        'liability' => 'Liability',
                        'equity' => 'Equity',
                        'income' => 'Income',
                        'expense' => 'Expense',
                    ]),
                Forms\Components\Select::make('parent_id')
                    ->label('Parent Account')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->placeholder('None'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->sortable()->searchable(),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('parent.name')->label('Parent')->toggleable(),
                TextColumn::make('balance')
                    ->money('ngn', true)
                    ->label('Balance'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'asset' => 'Asset',
                        'liability' => 'Liability',
                        'equity' => 'Equity',
                        'income' => 'Income',
                        'expense' => 'Expense',
                    ]),
            ])
            ->headerActions([
                Action::make('rebuild_monthly_balances')
                    ->label('Rebuild Monthly Balances')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From (YYYY-MM-DD)'),
                        Forms\Components\DatePicker::make('to')->label('To (YYYY-MM-DD)'),
                        Forms\Components\TextInput::make('branch_id')->numeric()->label('Branch ID')->placeholder('All branches'),
                        Forms\Components\Toggle::make('truncate')->label('Clear Existing for Months')->default(false),
                    ])
                    ->action(function (array $data) {
                        try {
                            if (!Schema::hasTable('ledger_account_monthly_balances')) {
                                Notification::make('mb_missing')->title('Migrations pending')->warning()->body('monthly balances table missing')->send();
                                return;
                            }
                            Artisan::call('accounting:rebuild-monthly-balances', array_filter([
                                '--from' => $data['from'] ?? null,
                                '--to' => $data['to'] ?? null,
                                '--branch' => $data['branch_id'] ?? null,
                                '--truncate' => ($data['truncate'] ?? false) ? true : null,
                            ]));
                            Notification::make('mb_ok')->title('Monthly Balances Rebuilt')->success()->body(trim(Artisan::output()))->send();
                        } catch (\Throwable $e) {
                            Notification::make('mb_err')->title('Rebuild Failed')->danger()->body($e->getMessage())->send();
                        }
                    }),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLedgerAccounts::route('/'),
            'create' => Pages\CreateLedgerAccount::route('/create'),
            'edit' => Pages\EditLedgerAccount::route('/{record}/edit'),
        ];
    }
}
