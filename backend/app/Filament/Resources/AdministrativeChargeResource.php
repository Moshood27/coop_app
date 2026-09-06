<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdministrativeChargeResource\Pages;
use App\Models\Branch;
use App\Models\WalletTransaction;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdministrativeChargeResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Admin Charge Reports';
    protected static ?string $navigationGroup = 'Financial Reports';
    protected static ?int $navigationSort = 5;
    protected static ?string $breadcrumb = 'Admin Charges';
    protected static ?string $pluralLabel = 'Admin Charge Reports';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->addSelect([
                'collected_sitting_fees' => \Illuminate\Support\Facades\DB::table('contributions')
                    ->join('users', 'users.id', '=', 'contributions.user_id')
                    ->whereColumn('users.branch_id', 'branches.id')
                    ->where('contributions.scheme_id', 60)
                    ->where('users.is_distant', false)
                    ->selectRaw('coalesce(sum(contributions.amount), 0)'),
                'collected_meeting_fees' => \Illuminate\Support\Facades\DB::table('contributions')
                    ->join('users', 'users.id', '=', 'contributions.user_id')
                    ->whereColumn('users.branch_id', 'branches.id')
                    ->where('contributions.scheme_id', 60)
                    ->where('users.is_distant', true)
                    ->selectRaw('coalesce(sum(contributions.amount), 0)'),
                'outstanding_sitting_fees' => \Illuminate\Support\Facades\DB::table('users')
                    ->whereColumn('branch_id', 'branches.id')
                    ->where('is_distant', false)
                    ->selectRaw('coalesce(sum(admin_charge_balance), 0)'),
                'outstanding_meeting_fees' => \Illuminate\Support\Facades\DB::table('users')
                    ->whereColumn('branch_id', 'branches.id')
                    ->where('is_distant', true)
                    ->selectRaw('coalesce(sum(admin_charge_balance), 0)'),
                'total_outstanding' => \Illuminate\Support\Facades\DB::table('users')
                    ->whereColumn('branch_id', 'branches.id')
                    ->selectRaw('coalesce(sum(admin_charge_balance), 0)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('collected_sitting_fees')
                    ->label('Collected Sitting')
                    ->money('NGN')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total Collected Sitting')),

                TextColumn::make('collected_meeting_fees')
                    ->label('Collected Meeting')
                    ->money('NGN')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total Collected Meeting')),

                TextColumn::make('outstanding_sitting_fees')
                    ->label('Outstanding Sitting')
                    ->money('NGN')
                    ->color('danger')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total Outstanding Sitting')),

                TextColumn::make('outstanding_meeting_fees')
                    ->label('Outstanding Meeting')
                    ->money('NGN')
                    ->color('danger')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total Outstanding Meeting')),

                TextColumn::make('total_outstanding')
                    ->label('Total Outstanding')
                    ->money('NGN')
                    ->weight('bold')
                    ->color('danger')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Grand Total Outstanding')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('View Members')
                    ->icon('heroicon-o-users')
                    ->url(fn (Branch $record) => UserResource::getUrl('index', [
                        'tableFilters[branch][value]' => $record->id,
                        'tableFilters[has_admin_charge_balance][value]' => '1',
                    ])),
            ])
            ->bulkActions([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('export_report')
                    ->label('Export PDF')
                    ->icon('heroicon-o-printer')
                    ->action(function () {
                        // Logic for exporting PDF could go here
                        // For now, we'll use the print action
                    })
                    ->extraAttributes(['onclick' => 'window.print()']),
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
            'index' => Pages\ListAdministrativeCharges::route('/'),
        ];
    }
}
