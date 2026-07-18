@extends('layouts.mail')

@section('title', 'Wallet Credited')

@section('content')
    <p class="salam">Assalāmu ‘alaykum {{ $user->full_name }},</p>
    <p>Alhamdulillāh, your cooperative wallet has just been credited.</p>

    <p class="amount">+ {{ '₦' . number_format($amount, 2) }}</p>

    <table class="meta" role="presentation" cellspacing="0" cellpadding="0">
        <tr>
            <th>Member</th>
            <td>{{ $user->full_name }} @if(!empty($user->membership_number)) ({{ $user->membership_number }}) @endif</td>
        </tr>
        <tr>
            <th>Date & Time</th>
            <td>{{ optional($timestamp)->timezone(config('app.timezone', 'Africa/Lagos'))?->format('D, d M Y - h:ia') }}</td>
        </tr>
        <tr>
            <th>Credited Amount</th>
            <td>{{ '₦' . number_format($amount, 2) }}</td>
        </tr>
        @if(isset($newBalance))
        <tr>
            <th>New Wallet Balance</th>
            <td>{{ '₦' . number_format($newBalance, 2) }}</td>
        </tr>
        @endif
        @if(!empty($note))
        <tr>
            <th>Note</th>
            <td>{{ $note }}</td>
        </tr>
        @endif
    </table>

    <div class="divider"></div>

    <p>May Allāh put barakah in your wealth and make this a means of ease and goodness for you and your family. Āmīn.</p>
    <p class="muted">If you did not expect this credit, please contact our support immediately.</p>

    @php($supportEmail = config('mail.from.address'))
    <p>
        <a href="mailto:{{ $supportEmail }}" class="btn">Contact Support</a>
    </p>
@endsection
