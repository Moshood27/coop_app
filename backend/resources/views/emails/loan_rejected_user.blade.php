@extends('layouts.mail')

@section('title', 'Loan Request Rejected')

@section('content')
    <p class="salam">Assalāmu ‘alaykum {{ $member?->name ?? 'Member' }},</p>
    <p>We pray this message finds you in good health and īmān.</p>
    <p>Your Qard Hasan request <strong>{{ $loan->qard_id_string }}</strong> has been declined.</p>

    <p class="reason" style="background:#fff7ed; border:1px solid #fdba74; color:#7c2d12; padding:12px 14px; border-radius:8px;"><strong>Reason:</strong> {{ $reason }}</p>

    <table class="meta" role="presentation">
        <tr>
            <th class="label">Principal</th>
            <td class="value">₦{{ number_format((float) $loan->principal_amount, 2) }}</td>
        </tr>
        <tr>
            <th class="label">Installments</th>
            <td class="value">{{ $loan->total_installments }} × ₦{{ number_format((float) $loan->per_installment, 2) }} ({{ ucfirst((string) $loan->interval) }})</td>
        </tr>
    </table>

    <div class="divider"></div>
    <p class="muted">If you have any questions, please contact support or your branch admin.</p>
@endsection
