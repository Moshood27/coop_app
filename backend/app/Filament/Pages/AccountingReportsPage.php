<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Schema;

class AccountingReportsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static string $view = 'filament.pages.accounting-reports-page';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Report Exports';
    protected static ?int $navigationSort = 86;

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('from')->default(now()->startOfMonth()),
            Forms\Components\DatePicker::make('to')->default(now()),
            Forms\Components\TextInput::make('branch_id')->numeric()->label('Branch ID (optional)'),
            Forms\Components\TextInput::make('compare_from')->label('Compare: From (YYYY-MM-DD)'),
            Forms\Components\TextInput::make('compare_to')->label('Compare: To (YYYY-MM-DD)'),
        ])->statePath('data');
    }

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable('ledger_journals');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
