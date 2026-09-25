<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Mail\WalletCredited;
use App\Jobs\SendBulkCommunication;
use App\Models\Branch;
use App\Models\ShariahAuditLog as ShariahAudit;
use App\Models\User;
use App\Models\Contribution;
use App\Models\Scheme;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\MemberApplication;
use App\Services\ChatService;
use App\Filament\Resources\ChatRoomResource;
use App\Mail\NewMemberWelcome;
use App\Mail\MemberApplicationRejected;
use App\Services\AttendanceService;
use App\Services\PushService;
use App\Services\SmsService;
use App\Services\TakafulService;
use App\Support\SecurityUtils;
use App\Services\AdministrativeChargeService;
use App\Notifications\WellnessCheckNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use App\Filament\Resources\UserResource\Traits\UserResourceSchema;

class UserResource extends Resource
{
    use UserResourceSchema;
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Core Cooperative';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(static::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(function (Builder $query): Builder {
                return $query->orderByRaw('LENGTH(surname) ASC')->orderBy('surname', 'asc');
            })
            ->columns(static::getTableColumns())
            ->filters(static::getTableFilters())
            ->headerActions(static::getTableHeaderActions())
            ->actions(static::getTableActions())
            ->bulkActions(static::getTableBulkActions());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_user');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_user');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('update_user');
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('delete_user');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $query = parent::getEloquentQuery()
            ->with(['branch'])
            ->withCount(['attendanceRecords as audited_attendance_count' => function ($query) {
                $query->where('status', 'present')
                    ->whereHas('meeting', function ($q) {
                        $q->where('status', 'audited');
                    });
            }]);

        // If the user is a Super Admin, let them see everything
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // Otherwise, only show records belonging to the user's branch
        return $query->where('branch_id', $user->branch_id);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ChatRoomsRelationManager::class,
            RelationManagers\QardHasansRelationManager::class,
            RelationManagers\QardHasanRepaymentsRelationManager::class,
            RelationManagers\ContributionsRelationManager::class,
            RelationManagers\TakafulContributionsRelationManager::class,
            RelationManagers\TakafulPoolEntriesRelationManager::class,
            RelationManagers\WithdrawalRequestsRelationManager::class,
            RelationManagers\SavingsGoalsRelationManager::class,
            RelationManagers\ProjectInvestmentsRelationManager::class,
            RelationManagers\ProjectProfitPayoutsRelationManager::class,
            RelationManagers\WalletTransactionsRelationManager::class,
            RelationManagers\StoreOrdersRelationManager::class,
            RelationManagers\BeneficiariesRelationManager::class,
            RelationManagers\JuniorAccountsRelationManager::class,
            ActivitiesRelationManager::class,
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
