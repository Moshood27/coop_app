<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\ContributionResource\Pages;
use App\Models\Contribution;
use App\Models\Project;
use App\Models\Scheme;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static ?string $navigationGroup = 'Finance & Treasury';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Member Selection')
                    ->description('Select the member making the contribution.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Member')
                            ->relationship('user', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->full_name} ({$record->membership_number})")
                            ->searchable(['surname', 'name', 'other_names', 'membership_number'])
                            ->preload()
                            ->required()
                            ->reactive()
                            ->columnSpanFull()
                            ->helperText(function (Forms\Get $get) {
                                $userId = $get('user_id');
                                if (!$userId) return null;
                                $user = \App\Models\User::find($userId);
                                if ($user && !$user->hasActiveLoan()) {
                                    $loanUrl = \App\Filament\Resources\QardHasanResource::getUrl('index', ['tableFilters[user_id][value]' => $userId]);
                                    return new \Illuminate\Support\HtmlString("<span class=\"text-danger-600 font-bold\">⚠️ This member has no active or defaulted loan record. If you are entering a loan repayment, please ensure a loan record exists for this user.</span><br/><a href=\"{$loanUrl}\" target=\"_blank\" class=\"text-primary-600 underline text-sm\">Check/Create Loan Record</a>");
                                }
                                return null;
                            }),
                    ]),

                Forms\Components\Section::make('Contribution Details')
                    ->description('Specify the schemes and amounts.')
                    ->icon('heroicon-o-currency-dollar')
                    ->visibleOn('create')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Contribution Items')
                            ->required()
                            ->minItems(1)
                            ->schema([
                                Forms\Components\Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        Forms\Components\Select::make('scheme_id')
                                            ->label('Scheme')
                                            ->options(Scheme::query()->pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->reactive()
                                            ->rule('distinct')
                                            ->extraInputAttributes(['style' => 'font-size: 1.1rem;'])
                                            ->helperText(function (Forms\Get $get) {
                                                $schemeId = $get('scheme_id');
                                                $userId = $get('../../user_id');
                                                if (!$schemeId || !$userId) return null;

                                                $scheme = \App\Models\Scheme::find($schemeId);
                                                if ($scheme && $scheme->name === 'Loan Repayment') {
                                                    $user = \App\Models\User::find($userId);
                                                    if ($user && !$user->hasActiveLoan()) {
                                                        $loanUrl = \App\Filament\Resources\QardHasanResource::getUrl('index', ['tableFilters[user_id][value]' => $userId]);
                                                        return new \Illuminate\Support\HtmlString("<span class=\"text-danger-600 font-bold\">⚠️ Warning: \"Loan Repayment\" selected but no active/defaulted loan record found for this member. This record will not be automatically deducted from any loan.</span><br/><a href=\"{$loanUrl}\" target=\"_blank\" class=\"text-primary-600 underline text-sm\">Manage Loans</a>");
                                                    }
                                                }
                                                return null;
                                            }),
                                        Forms\Components\TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->minValue(0.01)
                                            ->prefix('₦')
                                            ->required()
                                            ->extraInputAttributes(['style' => 'font-size: 1.1rem; font-weight: bold;']),
                                    ]),
                                Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                    ->schema([
                                        Forms\Components\Select::make('qard_hasan_id')
                                            ->label('Link to Loan (Optional)')
                                            ->options(function (Forms\Get $get) {
                                                $userId = $get('../../user_id');
                                                if (!$userId) return [];
                                                return \App\Models\QardHasan::where('user_id', $userId)
                                                    ->whereIn('status', ['active', 'defaulted'])
                                                    ->get()
                                                    ->mapWithKeys(fn($loan) => [$loan->id => "Loan #{$loan->id} (Rem: ₦".number_format($loan->principal_amount - $loan->paid_amount, 2).")"]);
                                            })
                                            ->searchable()
                                            ->visible(function (Forms\Get $get) {
                                                $userId = $get('../../user_id');
                                                if (!$userId) return false;
                                                return \App\Models\QardHasan::where('user_id', $userId)
                                                    ->whereIn('status', ['active', 'defaulted'])
                                                    ->exists();
                                            })
                                            ->helperText('Select a loan to deduct this payment from')
                                            ->nullable(),
                                        Forms\Components\Select::make('project_id')
                                            ->label('Project (optional)')
                                            ->options(Project::query()->where('active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->native(false)
                                            ->helperText('Link to a pooled project'),
                                        Forms\Components\Select::make('savings_group_id')
                                            ->label('Savings Group (optional)')
                                            ->relationship('savingsGroup', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Link to a savings group'),
                                    ]),
                            ])
                            ->itemLabel(fn (array $state): ?string => ($state['scheme_id'] ?? null)
                                ? Scheme::find($state['scheme_id'])?->name . ' - ₦' . number_format($state['amount'] ?? 0, 2)
                                : null)
                            ->collapsible()
                            ->cloneable()
                            ->defaultItems(1)
                            ->grid(1),
                    ]),

                // For edit: keep single scheme and amount fields
                Forms\Components\Section::make('Contribution Details')
                    ->description('Edit contribution record.')
                    ->icon('heroicon-o-pencil-square')
                    ->hiddenOn('create')
                    ->schema([
                        Forms\Components\Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\Select::make('scheme_id')
                                    ->label('Scheme')
                                    ->options(Scheme::withTrashed()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->extraInputAttributes(['style' => 'font-size: 1.1rem;'])
                                    ->helperText(function (Forms\Get $get) {
                                        $schemeId = $get('scheme_id');
                                        $userId = $get('user_id');
                                        if (!$schemeId || !$userId) return null;

                                        $scheme = \App\Models\Scheme::find($schemeId);
                                        if ($scheme && $scheme->name === 'Loan Repayment') {
                                            $user = \App\Models\User::find($userId);
                                            if ($user && !$user->hasActiveLoan()) {
                                                $loanUrl = \App\Filament\Resources\QardHasanResource::getUrl('index', ['tableFilters[user_id][value]' => $userId]);
                                                return new \Illuminate\Support\HtmlString("<span class=\"text-danger-600 font-bold\">⚠️ Warning: \"Loan Repayment\" selected but no active/defaulted loan record found for this member. This record will not be automatically deducted from any loan.</span><br/><a href=\"{$loanUrl}\" target=\"_blank\" class=\"text-primary-600 underline text-sm\">Manage Loans</a>");
                                            }
                                        }
                                        return null;
                                    }),
                                Forms\Components\TextInput::make('amount')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->required()
                                    ->extraInputAttributes(['style' => 'font-size: 1.1rem; font-weight: bold;']),
                            ]),
                        Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                Forms\Components\Select::make('qard_hasan_id')
                                    ->label('Link to Loan (Optional)')
                                    ->options(function (Forms\Get $get, Contribution $record) {
                                        $userId = $record->user_id;
                                        if (!$userId) return [];
                                        return \App\Models\QardHasan::where('user_id', $userId)
                                            ->whereIn('status', ['active', 'defaulted'])
                                            ->get()
                                            ->mapWithKeys(fn($loan) => [$loan->id => "Loan #{$loan->id} (Rem: ₦".number_format($loan->principal_amount - $loan->paid_amount, 2).")"]);
                                    })
                                    ->searchable()
                                    ->visible(function (Forms\Get $get, Contribution $record) {
                                        $userId = $record->user_id;
                                        if (!$userId) return false;
                                        return \App\Models\QardHasan::where('user_id', $userId)
                                            ->whereIn('status', ['active', 'defaulted'])
                                            ->exists();
                                    })
                                    ->helperText('Select a loan to deduct this payment from')
                                    ->nullable(),
                                Forms\Components\Select::make('project_id')
                                    ->label('Project (optional)')
                                    ->options(Project::query()->where('active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->native(false),
                                Forms\Components\Select::make('savings_group_id')
                                    ->label('Savings Group (optional)')
                                    ->relationship('savingsGroup', 'name')
                                    ->searchable()
                                    ->preload(),
                            ]),
                    ]),

                Forms\Components\Section::make('Status & Reference')
                    ->description('Manage the status and reference of this contribution.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'success' => 'Success',
                                        'failed' => 'Failed',
                                    ])
                                    ->required()
                                    ->native(false),
                                Forms\Components\TextInput::make('reference')
                                    ->maxLength(255)
                                    ->helperText('Leave empty to auto-generate')
                                    ->hiddenOn('create'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Time')->since()->sortable(),
                TextColumn::make('user.full_name')
                    ->label('Member')
                    ->searchable(['surname', 'name', 'other_names']),
                TextColumn::make('user.membership_number')
                    ->label('Member #')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(function ($state) {
                        if (auth()->user()->hasRole('super_admin')) {
                            return $state;
                        }

                        return Str::mask($state, '*', 2, -2);
                    }),
                TextColumn::make('scheme.name')->label('Scheme')->searchable(),
                TextColumn::make('qard_hasan_id')
                    ->label('Linked Loan')
                    ->formatStateUsing(fn ($state) => $state ? "Loan #{$state}" : '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('project.name')->label('Project')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('savingsGroup.name')->label('Savings Group')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')->money('ngn', true)->sortable(),
                TextColumn::make('units')
                    ->label(fn ($record) => ($record && $record->scheme && $record->scheme->name === 'Digital Gold') ? 'Grams' : 'Units')
                    ->numeric(6)
                    ->toggleable(),
                TextColumn::make('status')->badge()->colors([
                    'success' => ['success', 'paid', 'completed'],
                    'warning' => ['pending', 'processing'],
                    'danger' => ['failed', 'cancelled', 'rejected'],
                    'gray' => [''],
                ]),
                TextColumn::make('reference')->label('Ref')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('scheme_id')
                    ->label('Scheme')
                    ->options(Scheme::withTrashed()->pluck('name', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'paid' => 'Paid',
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('print')
                    ->label('Print List')
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->extraAttributes(['onclick' => 'window.print()']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')), // Only visible to Super Admin
                Tables\Actions\Action::make('printReceipt')
                    ->label('Print Receipt')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (Contribution $record) => route('admin.print.contribution-receipt', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Contribution $record) => $record->status === 'success'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasRole('super_admin')), // Only visible to Super Admin
                ]),
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_contribution');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_contribution');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('update_contribution');
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('delete_contribution');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        // If the user is a Super Admin, let them see everything
        if ($user->hasRole('super_admin')) {
            return parent::getEloquentQuery();
        }

        // Otherwise, only show records belonging to the user's branch
        return parent::getEloquentQuery()->whereHas('user', function ($query) use ($user) {
            $query->where('branch_id', $user->branch_id);
        });
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContributions::route('/'),
            'create' => Pages\CreateContribution::route('/create'),
            'edit' => Pages\EditContribution::route('/{record}/edit'),
        ];
    }
}
