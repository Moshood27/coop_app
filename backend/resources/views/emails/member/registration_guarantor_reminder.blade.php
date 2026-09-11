@component('mail::message')
# Assalamu Alaikum {{ $guarantorName }},

A new applicant, **{{ $applicantName }}**, has requested you to be their guarantor for membership in our Cooperative.

Acting as a guarantor is a significant responsibility in Islam, involving a testimony of the applicant's character and trustworthiness.

### The Islamic Testimony:
*"{{ $testimony }}"*

By accepting this request, you are providing this testimony and vouching for the applicant.

Please log in to the app to review and accept or decline this request.

@component('mail::button', ['url' => config('app.url') . '/login'])
Log in to App
@endcomponent

If you do not recognize this person or cannot vouch for them, you may decline the request in the app.

Barakallahu Feekum,<br>
{{ config('app.name') }}
@endcomponent
