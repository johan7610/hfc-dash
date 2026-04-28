@component('mail::message')
# A manager has nudged you

**From:** {{ $manager->name }}
**Category:** {{ str_replace('_', ' ', $nudge->category) }}
**Sent:** {{ $nudge->sent_at?->format('Y-m-d H:i') }}

{{ $nudge->message }}

@component('mail::button', ['url' => url('/dashboard')])
Open CoreX OS
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
