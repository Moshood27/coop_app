<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Services\FinancialReconciliationService;
use Illuminate\Support\Facades\Schema;

class FinancialReconciliationPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static string $view = 'filament.pages.financial-reconciliation-page';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Financial Reconciliation';
    protected static ?int $navigationSort = 87;

    public array $data = [];
    public ?array $lastResult = null;
    public bool $isProcessing = false;

    public function mount(): void
    {
        $admin = auth()->user();
        $this->form->fill([
            'fix' => false,
            'user_id' => null,
            'branch_id' => $admin->branch_id,
            'rollback' => false,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Audit Parameters')
                ->description('Audit and repair discrepancies in member wallets, passbooks, and loans.')
                ->schema([
                    Forms\Components\TextInput::make('user_id')
                        ->label('Specific User ID')
                        ->numeric()
                        ->helperText('Leave blank to run for all members.'),
                    Forms\Components\Select::make('branch_id')
                        ->label('Specific Branch')
                        ->options(\App\Models\Branch::pluck('name', 'id'))
                        ->searchable()
                        ->helperText('Restrict audit to a specific branch.'),
                    Forms\Components\Toggle::make('fix')
                        ->label('Apply Fixes')
                        ->helperText('If enabled, the system will attempt to repair identified discrepancies (e.g., updating balances, creating missing journals).'),
                    Forms\Components\Toggle::make('rollback')
                        ->label('Rollback Sync Records')
                        ->helperText('If enabled, will identify and (if Fix is ON) delete records created by the problematic automatic sync script.')
                        ->columnSpanFull(),
                ])->columns(2)
        ])->statePath('data');
    }

    public function runReconciliation(FinancialReconciliationService $service): void
    {
        $this->isProcessing = true;
        $state = $this->form->getState();

        try {
            if ($state['rollback'] ?? false) {
                $this->lastResult = $service->rollbackSync(
                    $state['fix'] ?? false,
                    $state['branch_id'] ? (int)$state['branch_id'] : null
                );

                Notification::make()
                    ->title($state['fix'] ? 'Sync Records Rolled Back' : 'Rollback Audit Completed')
                    ->success()
                    ->send();
            } else {
                $this->lastResult = $service->run(
                    $state['fix'] ?? false,
                    $state['user_id'] ? (int)$state['user_id'] : null,
                    $state['branch_id'] ? (int)$state['branch_id'] : null
                );

                Notification::make()
                    ->title($state['fix'] ? 'Reconciliation Fixed' : 'Audit Completed')
                    ->success()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Reconciliation Failed')
                ->danger()
                ->body($e->getMessage())
                ->send();
        } finally {
            $this->isProcessing = false;
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->can('ops.financial_reconcile');
    }
}
