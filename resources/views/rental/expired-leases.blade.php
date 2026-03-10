@extends('layouts.corex')

@section('corex-content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-5">
        <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Expired Leases</h2>
        <div class="text-sm text-white/60 mt-1">
            <a href="{{ route('rental.dashboard') }}" class="text-white/60 hover:text-white transition-all duration-300">&larr; Rentals</a>
            &middot; Expired and terminated lease agreements.
        </div>
    </div>

    <div class="space-y-3">
        @forelse($leases as $lease)
            @php $rental = number_format((float) $lease->rental_amount, 0, '.', ' '); @endphp
            <div class="rounded-md p-5 transition-all duration-300"
                 style="background: var(--surface); border: 1px solid var(--border); border-left: 3px solid #ef4444;"
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
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <span class="inline-block px-2.5 py-0.5 rounded-md text-[11px] font-semibold bg-red-100 text-red-800">
                                Expired {{ $lease->lease_end_date?->format('d M Y') ?? 'N/A' }}
                            </span>
                            @if($lease->status === 'terminated')
                                <span class="inline-block px-2.5 py-0.5 rounded-md text-[11px] font-semibold" style="background: var(--surface-2); color: var(--text-secondary);">
                                    Terminated
                                </span>
                            @endif
                        </div>
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
                <div class="text-lg" style="color: var(--text-muted);">No expired leases</div>
                <div class="text-sm mt-1" style="color: var(--text-muted);">Expired and terminated leases will appear here.</div>
            </div>
        @endforelse
    </div>

</div>
@endsection
