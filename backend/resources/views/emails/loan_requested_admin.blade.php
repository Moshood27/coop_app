@extends('layouts.mail')

@section('title', 'New Loan Request')

@section('content')
    <p>A member has requested a new loan and it requires admin review.</p>
    <table class="meta" role="presentation">
        <tr>
            <th class="label">Loan ID</th>
            <td class="value">{{ $loan->qard_id_string }}</td>
        </tr>
        <tr>
            <th class="label">Member</th>
            <td class="value">{{ $member?->full_name }} ({{ $member?->email }})</td>
        </tr>
        <tr>
            <th class="label">Principal</th>
            <td class="value">₦{{ number_format((float) $loan->principal_amount, 2) }}</td>
        </tr>
        <tr>
            <th class="label">Installments</th>
            <td class="value">{{ $loan->total_installments }} × ₦{{ number_format((float) $loan->per_installment, 2) }} ({{ ucfirst((string) $loan->interval) }})</td>
        </tr>
        <tr>
            <th class="label">Status</th>
            <td class="value"><span class="badge">{{ ucfirst((string) $loan->status) }}</span></td>
        </tr>
        @if($loan->relationLoaded('guarantors') || $loan->guarantors()->exists())
        <tr>
            <th class="label">Guarantors</th>
            <td class="value">
                @php($gs = $loan->guarantors()->get())
                @if($gs->isEmpty())
                    -
                @else
                    {{ $gs->map(fn($u) => $u->full_name)->filter()->implode(', ') }}
                @endif
            </td>
        </tr>
        @endif
    </table>

    <div class="divider"></div>
    <p class="muted">Open the admin dashboard → Loans to approve, reject (with reason), or disburse when ready.</p>
@endsection
