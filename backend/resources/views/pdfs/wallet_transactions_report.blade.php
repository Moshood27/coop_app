<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Transactions Report</title>
    <style>
        @page {
            margin: 0.5cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header table {
            width: 100%;
        }
        .header .logo-text {
            font-size: 24px;
            font-weight: bold;
            color: #1a56db;
        }
        .header .report-title {
            font-size: 18px;
            text-align: right;
            text-transform: uppercase;
            color: #111827;
        }
        .summary-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .summary-box table {
            width: 100%;
        }
        .summary-box td {
            width: 25%;
        }
        .summary-label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }
        .summary-value.credit {
            color: #059669;
        }
        .summary-value.debit {
            color: #dc2626;
        }
        .filters-info {
            font-size: 9px;
            color: #4b5563;
            margin-bottom: 10px;
        }
        table.transactions {
            width: 100%;
            border-collapse: collapse;
        }
        table.transactions th {
            background-color: #111827;
            color: #ffffff;
            text-align: left;
            padding: 8px;
            text-transform: uppercase;
            font-size: 8px;
        }
        table.transactions td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.transactions tr:nth-child(even) {
            background-color: #f3f4f6;
        }
        .badge {
            padding: 2px 6px;
            border-radius: 9999px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-credit {
            background-color: #def7ec;
            color: #03543f;
        }
        .badge-debit {
            background-color: #fde2e2;
            color: #9b1c1c;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            font-size: 8px;
            color: #9ca3af;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
        .currency {
            font-family: DejaVu Sans, sans-serif;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="logo-text">AT-TAQWA</div>
                    <div style="font-size: 10px; color: #6b7280;">Digital Wallet Management System</div>
                </td>
                <td>
                    <div class="report-title">Wallet Ledger Report</div>
                    <div style="text-align: right; font-size: 9px; color: #6b7280;">
                        Generated on {{ now()->format('F d, Y H:i:s') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="filters-info">
        <strong>Report Parameters:</strong>
        Branch: {{ $branch }} |
        Period: {{ $filters['from_date'] ?? 'Start' }} to {{ $filters['to_date'] ?? 'End' }} |
        Type: {{ ucfirst($filters['type'] ?? 'All') }} |
        Source: {{ ucfirst($filters['source'] ?? 'All') }}
    </div>

    <div class="summary-box">
        <table>
            <tr>
                <td>
                    <div class="summary-label">Total Transactions</div>
                    <div class="summary-value">{{ count($reportData) }}</div>
                </td>
                <td>
                    <div class="summary-label">Total Credits</div>
                    <div class="summary-value credit"><span class="currency">₦</span>{{ number_format($totalCredit, 2) }}</div>
                </td>
                <td>
                    <div class="summary-label">Total Debits</div>
                    <div class="summary-value debit"><span class="currency">₦</span>{{ number_format($totalDebit, 2) }}</div>
                </td>
                <td>
                    <div class="summary-label">Net Movement</div>
                    <div class="summary-value {{ ($totalCredit - $totalDebit) >= 0 ? 'credit' : 'debit' }}">
                        <span class="currency">₦</span>{{ number_format($totalCredit - $totalDebit, 2) }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="transactions">
        <thead>
            <tr>
                <th>Date</th>
                <th>Member / Branch</th>
                <th>Purpose / Passbook</th>
                <th>Source / Gateway</th>
                <th>Reference</th>
                <th>Status</th>
                <th class="text-right">Type</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData as $row)
                <tr>
                    <td>{{ $row['date']->format('Y-m-d H:i') }}</td>
                    <td>
                        <strong>{{ $row['member'] }}</strong><br>
                        <span style="color: #6b7280; font-size: 8px;">{{ $row['membership_no'] }} | {{ $row['branch'] }}</span>
                    </td>
                    <td>
                        {{ $row['purpose'] }}<br>
                        <span style="color: #6b7280; font-size: 8px;">{{ $row['scheme'] }}</span>
                    </td>
                    <td>
                        {{ ucfirst(str_replace('_', ' ', $row['source'])) }}<br>
                        <span style="color: #6b7280; font-size: 8px;">{{ $row['gateway'] }}</span>
                    </td>
                    <td style="font-family: monospace;">{{ $row['reference'] }}</td>
                    <td>
                        <span style="color: #059669; font-weight: bold;">{{ $row['status'] ?? 'Success' }}</span>
                    </td>
                    <td class="text-right">
                        <span class="badge {{ $row['type'] === 'CREDIT' ? 'badge-credit' : 'badge-debit' }}">
                            {{ $row['type'] }}
                        </span>
                    </td>
                    <td class="text-right">
                        <strong><span class="currency">₦</span>{{ number_format($row['amount'], 2) }}</strong>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        This report was generated by {{ $generatedBy }} for official use. &copy; {{ date('Y') }} Cooperative Management System.
    </div>
</body>
</html>
