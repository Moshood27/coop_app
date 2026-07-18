@extends('layouts.mail')

@section('title', 'Qard Hasan Repayment Reminder')

@section('content')
    @php($portalUrl = config('app.frontend_url') ?? config('app.url'))
    @php($supportEmail = config('mail.from.address'))

    <p class="salam">Assalāmu ‘alaykum {{ $user->full_name }},</p>
    <p>We pray this message finds you in good health and īmān. As a gentle reminder, you have outstanding Qard Hasan repayment(s) with {{ config('app.name') }}. Kindly review the summary below and, if able, make payment at your earliest convenience. May Allāh put barakah in your wealth and make repayment easy for you. Āmīn.</p>

    <div class="divider"></div>

    <p style="margin:0 0 6px; font-weight:600;">Your outstanding loans</p>
    <table class="meta" role="presentation" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th style="width:22%;">Loan ID</th>
                <th style="width:20%;">Status</th>
                <th style="width:20%;">Principal (₦)</th>
                <th style="width:18%;">Paid (₦)</th>
                <th style="width:20%;">Outstanding (₦)</th>
            </tr>
        </thead>
        <tbody>
        @foreach($loans as $ln)
            <tr>
                <td>{{ $ln['loan_id'] }}</td>
                <td>{{ ucfirst((string) $ln['status']) }}</td>
                <td>{{ number_format($ln['principal'], 2) }}</td>
                <td>{{ number_format($ln['paid'], 2) }}</td>
                <td>{{ number_format($ln['remaining'], 2) }}</td>
            </tr>
        @endforeach
            <tr class="total">
                <td colspan="4">Total Outstanding</td>
                <td>₦{{ number_format($totalOutstanding, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="divider"></div>

    <p class="muted">If you recently made a repayment, kindly disregard this notice. Otherwise, you can pay via your usual channel or reach out for assistance.</p>
    <p>
        @if(!empty($portalUrl))
        <a class="btn" href="{{ $portalUrl }}" target="_blank" rel="noopener">Open Member Portal</a>
        @endif
        <a class="btn" href="mailto:{{ $supportEmail }}">Contact Support</a>
    </p>
@endsection
