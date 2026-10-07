<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChargeAndFineManagementResource\Pages;
use App\Models\WalletTransaction;
use App\Models\User;
use App\Models\Contribution;
use App\Models\CharityEntry;
use App\Models\AttendanceRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;

class ChargeAndFineManagementResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Financial Reports';
    protected static ?string $navigationLabel = 'Charge & Fine Management';
    protected static ?string $pluralLabel = 'Charge & Fine Management';
    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('source', [
                'admin_charge',
                'attendance_fine_collection',
                'attendance_fine',
                'maintenance_charge'
            ])
            ->where('type', 'debit')
            ->select('wallet_transactions.*')
            ->addSelect([
                'refunded_id' => WalletTransaction::select('id')
                    ->whereColumn('reference', DB::raw("CONCAT('REFUND-', wallet_transactions.reference)"))
                    ->limit(1)
            ])
            ->with(['user.branch']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'full_name')
                    ->disabled(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->prefix('₦')
                    ->disabled(),
                Forms\Components\TextInput::make('source')
                    ->disabled(),
                Forms\Components\TextInput::make('reference')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('created_at')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label('Member')
                    ->searchable(['surname', 'name', 'other_names', 'membership_number'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.membership_number')
                    ->label('Membership #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin_charge' => 'info',
                        'attendance_fine_collection', 'attendance_fine' => 'warning',
                        'maintenance_charge' => 'gray',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('amount')
                    ->money('NGN')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total')),
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('refunded_id')
                    ->label('Refunded')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->options([
                        'admin_charge' => 'Admin Charge',
                        'attendance_fine_collection' => 'Fine Collection',
                        'attendance_fine' => 'Attendance Fine',
                        'maintenance_charge' => 'Maintenance Charge',
                    ]),
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('user.branch', 'name'),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('to')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Filter::make('month')
                    ->form([
                        Forms\Components\Select::make('month')
                            ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => Carbon::create()->month($m)->format('F')]))
                            ->label('Month'),
                        Forms\Components\Select::make('year')
                            ->options(collect(range(date('Y'), date('Y') - 5))->mapWithKeys(fn ($y) => [$y => $y]))
                            ->default(date('Y'))
                            ->label('Year'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['month'], fn ($q) => $q->whereMonth('created_at', $data['month']))
                            ->when($data['year'], fn ($q) => $q->whereYear('created_at', $data['year']));
                    }),
                Filter::make('not_refunded')
                    ->label('Hide Refunded')
                    ->query(fn (Builder $query) => $query->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('wallet_transactions as wt2')
                            ->whereColumn('wt2.reference', DB::raw("CONCAT('REFUND-', wallet_transactions.reference)"));
                    })),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Refund Transaction')
                    ->modalDescription('This will credit the member wallet, restore pending balances/fines, and notify the member. Are you sure?')
                    ->hidden(fn (WalletTransaction $record) => $record->refunded_id !== null)
                    ->action(function (WalletTransaction $record) {
                        try {
                            DB::transaction(function () use ($record) {
                                $user = $record->user;
                                if (!$user) throw new \Exception("User not found.");

                                $amount = $record->amount;

                                // 1. Handle Admin Charge
                                if ($record->source === 'admin_charge') {
                                    $contribution = Contribution::where('user_id', $user->id)
                                        ->where('reference', $record->reference)
                                        ->first();

                                    if ($contribution) {
                                        $contribution->delete();
                                    }

                                    $user->increment('balance', $amount);
                                    $user->increment('admin_charge_balance', $amount);
                                }
                                // 2. Handle Fine Collection
                                elseif (in_array($record->source, ['attendance_fine_collection', 'attendance_fine'])) {
                                    // Delete charity entry if exists
                                    CharityEntry::where('user_id', $user->id)
                                        ->where('amount', $amount)
                                        ->whereBetween('created_at', [$record->created_at->subSeconds(10), $record->created_at->addSeconds(10)])
                                        ->delete();

                                    // Revert attendance records
                                    $attendanceRecords = AttendanceRecord::where('user_id', $user->id)
                                        ->where(function($q) use ($record) {
                                            $q->whereBetween('fine_paid_at', [$record->created_at->subSeconds(10), $record->created_at->addSeconds(10)])
                                              ->orWhere(function($sq) use ($record) {
                                                  $sq->where('lateness_fine_paid', true)
                                                     ->whereBetween('updated_at', [$record->created_at->subSeconds(10), $record->created_at->addSeconds(10)]);
                                              });
                                        })
                                        ->get();

                                    foreach ($attendanceRecords as $attRecord) {
                                        if ($attRecord->status === 'fine_paid') {
                                            $attRecord->update(['status' => 'fine_pending', 'fine_paid_at' => null]);
                                        }
                                        if ($attRecord->lateness_fine_paid) {
                                            $attRecord->update(['lateness_fine_paid' => false]);
                                        }
                                    }

                                    $user->increment('balance', $amount);
                                    $user->increment('outstanding_fines', $amount);
                                }
                                // 3. Handle Maintenance Charge
                                else {
                                    $user->increment('balance', $amount);
                                }

                                // Create Refund Transaction
                                WalletTransaction::create([
                                    'user_id' => $user->id,
                                    'type' => 'credit',
                                    'amount' => $amount,
                                    'reference' => 'REFUND-' . $record->reference,
                                    'source' => 'refund',
                                    'meta' => [
                                        'original_tx_id' => $record->id,
                                        'original_source' => $record->source,
                                        'description' => "Refund for " . ucwords(str_replace('_', ' ', $record->source)),
                                        'admin_id' => auth()->id(),
                                    ]
                                ]);

                                // Notify Member
                                $title = "Refund Processed";
                                $message = "A refund of ₦" . number_format($amount, 2) . " has been credited to your wallet for: " . ucwords(str_replace('_', ' ', $record->source));

                                $user->notifyMember($title, $message, [
                                    'type' => 'refund',
                                    'amount' => $amount,
                                    'source' => $record->source
                                ], ['mail', 'push', 'database']);
                            });

                            Notification::make()
                                ->title('Refunded successfully')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error during refund')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                // Bulk refund could be dangerous, so let's stick to individual for now or add confirmation
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChargeAndFineManagements::route('/'),
        ];
    }
}
