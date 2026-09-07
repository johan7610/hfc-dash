{{-- DESIGN SYSTEM COMPLIANCE: UI_DESIGN_SYSTEM.md v 2026-04-20 --}}
@extends('layouts.corex')

@section('corex-content')
<div class="w-full space-y-5">
    <div class="rounded-md px-6 py-5 corex-page-banner">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h1 class="text-base font-bold leading-tight" style="color: var(--text-primary);">Rental Applications</h1>
                <p class="text-xs" style="color: var(--text-muted);">Send a rental application to a prospective tenant.</p>
            </div>
            @permission('rental_applications.create')
            <a href="{{ route('corex.rental-applications.create') }}" class="corex-btn-primary text-xs">New Rental Application</a>
            @endpermission
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-md px-4 py-3 text-sm" style="background: var(--ds-emerald-soft, #ecfdf5); color: var(--ds-emerald, #059669);">{{ session('success') }}</div>
    @endif

    <div class="rounded-md" style="background: var(--surface); border: 1px solid var(--border);">
        <table class="w-full text-sm">
            <thead>
                <tr style="border-bottom: 1px solid var(--border);">
                    <th class="text-left px-4 py-2" style="color: var(--text-muted);">Contact</th>
                    <th class="text-left px-4 py-2" style="color: var(--text-muted);">Property</th>
                    <th class="text-left px-4 py-2" style="color: var(--text-muted);">Status</th>
                    <th class="text-left px-4 py-2" style="color: var(--text-muted);">Sent</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($applications as $application)
                <tr style="border-bottom: 1px solid var(--border);">
                    <td class="px-4 py-2">{{ $application->contact->full_name ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $application->property?->buildDisplayAddress() ?? $application->property_address_override ?? '—' }}</td>
                    <td class="px-4 py-2"><span class="ds-badge ds-badge-info">{{ str_replace('_', ' ', $application->status) }}</span></td>
                    <td class="px-4 py-2">{{ $application->created_at->format('d M Y') }}</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('corex.rental-applications.show', $application) }}" class="corex-btn-outline text-xs">Open</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">No rental applications yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
</div>
@endsection
