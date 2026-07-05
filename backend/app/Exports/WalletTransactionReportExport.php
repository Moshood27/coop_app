<?php

namespace App\Exports;

use App\Models\WalletTransaction;
use App\Models\Scheme;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;

class WalletTransactionReportExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    protected $filters;
    protected $schemes;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
        $this->schemes = Scheme::pluck('name', 'id')->toArray();
    }

    public function collection()
    {
        $query = WalletTransaction::with(['user.branch'])->orderBy('created_at', 'desc');

        if (!empty($this->filters['from_date'])) {
            $query->whereDate('created_at', '>=', $this->filters['from_date']);
        }

        if (!empty($this->filters['to_date'])) {
            $query->whereDate('created_at', '<=', $this->filters['to_date']);
        }

        if (!empty($this->filters['source'])) {
            $query->where('source', $this->filters['source']);
        }

        if (!empty($this->filters['type'])) {
            $query->where('type', $this->filters['type']);
        }

        if (!empty($this->filters['branch_id'])) {
            $query->whereHas('user', function($q) {
                $q->where('branch_id', $this->filters['branch_id']);
            });
        }

        $transactions = $query->get();
        $reportData = new Collection();

        foreach ($transactions as $tx) {
            $distribution = $tx->meta['distribution'] ?? null;

            if ($tx->source === 'wallet_allocation' && is_array($distribution)) {
                foreach ($distribution as $item) {
                    $schemeId = $item['scheme_id'] ?? null;
                    $category = $item['category'] ?? null;

                    // Filter by scheme if provided
                    if (!empty($this->filters['scheme_id']) && $schemeId != $this->filters['scheme_id']) {
                        continue;
                    }

                    // Filter by purpose if provided
                    if (!empty($this->filters['purpose']) && $category !== $this->filters['purpose']) {
                        continue;
                    }

                    $reportData->push([
                        'date' => $tx->created_at,
                        'member' => $tx->user?->full_name ?? 'N/A',
                        'membership_no' => $tx->user?->membership_number ?? 'N/A',
                        'branch' => $tx->user?->branch?->name ?? 'Unassigned',
                        'type' => strtoupper($tx->type),
                        'amount' => $item['amount'] ?? $tx->amount,
                        'source' => $tx->source,
                        'gateway' => $tx->meta['channel'] ?? $tx->meta['processor'] ?? 'N/A',
                        'reference' => $tx->reference,
                        'scheme' => $this->schemes[$schemeId] ?? 'N/A',
                        'purpose' => $this->formatPurpose($category),
                        'status' => 'Success',
                    ]);
                }
            } else {
                // Not a wallet allocation, or no distribution info

                // If filtering by scheme, these are excluded as they don't have a scheme
                if (!empty($this->filters['scheme_id'])) {
                    continue;
                }

                // If filtering by purpose
                if (!empty($this->filters['purpose'])) {
                    // Allow if source matches purpose (e.g. loan_repayment source matches loan_repayment purpose)
                    if ($this->filters['purpose'] === 'loan_repayment' && $tx->source === 'loan_repayment') {
                        // Keep going
                    } else {
                        continue;
                    }
                }

                $reportData->push([
                    'date' => $tx->created_at,
                    'member' => $tx->user?->full_name ?? 'N/A',
                    'membership_no' => $tx->user?->membership_number ?? 'N/A',
                    'branch' => $tx->user?->branch?->name ?? 'Unassigned',
                    'type' => strtoupper($tx->type),
                    'amount' => $tx->amount,
                    'source' => $tx->source,
                    'gateway' => $tx->meta['channel'] ?? $tx->meta['processor'] ?? 'N/A',
                    'reference' => $tx->reference,
                    'scheme' => 'N/A',
                    'purpose' => $tx->source === 'loan_repayment' ? 'Loan Repayment' : ($tx->type === 'credit' ? 'Wallet Top-up' : 'N/A'),
                    'status' => 'Success',
                ]);
            }
        }

        if (!empty($this->filters['sort_by_branch'])) {
            return $reportData->sortBy([
                [fn($row) => $row['branch'] === 'Unassigned' ? 'ZZZ' : $row['branch'], 'asc'],
                ['date', 'desc'],
            ]);
        }

        return $reportData;
    }

    protected function formatPurpose($category)
    {
        return match ($category) {
            'deposit' => 'Contribution',
            'loan_repayment' => 'Loan Repayment',
            'withdrawal' => 'Withdrawal',
            'fine' => 'Fine Payment',
            default => ucfirst(str_replace('_', ' ', $category ?? 'N/A')),
        };
    }

    public function headings(): array
    {
        return [
            'Date',
            'Member',
            'Membership #',
            'Branch',
            'Type',
            'Amount',
            'Source',
            'Gateway',
            'Reference',
            'Passbook / Scheme',
            'Purpose',
            'Status',
        ];
    }

    public function map($row): array
    {
        return [
            $row['date']->format('Y-m-d H:i:s'),
            $row['member'],
            $row['membership_no'],
            $row['branch'],
            $row['type'],
            number_format($row['amount'], 2),
            ucfirst(str_replace('_', ' ', $row['source'])),
            $row['gateway'],
            $row['reference'],
            $row['scheme'],
            $row['purpose'],
            $row['status'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getDelegate()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
            },
        ];
    }
}
