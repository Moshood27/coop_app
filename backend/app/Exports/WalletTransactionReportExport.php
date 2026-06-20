<?php

namespace App\Exports;

use App\Models\WalletTransaction;
use App\Models\Scheme;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;

class WalletTransactionReportExport implements FromCollection, WithHeadings, WithMapping
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
                        'member' => $tx->user?->name ?? 'N/A',
                        'membership_no' => $tx->user?->membership_number ?? 'N/A',
                        'branch' => $tx->user?->branch?->name ?? 'N/A',
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

                // If filtering by scheme or purpose, these transactions might be excluded
                if (!empty($this->filters['scheme_id']) || !empty($this->filters['purpose'])) {
                    // For now, we exclude non-allocation transactions if scheme/purpose filter is active
                    // because we don't know their scheme/purpose.
                    continue;
                }

                $reportData->push([
                    'date' => $tx->created_at,
                    'member' => $tx->user?->name ?? 'N/A',
                    'membership_no' => $tx->user?->membership_number ?? 'N/A',
                    'branch' => $tx->user?->branch?->name ?? 'N/A',
                    'type' => strtoupper($tx->type),
                    'amount' => $tx->amount,
                    'source' => $tx->source,
                    'gateway' => $tx->meta['channel'] ?? $tx->meta['processor'] ?? 'N/A',
                    'reference' => $tx->reference,
                    'scheme' => 'N/A',
                    'purpose' => 'N/A',
                    'status' => 'Success',
                ]);
            }
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
}
