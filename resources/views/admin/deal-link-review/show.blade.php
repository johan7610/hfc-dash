@extends('layouts.corex-app')

@section('corex-content')
<div style="max-width:1100px;margin:0 auto;padding:0 20px;">

    <div style="margin-bottom:14px;">
        <a href="{{ route('corex.admin.deal-link-review.index') }}"
           style="font-size:0.75rem;color:var(--text-muted);text-decoration:none;">← Back to queue</a>
        <h1 style="font-size:1.25rem;font-weight:600;color:var(--text-primary);margin:6px 0 0 0;">
            Review match for: {{ $deal?->property_address }}
        </h1>
    </div>

    @if($errors->any())
        <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:6px;padding:10px 14px;font-size:0.8125rem;margin-bottom:14px;">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Deal summary --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:14px;margin-bottom:14px;">
        <h2 class="ds-section-header" style="margin:0 0 8px 0;">Deal details</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;font-size:0.8125rem;">
            <div><div style="font-size:0.625rem;color:var(--text-muted);text-transform:uppercase;">Deal #</div>{{ $deal?->deal_no ?? '—' }}</div>
            <div><div style="font-size:0.625rem;color:var(--text-muted);text-transform:uppercase;">Deal date</div>{{ $deal?->deal_date?->format('j M Y') ?: '—' }}</div>
            <div><div style="font-size:0.625rem;color:var(--text-muted);text-transform:uppercase;">Registration</div>{{ $deal?->registration_date?->format('j M Y') ?: '—' }}</div>
            <div><div style="font-size:0.625rem;color:var(--text-muted);text-transform:uppercase;">Sale price</div>@if($deal?->sale_price)R {{ number_format((int) $deal->sale_price) }}@elseif($deal?->property_value)R {{ number_format((float) $deal->property_value, 0) }}@else —@endif</div>
            <div><div style="font-size:0.625rem;color:var(--text-muted);text-transform:uppercase;">Seller</div>{{ $deal?->seller_name ?: '—' }}</div>
            <div><div style="font-size:0.625rem;color:var(--text-muted);text-transform:uppercase;">Buyer</div>{{ $deal?->buyer_name ?: '—' }}</div>
        </div>
    </div>

    {{-- Candidate properties --}}
    <div style="margin-bottom:14px;">
        <h2 class="ds-section-header" style="margin:0 0 8px 0;">Candidate properties ({{ $candidates->count() }})</h2>
        @if($candidates->isEmpty())
            <div style="padding:16px;background:var(--surface);border:1px dashed var(--border);border-radius:6px;color:var(--text-muted);font-size:0.875rem;">
                No candidates were found. Use the manual search below to pick a property anyway.
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:10px;">
                @foreach($candidates as $cand)
                    @php $prop = $properties->get($cand['property_id'] ?? null); @endphp
                    <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:12px 14px;display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center;">
                        <div>
                            <div style="font-weight:600;color:var(--text-primary);font-size:0.875rem;">
                                {{ $cand['address'] ?? ($prop?->address ?? 'unknown') }}
                            </div>
                            <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:3px;">
                                @if($cand['suburb'] ?? null){{ $cand['suburb'] }} · @endif
                                Property #{{ $cand['property_id'] }}
                                @if($prop)
                                    · Status: {{ $prop->status }}
                                    @if($prop->price) · Listed at R {{ number_format((float) $prop->price, 0) }}@endif
                                    @if($prop->last_activity_at) · Active {{ \Carbon\Carbon::parse($prop->last_activity_at)->diffForHumans() }}@endif
                                @endif
                            </div>
                            <div style="font-size:0.6875rem;color:var(--text-muted);margin-top:3px;">
                                Match score: <strong>{{ $cand['score'] ?? 0 }}</strong> ({{ $cand['confidence'] ?? '?' }})
                                @if(!empty($cand['date_match']))· date proximity confirmed @endif
                            </div>
                        </div>
                        <div>
                            <form method="POST" action="{{ route('corex.admin.deal-link-review.link', $item->id) }}">
                                @csrf
                                <input type="hidden" name="property_id" value="{{ $cand['property_id'] }}">
                                <button type="submit" class="corex-btn-primary" style="font-size:0.75rem;padding:7px 14px;">Link this property →</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Manual search --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:14px;margin-bottom:14px;">
        <h2 class="ds-section-header" style="margin:0 0 8px 0;">Or link manually</h2>
        <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 10px 0;">
            Paste the property ID if you already know it (visible on /corex/properties/N).
        </p>
        <form method="POST" action="{{ route('corex.admin.deal-link-review.link', $item->id) }}" style="display:flex;gap:10px;align-items:center;">
            @csrf
            <input type="number" name="property_id" required min="1" placeholder="Property ID"
                   style="padding:6px 10px;border:1px solid var(--border);border-radius:4px;font-size:0.8125rem;width:160px;">
            <input type="text" name="review_note" maxlength="2000" placeholder="Optional note explaining the pick"
                   style="padding:6px 10px;border:1px solid var(--border);border-radius:4px;font-size:0.8125rem;flex:1;">
            <button type="submit" class="corex-btn-primary" style="font-size:0.75rem;padding:7px 14px;">Link</button>
        </form>
    </div>

    {{-- Resolve without linking --}}
    <div style="display:flex;gap:8px;justify-content:flex-end;">
        <form method="POST" action="{{ route('corex.admin.deal-link-review.skip', $item->id) }}">
            @csrf
            <button type="submit" style="font-size:0.75rem;padding:7px 14px;border:1px solid var(--border);background:transparent;color:var(--text-secondary);border-radius:4px;cursor:pointer;">
                Defer for later
            </button>
        </form>
        <form method="POST" action="{{ route('corex.admin.deal-link-review.unlink', $item->id) }}">
            @csrf
            <button type="submit" style="font-size:0.75rem;padding:7px 14px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:4px;cursor:pointer;">
                None of these — mark unmatched
            </button>
        </form>
    </div>
</div>
@endsection
