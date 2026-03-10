@extends('layouts.corex')

@section('corex-content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-5">
        <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Active Leases</h2>
        <div class="text-sm text-white/60 mt-1">
            <a href="{{ route('rental.dashboard') }}" class="text-white/60 hover:text-white transition-all duration-300">&larr; Rentals</a>
            &middot; Completed and signed lease agreements.
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-md px-4 py-3 text-sm" style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-3">
        @forelse($leases as $lease)
            @php
                $daysLeft = $lease->daysUntilExpiry();
                $rental = number_format((float) $lease->rental_amount, 0, '.', ' ');
                $urgencyBadge = match(true) {
                    $daysLeft <= 0  => 'bg-red-100 text-red-800',
                    $daysLeft <= 30 => 'bg-red-100 text-red-700',
                    $daysLeft <= 90 => 'bg-amber-100 text-amber-700',
                    default         => 'bg-green-100 text-green-700',
                };
                $borderAccent = match(true) {
                    $daysLeft <= 0  => '#ef4444',
                    $daysLeft <= 30 => '#f87171',
                    $daysLeft <= 90 => '#f59e0b',
                    default         => '#22c55e',
                };
            @endphp
            <div class="rounded-md p-5 transition-all duration-300"
                 style="background: var(--surface); border: 1px solid var(--border); border-left: 3px solid {{ $borderAccent }};"
                 onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='var(--surface)'">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold" style="color: var(--text-primary);">{{ $lease->property_address ?: ($lease->document->name ?? 'Unnamed') }}</div>
                        <div class="text-xs mt-2 space-y-1" style="color: var(--text-secondary);">
                            <p><span class="font-medium" style="color: var(--text-primary);">Tenant:</span> {{ $lease->tenant_name ?? '—' }}</p>
                            <p><span class="font-medium" style="color: var(--text-primary);">Landlord:</span> {{ $lease->landlord_name ?? '—' }}</p>
                            @if($lease->rental_amount)
                            <p><span class="font-medium" style="color: var(--text-primary);">Rental:</span> R {{ $rental }}/mo</p>
                            @endif
                            @if($lease->lease_start_date)
                            <p><span class="font-medium" style="color: var(--text-primary);">Start:</span> {{ $lease->lease_start_date->format('d M Y') }}</p>
                            @endif
                        </div>
                        @if($lease->lease_end_date)
                        <div class="mt-3">
                            <span class="inline-block px-2.5 py-0.5 rounded-md text-[11px] font-semibold {{ $urgencyBadge }}">
                                @if($daysLeft <= 0)
                                    EXPIRED — {{ $lease->lease_end_date->format('d M Y') }}
                                @else
                                    Expires {{ $lease->lease_end_date->format('d M Y') }} ({{ $daysLeft }} days)
                                @endif
                            </span>
                        </div>
                        @endif
                        @if($lease->signatureTemplate && $lease->signatureTemplate->completed_at)
                            <div class="text-[11px] text-green-500 mt-1.5">
                                Signed {{ $lease->signatureTemplate->completed_at->format('d M Y') }}
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2 ml-4 shrink-0">
                        @if($lease->document)
                            <a href="{{ route('docuperfect.signatures.audit', $lease->document) }}"
                               class="corex-btn-outline text-xs px-3 py-1.5 text-center transition-all duration-300">
                                Audit
                            </a>
                        @endif
                        @if($lease->signatureTemplate && $lease->signatureTemplate->signed_pdf_path)
                            <a href="{{ route('docuperfect.signatures.download', $lease->document) }}"
                               class="text-xs px-3 py-1.5 rounded-md text-white text-center font-medium transition-all duration-300"
                               style="background: var(--brand-button, #0ea5e9);">
                                Download PDF
                            </a>
                        @endif
                        <a href="{{ route('docuperfect.leases.history', $lease) }}"
                           class="corex-btn-outline text-xs px-3 py-1.5 text-center transition-all duration-300">
                            History
                        </a>
                        <form method="POST" action="{{ route('docuperfect.leases.renew', $lease) }}" class="inline">
                            @csrf
                            <button type="submit" class="w-full text-xs px-3 py-1.5 rounded-md text-white font-medium text-center transition-all duration-300"
                                    style="background: var(--brand-button, #0ea5e9);"
                                    onclick="return confirm('Renew lease for {{ $lease->property_address }}?')">
                                Renew Lease
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-md p-8 text-center" style="background: var(--surface); border: 1px solid var(--border);">
                <div class="text-lg" style="color: var(--text-muted);">No active leases yet</div>
                <div class="text-sm mt-1" style="color: var(--text-muted);">Completed and approved lease agreements will appear here.</div>
            </div>
        @endforelse
    </div>

</div>
@endsection
