@extends('layouts.corex')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight">My Listing Stock</h2>
                <div class="text-sm text-white/60">Read-only view from imported Propcon stock</div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-wide text-white/60">Active listings</div>
                <div class="text-2xl font-bold text-white">{{ number_format((int)($summary->listing_count ?? 0)) }}</div>
                <div class="text-xs text-white/60">Value: R {{ number_format(((int)($summary->total_price_cents ?? 0))/100, 0) }}</div>
            </div>
        </div>
    </div>

    {{-- Context Card --}}
    @if(!empty($context))
    <div class="ds-status-card" style="border-left-color: var(--brand-default, #0b2a4a);">
        <div class="flex items-center justify-between gap-6">
            <div class="min-w-0">
                <div class="ds-label">{{ strtoupper((string)($context['filter'] ?? 'view')) }}</div>
                <div class="ds-value text-xl">{{ $context['title'] ?? 'Listings' }}</div>
                <div class="text-sm mt-1" style="color: var(--text-muted);">{{ $context['note'] ?? '' }}</div>
            </div>
            <div class="text-right shrink-0">
                <div class="ds-label">Count</div>
                <div class="ds-value-lg">{{ (int)($context['count'] ?? 0) }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Listings Table --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">

        {{-- Table Header Bar --}}
        <div class="px-4 py-3 flex items-center justify-between" style="border-bottom: 1px solid var(--border);">
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold" style="color: var(--text-primary);">Listings</span>
                <span class="text-xs" style="color: var(--text-muted);">{{ $listings->total() }} total &middot; page {{ $listings->currentPage() }} of {{ $listings->lastPage() }}</span>
            </div>
        </div>

        {{-- Mandate & Type Summary Pills --}}
        <div class="px-4 py-2 flex flex-wrap items-start gap-4" style="border-bottom: 1px solid var(--border);">
            <div class="flex items-center gap-2">
                <div class="text-xs font-semibold" style="color: var(--text-primary);">Mandate</div>
                <div class="flex flex-wrap gap-1.5">
                    @forelse($byMandate as $m)
                        <a href="{{ route('agent.listings', array_merge(request()->except('page'), ['mandate' => $m->label])) }}"
                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs transition-all duration-300"
                           style="background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border);">
                            <span class="font-semibold" style="color: var(--text-primary);">{{ $m->c }}</span>
                            <span>{{ $m->label }}</span>
                        </a>
                    @empty
                        <span class="text-xs" style="color: var(--text-muted);">None</span>
                    @endforelse
                </div>
            </div>

            <div style="color: var(--text-muted);" class="select-none">|</div>

            <div class="flex items-center gap-2">
                <div class="text-xs font-semibold" style="color: var(--text-primary);">Type</div>
                <div class="flex flex-wrap gap-1.5">
                    @forelse($byType as $t)
                        <a href="{{ route('agent.listings', array_merge(request()->except('page'), ['type' => $t->label])) }}"
                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs transition-all duration-300"
                           style="background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border);">
                            <span class="font-semibold" style="color: var(--text-primary);">{{ $t->c }}</span>
                            <span>{{ $t->label }}</span>
                        </a>
                    @empty
                        <span class="text-xs" style="color: var(--text-muted);">None</span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-3 py-2">Status</th>
                        <th class="text-left px-3 py-2">Mandate</th>
                        <th class="text-left px-3 py-2">Type</th>
                        <th class="text-right px-3 py-2">DOM</th>
                        <th class="text-right px-3 py-2">Since edit</th>
                        <th class="text-left px-3 py-2">Expiry</th>
                        <th class="text-right px-3 py-2">Price</th>
                        <th class="text-right px-3 py-2">CMA (R)</th>
                        <th class="text-right px-3 py-2">Ref</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($listings as $l)
                @php
                    $addressText = trim(preg_replace('/\s+/', ' ', str_replace(["\r","\n"], ' ', (string)($l->property ?? '')))) ?: ($l->region ?: '(no address)');
                    $cmaText = $l->cma_price_cents !== null ? number_format($l->cma_price_cents/100, 0, '.', '') : '';
                    $refText = $l->external_ref ?? $l->external_id ?? '';
                @endphp

                {{-- Address row --}}
                <tr style="background: var(--surface-2);">
                    <td colspan="9" class="px-3 py-2">
                        <div class="font-semibold" style="color: var(--text-primary);">{{ $addressText }}</div>
                    </td>
                </tr>

                {{-- Data row --}}
                <tr>
                    <td class="px-3 py-2" style="color: var(--text-secondary);">{{ $l->status }}</td>
                    <td class="px-3 py-2" style="color: var(--text-secondary);">{{ $l->mandate }}</td>
                    <td class="px-3 py-2" style="color: var(--text-secondary);">{{ $l->type }}</td>
                    <td class="px-3 py-2 text-right font-semibold" style="color: var(--text-primary);">
                        {{ $l->days_on_market !== null ? (int)$l->days_on_market : "—" }}
                    </td>
                    <td class="px-3 py-2 text-right font-semibold" style="color: var(--text-primary);">
                        {{ $l->days_since_edit !== null ? (int)$l->days_since_edit : "—" }}
                    </td>
                    <td class="px-3 py-2 text-xs">
                        @if($l->expires_at)
                            <div class="font-medium" style="color: var(--text-primary);">{{ $l->expires_on }}</div>
                            @php $dte = $l->days_to_expiry; @endphp
                            @if(!is_null($dte))
                                @if($dte < 0)
                                    <div style="color: var(--text-muted);">expired {{ abs((int)$dte) }}d ago</div>
                                @else
                                    <div style="color: var(--text-muted);">in {{ (int)$dte }}d</div>
                                @endif
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right font-semibold">
                        @if($l->price_cents !== null)
                            R {{ number_format($l->price_cents/100, 0) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right">
                        <form method="POST" action="{{ route('agent.listings.cma', $l) }}" class="flex items-center justify-end gap-2">
                            @csrf
                            <input name="cma_value"
                                   value="{{ $cmaText }}"
                                   placeholder="e.g. 1250000"
                                   class="w-28 text-right rounded-md px-2 py-1 text-xs transition-all duration-300"
                                   style="background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border);" />
                            <button class="px-2 py-1 rounded-md text-xs font-semibold text-white transition-all duration-300"
                                    style="background: var(--brand-default, #0b2a4a);">
                                Save
                            </button>
                        </form>
                        @if($l->cma_updated_at)
                            <div class="text-[10px] mt-1" style="color: var(--text-muted);">
                                updated {{ is_string($l->cma_updated_at) ? substr($l->cma_updated_at,0,10) : $l->cma_updated_at->format('Y-m-d') }}
                            </div>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right text-xs" style="color: var(--text-muted);">
                        {{ $refText ?: '—' }}
                    </td>
                </tr>

                {{-- Separator --}}
                <tr><td colspan="9" class="h-0.5" style="border-bottom: 1px solid var(--border);"></td></tr>

                @empty
                <tr>
                    <td colspan="9" class="px-4 py-6 text-center" style="color: var(--text-muted);">
                        No listings found.
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-3 py-2" style="border-top: 1px solid var(--border);">
            {{ $listings->links() }}
        </div>
    </div>

</div>
@endsection
