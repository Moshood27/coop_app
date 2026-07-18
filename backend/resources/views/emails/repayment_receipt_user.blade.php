@extends('layouts.mail')

@section('title', 'Payment Receipt')

@section('content')
    @php($portalUrl = config('app.frontend_url') ?? config('app.url'))
    <p class="salam">Assalāmu ‘alaykum {{ $loan->user->full_name }},</p>
    <p>We pray this message finds you in good health and īmān.</p>
    <p>We received your Qard Hasan repayment. May Allāh put barakah in your wealth. Āmīn. Here are the details for your records:</p>
    <table class="meta" role="presentation">
        <tr>
            <th class="label">Loan ID</th>
            <td class="value">{{ $loan->qard_id_string }}</td>
        </tr>
        <tr>
            <th class="label">Amount paid</th>
            <td class="value">₦{{ number_format($repayment->amount, 2) }}</td>
        </tr>
        <tr>
            <th class="label">Reference</th>
            <td class="value">{{ $repayment->reference }}</td>
        </tr>
        <tr>
            <th class="label">Date</th>
            <td class="value">{{ $repayment->paid_at ?: now() }}</td>
        </tr>
        <tr>
            <th class="label">Remaining principal</th>
            <td class="value">₦{{ number_format($loan->remaining_principal, 2) }}</td>
        </tr>
        <tr>
            <th class="label">Loan status</th>
            <td class="value">{{ ucfirst((string) $loan->status) }}</td>
        </tr>
    </table>

    <div class="divider"></div>
    <p>You can view the latest schedule and your full repayment history in your member portal.</p>
    @if(!empty($portalUrl))
        <p style="margin:16px 0 0;">
            <a class="btn" href="{{ $portalUrl }}" target="_blank" rel="noopener">Open Member Portal</a>
        </p>
    @endif

    <p class="muted" style="margin-top:16px;">If you didn’t make this payment, please contact support immediately.</p>
@endsection
