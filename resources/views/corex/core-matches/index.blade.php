@extends('layouts.corex')

@section('corex-content')
@php
    $totalMatches = $contacts->sum(fn($row) => $row['matches']->count());
@endphp
<div class="space-y-5">

    {{-- Page header --}}
    <div class="rounded-md px-6 py-5 flex items-center justify-between gap-4 flex-wrap"
         style="background:var(--brand-default,#0b2a4a);">
        <div>
            <h2 class="text-xl font-bold text-white tracking-tight">Core Matches</h2>
            <p class="text-sm mt-0.5" style="color:rgba(255,255,255,0.55);">Buyer and renter search criteria saved against your contacts.</p>
        </div>
        <div class="flex items-center gap-3">
            @if($totalMatches > 0)
            <div class="text-right">
                <div class="text-2xl font-bold leading-tight" style="color:var(--brand-icon,#0ea5e9);">{{ $totalMatches }}</div>
                <div class="text-[10px] font-semibold uppercase tracking-wider" style="color:rgba(255,255,255,0.4);">{{ Str::plural('search', $totalMatches) }}</div>
            </div>
            <div style="width:1px; height:36px; background:rgba(255,255,255,0.12);"></div>
            <div class="text-right">
                <div class="text-2xl font-bold leading-tight text-white">{{ $contacts->count() }}</div>
                <div class="text-[10px] font-semibold uppercase tracking-wider" style="color:rgba(255,255,255,0.4);">{{ Str::plural('contact', $contacts->count()) }}</div>
            </div>
            @endif
            <a href="{{ route('corex.contacts.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-md text-xs font-semibold no-underline ml-2 transition-all duration-300"
               style="background:rgba(255,255,255,0.08); color:rgba(255,255,255,0.7); border:1px solid rgba(255,255,255,0.15);"
               onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                Contacts
            </a>
        </div>
    </div>

    @if($contacts->isEmpty())
    {{-- Empty state --}}
    <div class="rounded-md py-20 text-center" style="background:var(--surface); border:1px solid var(--border);">
        <div class="w-14 h-14 rounded-md mx-auto mb-4 flex items-center justify-center"
             style="background:color-mix(in srgb, var(--brand-icon,#0ea5e9) 8%, transparent); border:1px solid color-mix(in srgb, var(--brand-icon,#0ea5e9) 15%, transparent);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7" style="color:var(--brand-icon,#0ea5e9); opacity:.6;"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
        </div>
        <p class="text-base font-bold" style="color:var(--text-muted);">No Core Matches saved yet.</p>
        <p class="text-sm mt-1.5 max-w-xs mx-auto" style="color:var(--text-muted); opacity:.7;">Open a contact and go to the Core Matches tab to save buyer or renter criteria.</p>
        <a href="{{ route('corex.contacts.index') }}"
           class="inline-flex items-center gap-1.5 mt-6 px-4 py-2 rounded-md text-sm font-semibold no-underline transition-all duration-300"
           style="background:color-mix(in srgb, var(--brand-button,#0ea5e9) 10%, transparent); color:var(--brand-button,#0ea5e9); border:1px solid color-mix(in srgb, var(--brand-button,#0ea5e9) 25%, transparent);">
            Go to Contacts →
        </a>
    </div>

    @else
    <div class="space-y-3">
        @foreach($contacts as $row)
        @php
            $contact = $row['contact'];
            $matches = $row['matches'];
        @endphp

        <div class="rounded-md overflow-hidden" style="background:var(--surface); border:1px solid var(--border);">

            {{-- Contact header --}}
            <div class="flex items-center justify-between gap-3 px-5 py-4"
                 style="background:var(--surface-2); border-bottom:1px solid var(--border);">
                <div class="flex items-center gap-3 min-w-0">
                    {{-- Avatar --}}
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold text-white flex-shrink-0"
                         style="background:var(--brand-icon,#0ea5e9);">
                        {{ $contact->initials }}
                    </div>
                    {{-- Name + meta --}}
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('corex.contacts.show', $contact) }}?tab=matches"
                               class="text-sm font-semibold no-underline leading-tight transition-all duration-300"
                               style="color:var(--text-primary);"
                               onmouseover="this.style.color='var(--brand-icon,#0ea5e9)'" onmouseout="this.style.color='var(--text-primary)'">
                                {{ $contact->full_name }}
                            </a>
                            @if($contact->type)
                            <span class="text-xs px-2 py-0.5 rounded-md font-medium flex-shrink-0"
                                  style="background:color-mix(in srgb, var(--brand-icon,#0ea5e9) 12%, transparent); color:var(--brand-icon,#0ea5e9); border:1px solid color-mix(in srgb, var(--brand-icon,#0ea5e9) 25%, transparent);">
                                {{ $contact->type->name }}
                            </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 mt-0.5 flex-wrap">
                            @if($contact->phone)
                            <span class="text-xs" style="color:var(--text-secondary);">{{ $contact->phone }}</span>
                            @endif
                            @if($contact->email)
                            <span class="text-xs" style="color:var(--text-secondary);">{{ $contact->email }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <span class="text-xs font-bold px-2.5 py-1 rounded-md"
                          style="background:color-mix(in srgb, var(--brand-icon,#0ea5e9) 10%, transparent); color:var(--brand-icon,#0ea5e9); border:1px solid color-mix(in srgb, var(--brand-icon,#0ea5e9) 20%, transparent);">
                        {{ $matches->count() }} {{ Str::plural('search', $matches->count()) }}
                    </span>
                </div>
            </div>

            {{-- Match rows --}}
            <div>
                @foreach($matches as $match)
                <div class="flex items-center gap-4 px-5 py-3.5 flex-wrap"
                     style="{{ !$loop->last ? 'border-bottom:1px solid var(--border);' : '' }}">

                    {{-- Type pill --}}
                    <span class="text-xs font-bold px-2.5 py-1 rounded-md flex-shrink-0 whitespace-nowrap"
                          style="{{ $match->listing_type === 'rental'
                              ? 'background:rgba(168,85,247,0.10); color:#a855f7; border:1px solid rgba(168,85,247,0.22);'
                              : 'background:color-mix(in srgb, var(--brand-icon,#0ea5e9) 10%, transparent); color:var(--brand-icon,#0ea5e9); border:1px solid color-mix(in srgb, var(--brand-icon,#0ea5e9) 22%, transparent);' }}">
                        {{ $match->listingTypeLabel() }}
                    </span>

                    {{-- Criteria --}}
                    <div class="flex items-center gap-1.5 flex-wrap flex-1 min-w-0">
                        @if($match->price_min || $match->price_max)
                        <span class="text-xs font-bold" style="color:var(--text-primary);">{{ $match->priceRangeLabel() }}</span>
                        <span class="text-xs" style="color:var(--text-muted);">·</span>
                        @endif

                        @if($match->suburb)
                        <span class="text-xs font-medium" style="color:var(--text-secondary);">📍 {{ $match->suburb }}</span>
                        <span class="text-xs" style="color:var(--text-muted);">·</span>
                        @endif

                        @if($match->category)
                        <span class="text-xs px-2 py-0.5 rounded-md font-medium" style="background:var(--surface-2); color:var(--text-secondary); border:1px solid var(--border);">{{ $match->category }}</span>
                        @endif

                        @if($match->property_type)
                        <span class="text-xs px-2 py-0.5 rounded-md font-medium" style="background:var(--surface-2); color:var(--text-secondary); border:1px solid var(--border);">{{ $match->property_type }}</span>
                        @endif

                        @foreach([[$match->beds_min,'Beds'],[$match->baths_min,'Baths'],[$match->garages_min,'Gar']] as [$val,$lbl])
                        @if($val !== null)
                        <span class="text-xs px-2 py-0.5 rounded-md font-medium" style="background:var(--surface-2); color:var(--text-secondary); border:1px solid var(--border);">{{ $val }}+ {{ $lbl }}</span>
                        @endif
                        @endforeach

                        @if(!$match->category && !$match->property_type && !$match->suburb && !$match->price_min && !$match->price_max && !$match->beds_min && !$match->baths_min)
                        <span class="text-xs italic" style="color:var(--text-muted);">Any property</span>
                        @endif
                    </div>

                    {{-- Action --}}
                    <a href="{{ route('corex.contacts.matches.results', [$contact, $match]) }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-md text-xs font-semibold no-underline flex-shrink-0 whitespace-nowrap transition-all duration-300"
                       style="background:color-mix(in srgb, var(--brand-button,#0ea5e9) 8%, transparent); color:var(--brand-button,#0ea5e9); border:1px solid color-mix(in srgb, var(--brand-button,#0ea5e9) 20%, transparent);"
                       onmouseover="this.style.background='color-mix(in srgb, var(--brand-button,#0ea5e9) 16%, transparent)'; this.style.borderColor='color-mix(in srgb, var(--brand-button,#0ea5e9) 40%, transparent)'"
                       onmouseout="this.style.background='color-mix(in srgb, var(--brand-button,#0ea5e9) 8%, transparent)'; this.style.borderColor='color-mix(in srgb, var(--brand-button,#0ea5e9) 20%, transparent)'">
                        View Matches
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
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
