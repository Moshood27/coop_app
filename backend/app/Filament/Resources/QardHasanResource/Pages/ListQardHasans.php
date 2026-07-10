<?php

namespace App\Filament\Resources\QardHasanResource\Pages;

use App\Filament\Traits\HasWipeAction;

use App\Exports\LoanImportTemplate;
use Maatwebsite\Excel\Facades\Excel;
use App\Filament\Resources\QardHasanResource;
use App\Filament\Pages\QardHasanBranchReport;
use App\Services\CsvImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;

class ListQardHasans extends ListRecords
{
    use HasWipeAction;

    protected static string $resource = QardHasanResource::class;

    public function getSubheading(): ?string
    {
        return 'Manage interest-free (Qard Hasan) loan applications and repayments.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Apply for Loan')
                ->icon('heroicon-m-plus'),
            Actions\Action::make('branchReport')
                ->label('Outstanding Report')
                ->icon('heroicon-m-document-chart-bar')
                ->color('success')
                ->url(fn () => QardHasanBranchReport::getUrl()),
            Actions\Action::make('importCsv')
                ->label('Import CSV')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('info')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Loans CSV file')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->maxSize(10240)
                        ->storeFiles(false),
                    Forms\Components\Placeholder::make('template_info')
                        ->content(new \Illuminate\Support\HtmlString('Download the template for the required format: <a href="/admin/templates/loans-template.xlsx" style="color:blue;text-decoration:underline;">Download Excel Template</a>')),
                ])
                ->modalHeading('Import Loans from CSV')
                ->modalDescription('Upload a CSV with required loan fields. Tip: Use the Excel template below, then Save As CSV before uploading.')
                ->action(function (array $data): void {
                    try {
                        /** @var CsvImportService $svc */
                        $svc = app(CsvImportService::class);
                        $path = $data['file']->getRealPath();
                        $res = $svc->importLoans($path);
                        $s = $res['summary'] ?? [];
                        $errors = $res['errors'] ?? [];
                        $msg = sprintf('Processed: %d | Created: %d | Updated: %d | Failed: %d', $s['processed'] ?? 0, $s['created'] ?? 0, $s['updated'] ?? 0, $s['failed'] ?? 0);
                        if (!empty($errors)) {
                            $errorDetails = array_map(fn($e) => "Row {$e['row']}: {$e['error']}", array_slice($errors, 0, 5));
                            $msg .= "\n\nErrors:\n" . implode("\n", $errorDetails);
                            Notification::make()->warning()->title('Loans import completed with errors')->body($msg)->send();
                        } else {
                            Notification::make()->success()->title('Loans import completed')->body($msg)->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Import failed')->body($e->getMessage())->send();
                    }
                }),
            Actions\Action::make('downloadTemplate')
                ->label('Template')
                ->icon('heroicon-m-document-arrow-down')
                ->color('gray')
                ->action(fn () => Excel::download(new LoanImportTemplate, 'loans_import_template.xlsx')),
            $this->getWipeHeaderAction()
                ->icon('heroicon-m-trash'),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('print')
                ->label('Print List')
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->extraAttributes(['onclick' => 'window.print()']),
        ];
    }
}
