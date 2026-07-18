@extends('layouts.mail')

@section('title', 'Qard Hasan Disbursed')

@section('content')
    @php($portalUrl = config('app.frontend_url') ?? config('app.url'))
    @php($supportEmail = config('mail.from.address'))

    <p class="salam">Assalāmu ‘alaykum {{ $loan->user->full_name }},</p>
    <p>Alhamdulillāh, your Qard Hasan has been disbursed successfully. The amount below has been credited to your cooperative wallet.</p>

    <p class="amount">+ ₦{{ number_format($creditedAmount, 2) }}</p>

    <table class="meta" role="presentation" cellspacing="0" cellpadding="0">
        <tr>
            <th>Loan ID</th>
            <td>{{ $loan->qard_id_string ?: ('QH-' . $loan->id) }}</td>
        </tr>
        <tr>
            <th>Principal Amount</th>
            <td>₦{{ number_format($loan->principal_amount, 2) }}</td>
        </tr>
        <tr>
            <th>Credited to Wallet</th>
            <td>₦{{ number_format($creditedAmount, 2) }}</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>{{ ucfirst((string) $loan->status) }}</td>
        </tr>
        <tr>
            <th>Disbursed At</th>
            <td>{{ optional($loan->updated_at)->timezone(config('app.timezone','Africa/Lagos'))?->format('D, d M Y - h:ia') }}</td>
        </tr>
        @if(!empty($loan->user?->balance))
        <tr>
            <th>Current Wallet Balance</th>
            <td>₦{{ number_format((float) $loan->user->balance, 2) }}</td>
        </tr>
        @endif
    </table>

    <div class="divider"></div>
    <p>May Allāh make this a means of ease for you and bless your wealth with barakah. Remember, Qard Hasan is a trust—please plan your repayments responsibly. Āmīn.</p>

    <p class="muted">You can view your loan schedule and repayments from your member portal.</p>
    <p>
        @if(!empty($portalUrl))
        <a class="btn" href="{{ $portalUrl }}" target="_blank" rel="noopener">Open Member Portal</a>
        @endif
        <a class="btn" href="mailto:{{ $supportEmail }}">Contact Support</a>
    </p>
@endsection
