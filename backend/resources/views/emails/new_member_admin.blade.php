@extends('layouts.mail')

@section('title', 'New member created')

@section('content')
    <p>A new member account has been created.</p>
    <table class="meta" role="presentation">
        <tr>
            <th class="label">Name</th>
            <td class="value">{{ $user->full_name }}</td>
        </tr>
        <tr>
            <th class="label">Email</th>
            <td class="value">{{ $user->email ?: '—' }}</td>
        </tr>
        @if($user->branch)
            <tr>
                <th class="label">Branch</th>
                <td class="value">{{ $user->branch->name }}</td>
            </tr>
        @endif
        @if(!empty($user->membership_number))
            <tr>
                <th class="label">Membership #</th>
                <td class="value">{{ $user->membership_number }}</td>
            </tr>
        @endif
        <tr>
            <th class="label">Created at</th>
            <td class="value">{{ $user->created_at }}</td>
        </tr>
    </table>

    <div class="divider"></div>
    <p style="color:#6b7280; font-size:13px;">This notification was sent to administrators. No action is required unless follow-up is needed.</p>
@endsection
