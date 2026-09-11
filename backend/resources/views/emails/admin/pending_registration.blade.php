@component('mail::message')
# New Membership Registration Submitted

A new applicant has completed their registration process and is currently pending admin approval.

**Applicant Details:**
- **Name:** {{ $user->full_name }}
- **Email:** {{ $user->email }}
- **Phone:** {{ $user->phone }}
- **Branch:** {{ $user->branch?->name ?? 'N/A' }}
- **Membership ID:** {{ $user->membership_number }}

Please log in to the admin panel to review the application documents and approve or reject the request.

@component('mail::button', ['url' => $url])
Review Application
@endcomponent

Barakallahu Feekum,<br>
{{ config('app.name') }}
@endcomponent
