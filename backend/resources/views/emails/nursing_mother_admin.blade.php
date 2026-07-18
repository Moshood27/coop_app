@extends('layouts.mail')

@section('title', 'New Nursing Mother Grace Request')

@section('content')
    <p>A member has applied for nursing mother grace. Please review the medical proof and approve or reject the request.</p>
    <table class="meta" role="presentation">
        <tr>
            <th class="label">Member</th>
            <td class="value">{{ $member->full_name }}</td>
        </tr>
        <tr>
            <th class="label">Email</th>
            <td class="value">{{ $member->email }}</td>
        </tr>
        <tr>
            <th class="label">Membership No.</th>
            <td class="value">{{ $member->membership_number }}</td>
        </tr>
        <tr>
            <th class="label">Baby Birth Date</th>
            <td class="value">{{ $member->baby_birth_date ?: 'Not provided' }}</td>
        </tr>
        <tr>
            <th class="label">Status</th>
            <td class="value"><span class="badge">{{ ucfirst((string) $member->nursing_mother_status) }}</span></td>
        </tr>
    </table>

    <div class="divider"></div>
    <p class="muted">Open the admin dashboard → Members → Nursing Mother to review this request.</p>
@endsection
