@extends('layouts.mail')

@section('title', 'Loan Disbursed (Admin)')

@section('content')
    <p>A loan has been disbursed.</p>
    <table class="meta" role="presentation">
        <tr>
            <th class="label">Loan ID</th>
            <td class="value">{{ $loan->qard_id_string }}</td>
        </tr>
        <tr>
            <th class="label">Member</th>
            <td class="value">{{ $loan->user->full_name }} ({{ $loan->user->email }})</td>
        </tr>
        <tr>
            <th class="label">Principal</th>
            <td class="value">₦{{ number_format($loan->principal_amount, 2) }}</td>
        </tr>
        <tr>
            <th class="label">Admin fee (flat)</th>
            <td class="value">₦{{ number_format($loan->admin_fee_flat, 2) }}</td>
        </tr>
        <tr>
            <th class="label">Admin fee (%)</th>
            <td class="value">{{ number_format($loan->admin_fee_pct, 2) }}%</td>
        </tr>
        <tr>
            <th class="label">Credited to wallet</th>
            <td class="value">₦{{ number_format($creditedAmount, 2) }}</td>
        </tr>
        <tr>
            <th class="label">Status</th>
            <td class="value">{{ ucfirst((string) $loan->status) }}</td>
        </tr>
        <tr>
            <th class="label">Disbursed at</th>
            <td class="value">{{ $loan->updated_at }}</td>
        </tr>
    </table>

    <div class="divider"></div>
    <p class="muted">This notification is for administrators. For full details, review the loan in the dashboard.</p>
@endsection
