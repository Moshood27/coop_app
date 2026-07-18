@extends('layouts.mail')

@section('title', 'Welcome to ' . config('app.name'))

@section('content')
    @php($portalUrl = config('app.frontend_url') ?? config('app.url'))
    @php($supportEmail = config('mail.from.address'))

    <p class="salam">Assalāmu ‘alaykum {{ $user->full_name }},</p>
    <p>Welcome to {{ config('app.name') }}. We begin in the Name of Allāh, the Most Merciful, the Especially Merciful. May Allāh place barakah in your membership and make it a means of khayr for you and your family. Āmīn.</p>

    @if(!empty($user->membership_number))
    <table class="meta" role="presentation" cellspacing="0" cellpadding="0">
        <tr>
            <th>Membership Number</th>
            <td>{{ $user->membership_number }}</td>
        </tr>
        <tr>
            <th>Name</th>
            <td>{{ $user->full_name }}</td>
        </tr>
    </table>
    @endif

    <div class="divider"></div>

    <p>You can access your member portal to review your profile, track Qard Hasan loans and repayments, and manage your contributions.</p>
    @if(!empty($portalUrl))
    <p style="margin:16px 0 0;">
        <a class="btn" href="{{ $portalUrl }}" target="_blank" rel="noopener">Open Member Portal</a>
    </p>
    @endif

    <p class="muted" style="margin-top:16px;">If you did not expect this membership or believe it was created in error, please contact us.</p>
    <p>
        <a href="mailto:{{ $supportEmail }}" class="btn">Contact Support</a>
    </p>
@endsection
