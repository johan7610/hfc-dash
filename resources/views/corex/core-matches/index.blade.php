@extends('layouts.corex')

@section('corex-content')
<div class="space-y-5">

    {{-- Page header --}}
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold" style="color:var(--text-primary);">Core Matches</h1>
            <p class="text-xs mt-0.5" style="color:var(--text-muted);">Buyer & renter criteria saved against your contacts.</p>
        </div>
        <a href="{{ route('corex.contacts.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold no-underline"
           style="background:var(--surface); border:1px solid var(--border); color:var(--text-secondary);"
           onmouseover="this.style.borderColor='#00b4d8'" onmouseout="this.style.borderColor='var(--border)'">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
            Contacts
        </a>
    </div>

    @if($contacts->isEmpty())
    {{-- Empty state --}}
    <div class="rounded-2xl py-20 text-center" style="background:var(--surface); border:1px solid var(--border);">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-12 h-12 mx-auto mb-4 opacity-20" style="color:var(--text-muted);"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
        <p class="text-base font-bold" style="color:var(--text-muted);">No Core Matches saved yet.</p>
        <p class="text-sm mt-1.5" style="color:var(--text-muted); opacity:.7;">Open a contact and go to the Core Matches tab to add buyer criteria.</p>
        <a href="{{ route('corex.contacts.index') }}"
           class="inline-flex items-center gap-1.5 mt-5 px-4 py-2 rounded-lg text-sm font-semibold no-underline"
           style="background:rgba(0,180,216,0.1); color:#00b4d8; border:1px solid rgba(0,180,216,0.25);">
            Go to Contacts
        </a>
    </div>

    @else
    <div class="space-y-4">
        @foreach($contacts as $row)
        @php
            $contact = $row['contact'];
            $matches = $row['matches'];
        @endphp
        <div class="rounded-2xl overflow-hidden" style="background:var(--surface); border:1px solid var(--border);">

            {{-- Contact header --}}
            <div class="flex items-center justify-between gap-3 px-5 py-3" style="background:var(--surface-2); border-bottom:1px solid var(--border);">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                         style="background:{{ $contact->type?->color ?? '#334155' }};">
                        {{ $contact->initials }}
                    </div>
                    <div>
                        <a href="{{ route('corex.contacts.show', $contact) }}?tab=matches"
                           class="text-sm font-bold no-underline"
                           style="color:var(--text-primary);"
                           onmouseover="this.style.color='#00b4d8'" onmouseout="this.style.color='var(--text-primary)'">
                            {{ $contact->full_name }}
                        </a>
                        @if($contact->type)
                        <span class="ml-2 text-[10px] px-1.5 py-0.5 rounded-full font-semibold"
                              style="background:{{ $contact->type->color }}22; color:{{ $contact->type->color }}; border:1px solid {{ $contact->type->color }}44;">
                            {{ $contact->type->name }}
                        </span>
                        @endif
                        @if($contact->phone)
                        <div class="text-[10px] mt-0.5" style="color:var(--text-muted);">{{ $contact->phone }}</div>
                        @endif
                    </div>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded-full flex-shrink-0"
                      style="background:rgba(0,180,216,0.1); color:#00b4d8; border:1px solid rgba(0,180,216,0.2);">
                    {{ $matches->count() }} {{ Str::plural('match', $matches->count()) }}
                </span>
            </div>

            {{-- Match rows --}}
            <div class="divide-y" style="border-color:var(--border);">
                @foreach($matches as $match)
                <div class="flex items-center gap-4 px-5 py-3 flex-wrap">

                    {{-- Type badge --}}
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full flex-shrink-0"
                          style="{{ $match->listing_type === 'rental' ? 'background:rgba(168,85,247,0.12); color:#a855f7; border:1px solid rgba(168,85,247,0.25);' : 'background:rgba(0,180,216,0.12); color:#00b4d8; border:1px solid rgba(0,180,216,0.25);' }}">
                        {{ $match->listingTypeLabel() }}
                    </span>

                    {{-- Criteria chips --}}
                    <div class="flex items-center gap-2 flex-wrap flex-1 min-w-0 text-xs" style="color:var(--text-secondary);">
                        @if($match->price_min || $match->price_max)
                        <span class="font-semibold" style="color:var(--text-primary);">{{ $match->priceRangeLabel() }}</span>
                        <span style="color:var(--border);">·</span>
                        @endif
                        @if($match->suburb)
                        <span>📍 {{ $match->suburb }}</span>
                        <span style="color:var(--border);">·</span>
                        @endif
                        @if($match->category)
                        <span>{{ $match->category }}</span>
                        @endif
                        @if($match->property_type)
                        <span>{{ $match->property_type }}</span>
                        @endif
                        @foreach([[$match->beds_min,'Beds'],[$match->baths_min,'Baths'],[$match->garages_min,'Gar']] as [$val,$lbl])
                        @if($val !== null)
                        <span class="px-1.5 py-0.5 rounded" style="background:var(--surface-2);">{{ $val }}+ {{ $lbl }}</span>
                        @endif
                        @endforeach
                        @if(!$match->category && !$match->property_type && !$match->suburb && !$match->price_min && !$match->price_max && !$match->beds_min && !$match->baths_min)
                        <span style="color:var(--text-muted);">Any property</span>
                        @endif
                    </div>

                    {{-- View matches button --}}
                    <a href="{{ route('corex.contacts.matches.results', [$contact, $match]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold no-underline flex-shrink-0"
                       style="background:rgba(0,180,216,0.08); color:#00b4d8; border:1px solid rgba(0,180,216,0.2);"
                       onmouseover="this.style.background='rgba(0,180,216,0.18)'" onmouseout="this.style.background='rgba(0,180,216,0.08)'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
                        View Matches
                    </a>
                </div>
                @endforeach
            </div>

        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
