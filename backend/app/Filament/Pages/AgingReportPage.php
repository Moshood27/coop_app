<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use App\Services\AgingReportService;

class AgingReportPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.aging-report-page';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'AR/AP Aging';

    // Holds Filament form state (statePath: 'data')
    public ?array $data = [];

    public ?array $result = null;

    public function mount(): void
    {
        $this->form->fill();
        if (!Schema::hasTable('ledger_entries')) {
            Notification::make()->title('Migrations pending')
                ->body('Run php artisan migrate after deployment to enable Aging Reports.')
                ->warning()->send();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options(['ar' => 'Accounts Receivable', 'ap' => 'Accounts Payable'])
                    ->default('ar')
                    ->required(),
                Forms\Components\DatePicker::make('asOf')
                    ->label('As of')
                    ->default(now())
                    ->required(),
                Forms\Components\TextInput::make('buckets')
                    ->label('Buckets (days, comma separated)')
                    ->default('30,60,90'),
                Forms\Components\TextInput::make('accounts')
                    ->label('Account codes (CSV, optional)')
                    ->helperText('Blank = all asset (AR) or liability (AP) accounts'),
                Forms\Components\TextInput::make('branch_id')
                    ->numeric()
                    ->label('Branch ID (optional)'),
            ])
            ->statePath('data');
    }

    public function generate(AgingReportService $service): void
    {
        if (!Schema::hasTable('ledger_entries')) {
            $this->result = null;
            Notification::make()->title('Migrations pending')
                ->warning()->body('Aging requires ledger tables. Run migrations after deploy.')->send();
            return;
        }

        $data = $this->form->getState();
        $buckets = array_map('intval', array_filter(explode(',', (string)($data['buckets'] ?? '30,60,90'))));
        $this->result = $service->build(
            in_array($data['type'] ?? 'ar', ['ar','ap']) ? $data['type'] : 'ar',
            (string)($data['asOf'] ?? now()->toDateString()),
            $buckets ?: [30,60,90],
            !empty($data['branch_id']) ? (int)$data['branch_id'] : null,
            !empty($data['accounts']) ? array_filter(explode(',', (string)$data['accounts'])) : null,
        );
    }
}
