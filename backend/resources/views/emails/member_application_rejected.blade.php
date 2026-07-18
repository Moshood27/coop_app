@extends('layouts.mail')

@section('title', 'Membership Application Status')

@section('content')
    <p class="salam">Assalāmu ‘alaykum {{ $application->full_name }},</p>
    <p>Thank you for your interest in joining {{ config('app.name') }}. After reviewing your application, we regret to inform you that we cannot approve it at this time.</p>

    <div class="divider"></div>

    <p class="salam">Reason for rejection:</p>
    <div class="note">
        {{ $reason }}
    </div>

    <p style="margin-top:16px;">If you have any questions or would like to provide more information, you may reply to this email or contact our support team.</p>
@endsection
