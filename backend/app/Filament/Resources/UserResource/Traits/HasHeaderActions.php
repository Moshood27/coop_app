<?php

namespace App\Filament\Resources\UserResource\Traits;

use App\Models\Branch;
use App\Models\User;
use App\Jobs\SendBulkCommunication;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Forms;
use Illuminate\Support\Facades\DB;

trait HasHeaderActions
{
    public static function getHeaderActions(): array
    {
        return [
            Action::make('exportGmailMembers')
                ->label('Export Gmail Members')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $users = User::member()
                        ->where('email', 'like', '%@gmail.com')
                        ->orderBy('email')
                        ->get(['email']);

                    if ($users->isEmpty()) {
                        Notification::make()
                            ->title('No Gmail members found')
                            ->warning()
                            ->send();
                        return;
                    }

                    $csvData = "Email\n";
                    foreach ($users as $user) {
                        $csvData .= $user->email . "\n";
                    }

                    return response()->streamDownload(
                        fn () => print($csvData),
                        'gmail-members-' . now()->format('Y-m-d') . '.csv',
                        ['Content-Type' => 'text/csv']
                    );
                }),
            Action::make('print_list')
                ->label('Print Member List')
                ->icon('heroicon-o-printer')
                ->url(fn (Table $table) => route('admin.print.users-list', [
                    'branch_id' => data_get($table->getLivewire()->tableFilters, 'branch.value'),
                    'search' => $table->getLivewire()->getTableSearch(),
                ]))
                ->openUrlInNewTab(),
            Action::make('print')
                ->label('Print Screen')
                ->icon('heroicon-o-computer-desktop')
                ->extraAttributes(['onclick' => 'window.print()']),
            Action::make('bulkCommunicate')
                ->label('Bulk Communicate')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->form([
                    Forms\Components\Select::make('branch_id')
                        ->label('Branch')
                        ->options(Branch::all()->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('title')
                        ->label('Title (Optional)')
                        ->placeholder('Coop Notice')
                        ->maxLength(100),
                    Forms\Components\Textarea::make('message')
                        ->label('Message')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\CheckboxList::make('channels')
                        ->label('Channels')
                        ->options([
                            'sms' => 'SMS',
                            'push' => 'Push Notification',
                            'mail' => 'Email',
                        ])
                        ->required()
                        ->columns(3),
                ])
                ->action(function (array $data) {
                    SendBulkCommunication::dispatch(
                        (int) $data['branch_id'],
                        $data['title'] ?: 'Coop Notice',
                        $data['message'],
                        $data['channels'],
                        auth()->id()
                    );

                    Notification::make()
                        ->title('Bulk communication queued.')
                        ->body("The messages are being sent in the background.")
                        ->info()
                        ->send();
                }),
        ];
    }
}
