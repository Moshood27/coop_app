<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonthClosingResource\Pages;
use App\Models\MonthClosing;
use App\Models\Setting;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class MonthClosingResource extends Resource
{
    protected static ?string $model = MonthClosing::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Month Closings';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('month')
                    ->formatStateUsing(fn (int $state): string => Carbon::create()->month($state)->format('F'))
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closer.name')
                    ->label('Closed By')
                    ->sortable(),
            ])
            ->defaultSort('year', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('close_month')
                    ->label('Close New Month')
                    ->form([
                        Forms\Components\Select::make('year')
                            ->options(array_combine(range(date('Y') - 1, date('Y') + 1), range(date('Y') - 1, date('Y') + 1)))
                            ->default(date('Y'))
                            ->required(),
                        Forms\Components\Select::make('month')
                            ->options([
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                            ])
                            ->default(date('n'))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $exists = MonthClosing::where('year', $data['year'])
                            ->where('month', $data['month'])
                            ->exists();

                        if ($exists) {
                            Notification::make()
                                ->danger()
                                ->title('Month already closed')
                                ->body("The month of " . Carbon::create()->month($data['month'])->format('F') . " {$data['year']} is already closed.")
                                ->send();
                            return;
                        }

                        MonthClosing::create([
                            'year' => $data['year'],
                            'month' => $data['month'],
                            'closed_at' => now(),
                            'closed_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Month closed successfully')
                            ->body("The month of " . Carbon::create()->month($data['month'])->format('F') . " {$data['year']} has been closed.")
                            ->send();
                    })
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Re-open')
                    ->modalHeading('Re-open Month')
                    ->modalDescription('Are you sure you want to re-open this month? This will allow new transactions for this period.')
                    ->successNotificationTitle('Month re-opened successfully'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMonthClosings::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
