<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;

class FxOperationsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static string $view = 'filament.pages.fx-operations-page';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'FX Operations';
    protected static ?int $navigationSort = 85;

    public array $data = [];
    public ?array $lastResponse = null;

    public function mount(): void
    {
        $this->form->fill();
        if (!Schema::hasTable('ledger_journals')) {
            Notification::make()->title('Migrations pending')
                ->body('Run migrations after deployment to enable FX operations.')
                ->warning()->send();
        }
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Realized FX')
                ->schema([
                    Forms\Components\TextInput::make('currency_code')->label('Currency (e.g., USD)')->required()->maxLength(3),
                    Forms\Components\TextInput::make('amount_foreign')->numeric()->required()->label('Amount (foreign)'),
                    Forms\Components\TextInput::make('recognition_rate')->numeric()->required()->helperText('Rate used at recognition'),
                    Forms\Components\TextInput::make('settlement_rate')->numeric()->required()->helperText('Rate used at settlement'),
                    Forms\Components\TextInput::make('counter_account_code')->required()->label('Counter Account (e.g., AR/AP/Bank code)'),
                    Forms\Components\TextInput::make('fx_gain_account_code')->required()->label('FX Gain Account Code'),
                    Forms\Components\TextInput::make('fx_loss_account_code')->required()->label('FX Loss Account Code'),
                    Forms\Components\DatePicker::make('date')->label('Date')->default(now()),
                    Forms\Components\TextInput::make('branch_id')->numeric()->label('Branch ID (optional)'),
                    Forms\Components\TextInput::make('reference')->label('Reference'),
                    Forms\Components\TextInput::make('description')->label('Description'),
                ])->columns(2)
        ])->statePath('data');
    }

    public function postRealized(): void
    {
        if (!Schema::hasTable('ledger_journals')) {
            $this->lastResponse = ['status' => 'error', 'message' => 'Migrations pending'];
            return;
        }
        $payload = $this->form->getState();
        try {
            $resp = Http::asJson()->withToken(auth()->user()?->currentAccessToken()?->plainTextToken)
                ->post(url('/api/admin/accounting/fx/realized'), $payload);
            $this->lastResponse = $resp->json();
            Notification::make()->title('FX Realized')->success()->body('Response: '.json_encode($this->lastResponse))->send();
        } catch (\Throwable $e) {
            $this->lastResponse = ['status' => 'error', 'message' => $e->getMessage()];
            Notification::make()->title('FX Error')->danger()->body($e->getMessage())->send();
        }
    }
}
