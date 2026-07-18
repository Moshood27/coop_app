@extends('layouts.mail')

@section('title', 'Interview Invitation - ' . config('app.name'))

@section('content')
    <p class="salam">Assalāmu ‘alaykum {{ $name }},</p>
    <p>We are pleased to invite you to a meeting regarding your membership application with <strong>{{ config('app.name') }}</strong>.</p>

    <div class="divider"></div>
    <p><strong>Meeting Details:</strong></p>
    <p><span class="detail-label">Type:</span> {{ ucfirst($meetingType) }}</p>
    <p><span class="detail-label">Date & Time:</span> {{ $meetingDateTime }}</p>
    @if($meetingLocationOrLink)
        <p><span class="detail-label">{{ $meetingType === 'online' ? 'Meeting Link' : 'Location' }}:</span>
            @if($meetingType === 'online')
                <a href="{{ $meetingLocationOrLink }}">{{ $meetingLocationOrLink }}</a>
            @else
                {{ $meetingLocationOrLink }}
            @endif
        </p>
    @endif

    @if(!empty($customMessage))
        <div class="divider"></div>
        <p><strong>Additional Message:</strong></p>
        <p style="font-style: italic;">"{{ $customMessage }}"</p>
    @endif
    <div class="divider"></div>

    <p>This meeting is part of our approval process to better understand your interests and how we can best serve you within the cooperative.</p>

    <p>If you have any questions or need to reschedule, please contact us.</p>
@endsection
