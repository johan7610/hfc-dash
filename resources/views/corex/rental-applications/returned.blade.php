{{-- DESIGN SYSTEM COMPLIANCE: UI_DESIGN_SYSTEM.md v 2026-04-20 --}}
@extends('layouts.corex')

@section('corex-content')
<div class="w-full space-y-5">
    <div class="rounded-md px-6 py-5 corex-page-banner">
        <h1 class="text-base font-bold leading-tight" style="color: var(--text-primary);">Returned Applications</h1>
        <p class="text-xs" style="color: var(--text-muted);">Applications the tenant has submitted, for the rental team to work through.</p>
    </div>

    <div class="flex gap-2">
        <a href="{{ route('corex.rental-applications.returned') }}" class="corex-btn-outline text-xs {{ !request('status') ? 'corex-tab-active' : '' }}">All</a>
        @foreach(['returned', 'under_assessment', 'approved', 'declined', 'withdrawn'] as $status)
            <a href="{{ route('corex.rental-applications.returned', ['status' => $status]) }}"
               class="corex-btn-outline text-xs {{ request('status') === $status ? 'corex-tab-active' : '' }}">
                {{ str_replace('_', ' ', ucfirst($status)) }}
            </a>
        @endforeach
    </div>

    <div class="rounded-md" style="background: var(--surface); border: 1px solid var(--border);">
        <table class="w-full text-sm">
            <thead>
                <tr style="border-bottom: 1px solid var(--border);">
                    <th class="text-left px-4 py-2" style="color: var(--text-muted);">Contact</th>
                    <th class="text-left px-4 py-2" style="color: var(--text-muted);">Property</th>
                    <th class="text-left px-4 py-2" style="color: var(--text-muted);">Status</th>
                    <th class="text-left px-4 py-2" style="color: var(--text-muted);">Signatures</th>
                    <th class="text-left px-4 py-2" style="color: var(--text-muted);">Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($applications as $application)
                <tr style="border-bottom: 1px solid var(--border);">
                    <td class="px-4 py-2">{{ $application->contact->full_name ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $application->property?->buildDisplayAddress() ?? $application->property_address_override ?? '—' }}</td>
                    <td class="px-4 py-2"><span class="ds-badge ds-badge-info">{{ str_replace('_', ' ', $application->status) }}</span></td>
                    <td class="px-4 py-2">{{ $application->isFullySigned() ? '✓ Both signed' : 'Incomplete' }}</td>
                    <td class="px-4 py-2">{{ optional($application->submitted_at)->format('d M Y') ?? '—' }}</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('corex.rental-applications.show', $application) }}" class="corex-btn-outline text-xs">Open</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">No returned applications.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
</div>
@endsection
