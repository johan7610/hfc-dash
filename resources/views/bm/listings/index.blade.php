@extends('layouts.corex')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Branch Listing Stock</h2>
                <div class="text-sm text-white/60">Read-only view from imported Propcon stock for your branch</div>
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
    <div class="ds-status-card" style="border-left: 3px solid var(--brand-icon, #0ea5e9);">
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

    {{-- Filters --}}
    <div class="ds-status-card p-4 space-y-4">
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px]">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Status</label>
                <select name="status" class="w-full rounded-md text-sm px-3 py-1.5 transition-all duration-300" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                    <option value="active" {{ $statusFilter==='active' ? 'selected' : '' }}>Active (contains active/for sale)</option>
                    <option value="all" {{ $statusFilter==='all' ? 'selected' : '' }}>All</option>
                    <option value="sold" {{ $statusFilter==='sold' ? 'selected' : '' }}>Contains: sold</option>
                    <option value="withdrawn" {{ $statusFilter==='withdrawn' ? 'selected' : '' }}>Contains: withdrawn</option>
                    <option value="expired" {{ $statusFilter==='expired' ? 'selected' : '' }}>Contains: expired</option>
                    <option value="under offer" {{ $statusFilter==='under offer' ? 'selected' : '' }}>Contains: under offer</option>
                </select>
            </div>

            <div class="min-w-[180px]">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Mandate contains</label>
                <input type="text" name="mandate" value="{{ $mandate }}"
                       placeholder="e.g. open / sole"
                       class="w-full rounded-md text-sm px-3 py-1.5 transition-all duration-300 placeholder:opacity-50" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);" />
            </div>

            <div class="min-w-[180px]">
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Type contains</label>
                <input type="text" name="type" value="{{ $type }}"
                       placeholder="e.g. apartment"
                       class="w-full rounded-md text-sm px-3 py-1.5 transition-all duration-300 placeholder:opacity-50" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);" />
            </div>

            <div class="flex gap-2">
                <button class="corex-btn-primary text-sm">Apply</button>
                <a href="{{ route('agent.listings') }}" class="px-3 py-1.5 rounded-md text-sm transition-all duration-300" style="border: 1px solid var(--border); color: var(--text-secondary);" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                    Reset
                </a>
            </div>
        </form>

        <div class="flex flex-wrap items-start gap-4">
            <div class="flex items-center gap-2">
                <div class="text-sm font-semibold" style="color: var(--text-primary);">Mandate</div>
                <div class="flex flex-wrap gap-1.5">
                    @forelse($byMandate as $m)
                        <a href="{{ route('agent.listings', array_merge(request()->except('page'), ['mandate' => $m->label])) }}"
                           class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs transition-all duration-300" style="border: 1px solid var(--border); background: var(--surface-2); color: var(--text-secondary);" onmouseover="this.style.background='var(--surface)';this.style.borderColor='var(--border-hover)'" onmouseout="this.style.background='var(--surface-2)';this.style.borderColor='var(--border)'">
                            <span class="font-semibold" style="color: var(--text-primary);">{{ $m->c }}</span>
                            <span>{{ $m->label }}</span>
                        </a>
                    @empty
                        <span class="text-xs" style="color: var(--text-muted);">No mandate data</span>
                    @endforelse
                </div>
            </div>

            <div class="px-1 font-semibold select-none" style="color: var(--text-muted);">|</div>

            <div class="flex items-center gap-2">
                <div class="text-sm font-semibold" style="color: var(--text-primary);">Type</div>
                <div class="flex flex-wrap gap-1.5">
                    @forelse($byType as $t)
                        <a href="{{ route('agent.listings', array_merge(request()->except('page'), ['type' => $t->label])) }}"
                           class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs transition-all duration-300" style="border: 1px solid var(--border); background: var(--surface-2); color: var(--text-secondary);" onmouseover="this.style.background='var(--surface)';this.style.borderColor='var(--border-hover)'" onmouseout="this.style.background='var(--surface-2)';this.style.borderColor='var(--border)'">
                            <span class="font-semibold" style="color: var(--text-primary);">{{ $t->c }}</span>
                            <span>{{ $t->label }}</span>
                        </a>
                    @empty
                        <span class="text-xs" style="color: var(--text-muted);">No type data</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Listings Table --}}
    <div class="rounded-md overflow-hidden" style="border: 1px solid var(--border); background: var(--surface);">
        <div class="px-4 py-3 flex items-center justify-between" style="border-bottom: 1px solid var(--border);">
            <div class="text-sm font-medium" style="color: var(--text-primary);">Listings</div>
            <div class="text-xs" style="color: var(--text-muted);">{{ $listings->total() }} total (page {{ $listings->currentPage() }} of {{ $listings->lastPage() }})</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Status</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Mandate</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Type</th>
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">DOM</th>
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Since edit</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Expiry</th>
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Price</th>
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">CMA (R)</th>
                        <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Ref</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($listings as $l)
                    <tr style="background: var(--surface-2);">
                        <td colspan="9" class="px-4 py-2.5">
                            <div class="font-semibold" style="color: var(--text-primary);">
                                {{ trim(preg_replace('/\s+/', ' ', str_replace(["\r","\n"], ' ', (string)($l->property ?? '')))) ?: ($l->region ?: '(no address)') }}
                            </div>
                        </td>
                    </tr>

                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <td class="px-4 py-2.5" style="color: var(--text-secondary);">{{ $l->status }}</td>
                        <td class="px-4 py-2.5" style="color: var(--text-secondary);">{{ $l->mandate }}</td>
                        <td class="px-4 py-2.5" style="color: var(--text-secondary);">{{ $l->type }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold" style="color: var(--text-primary);">
                            {{ $l->days_on_market !== null ? (int)$l->days_on_market : "—" }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-semibold" style="color: var(--text-primary);">
                            {{ $l->days_since_edit !== null ? (int)$l->days_since_edit : "—" }}
                        </td>
                        <td class="px-4 py-2.5 text-xs">
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
                        <td class="px-4 py-2.5 text-right font-semibold" style="color: var(--text-primary);">
                            @if($l->price_cents !== null)
                                R {{ number_format($l->price_cents/100, 0) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @php
                                $cmaVal = $l->cma_price_cents !== null ? number_format($l->cma_price_cents/100, 0, '.', '') : '';
                            @endphp
                            <div class="text-right">
                                @if($cmaVal !== '')
                                    <div class="font-semibold" style="color: var(--text-primary);">R {{ number_format((float)$cmaVal, 0) }}</div>
                                @else
                                    —
                                @endif
                                @if($l->cma_updated_at)
                                    <div class="text-[10px] mt-0.5" style="color: var(--text-muted);">
                                        updated {{ is_string($l->cma_updated_at) ? substr($l->cma_updated_at,0,10) : $l->cma_updated_at->format('Y-m-d') }}
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-right text-xs" style="color: var(--text-muted);">
                            {{ $l->external_ref ?? $l->external_id ?? '—' }}
                        </td>
                    </tr>

                    <tr class="h-2"><td colspan="9"></td></tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center" style="color: var(--text-muted);">
                            No listings found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3" style="border-top: 1px solid var(--border);">
            {{ $listings->links() }}
        </div>
    </div>

</div>
@endsection
