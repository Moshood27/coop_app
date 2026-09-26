<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Traits\HasUserForm;
use App\Filament\Resources\UserResource\Traits\HasTableColumns;
use App\Filament\Resources\UserResource\Traits\HasTableFilters;
use App\Filament\Resources\UserResource\Traits\HasTableActions;
use App\Filament\Resources\UserResource\Traits\HasBulkActions;
use App\Filament\Resources\UserResource\Traits\HasHeaderActions;
use App\Filament\Resources\UserResource\Traits\HasMemberApprovals;

class UserResource extends Resource
{
    use HasUserForm;
    use HasTableColumns;
    use HasTableFilters;
    use HasTableActions;
    use HasBulkActions;
    use HasHeaderActions;
    use HasMemberApprovals;

    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'Core Cooperative';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Form $form): Form
    {
        return self::getUserForm($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderBy('surname', 'asc'))
            ->columns(self::getTableColumns())
            ->filters(self::getTableFilters())
            ->headerActions(self::getHeaderActions())
            ->actions([
                ...self::getTableActions(),
                ...self::getApprovalActions(),
            ])
            ->bulkActions(self::getBulkActions());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'branch_admin', 'officer']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'branch_admin']);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        return $query->where('branch_id', $user->branch_id);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContributionsRelationManager::class,
            RelationManagers\QardHasansRelationManager::class,
            RelationManagers\QardHasanRepaymentsRelationManager::class,
            RelationManagers\WalletTransactionsRelationManager::class,
            RelationManagers\WithdrawalRequestsRelationManager::class,
            RelationManagers\StoreOrdersRelationManager::class,
            RelationManagers\BeneficiariesRelationManager::class,
            RelationManagers\JuniorAccountsRelationManager::class,
            RelationManagers\SavingsGoalsRelationManager::class,
            RelationManagers\TakafulContributionsRelationManager::class,
            RelationManagers\TakafulPoolEntriesRelationManager::class,
            RelationManagers\ProjectInvestmentsRelationManager::class,
            RelationManagers\ProjectProfitPayoutsRelationManager::class,
            RelationManagers\ChatRoomsRelationManager::class,
            RelationManagers\SupportMessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}