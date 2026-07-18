@extends('layouts.mail')

@section('title', 'Loan Agreement Verified')

@section('content')
    <p class="salam">Great news, {{ $member->name ?? 'Member' }}!</p>
    <p>Your signed loan agreement for <strong>{{ $loan->qard_id_string }}</strong> has been verified by our team.</p>

    <p>Your loan is now being processed for final disbursement. You will be notified once the funds are credited to your wallet.</p>

    <p><strong>Next Steps:</strong></p>
    <ul>
        <li>Keep an eye on your wallet balance.</li>
        <li>Review your repayment schedule in the app.</li>
    </ul>

    <p>Thank you for your cooperation!</p>
@endsection
