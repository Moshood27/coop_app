@extends('layouts.mail')

@section('title', 'Invitation to Join ' . config('app.name'))

@section('content')
    @php($supportEmail = config('mail.from.address'))
    <p class="salam">Assalāmu ‘alaykum {{ $name }},</p>
    <p>You have been invited to join <strong>{{ config('app.name') }}</strong>.</p>

    @if(!empty($customMessage))
        <div class="divider"></div>
        <p><strong>Message from sender:</strong></p>
        <p style="font-style: italic;">"{{ $customMessage }}"</p>
        <div class="divider"></div>
    @endif

    <p>We invite you to register as a member and access our cooperative benefits, including Qard Hasan loans, contributions management, and more.</p>

    <p style="margin:24px 0;">
        <a class="btn" href="{{ $registrationUrl }}" target="_blank" rel="noopener">Complete Registration Form</a>
    </p>

    <p>If you have any questions, feel free to reach out to our support team.</p>
    <p>
        <a href="mailto:{{ $supportEmail }}" class="btn">Contact Support</a>
    </p>
@endsection
