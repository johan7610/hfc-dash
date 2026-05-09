@extends('layouts.corex-app')

@section('title', 'Client App Activity')

@section('content')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-2xl font-bold" style="color:var(--text-primary);">Client App Activity</h1>
        <p class="text-sm mt-1" style="color:var(--text-secondary);">System-wide log of client mobile-app sign-ins, password changes, and agency selections.</p>
    </div>

    <div class="flex gap-2 border-b" style="border-color:var(--border);">
        <a href="{{ route('admin.client-app-activity', ['tab' => 'activity']) }}"
           class="px-4 py-2 text-sm font-semibold {{ $tab === 'activity' ? 'border-b-2' : '' }}"
           style="color: {{ $tab === 'activity' ? 'var(--brand-icon, #0ea5e9)' : 'var(--text-secondary)' }}; border-color: var(--brand-icon, #0ea5e9);">
            Activity
        </a>
        <a href="{{ route('admin.client-app-activity', ['tab' => 'attempts']) }}"
           class="px-4 py-2 text-sm font-semibold {{ $tab === 'attempts' ? 'border-b-2' : '' }}"
           style="color: {{ $tab === 'attempts' ? 'var(--brand-icon, #0ea5e9)' : 'var(--text-secondary)' }}; border-color: var(--brand-icon, #0ea5e9);">
            Sign-in Attempts (no match)
        </a>
    </div>

    @if($tab === 'activity')
        <form method="GET" class="flex gap-2 items-end">
            <input type="hidden" name="tab" value="activity">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color:var(--text-muted);">Search Email</label>
                <input name="q" value="{{ request('q') }}" class="corex-input" placeholder="client@…">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color:var(--text-muted);">Event</label>
                <select name="event" class="corex-input">
                    <option value="">All</option>
                    @foreach($events as $ev)
                        <option value="{{ $ev }}" @selected(request('event') === $ev)>{{ $ev }}</option>
                    @endforeach
                </select>
            </div>
            <button class="corex-btn-primary text-sm">Filter</button>
        </form>

        <div class="rounded-md overflow-hidden" style="background:var(--surface); border:1px solid var(--border);">
            <table class="w-full text-sm">
                <thead style="background:var(--surface-2);">
                    <tr>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">When</th>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Client</th>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Agency</th>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Contact</th>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Event</th>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">IP</th>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Device</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color:var(--border);">
                    @forelse($logs as $log)
                        <tr style="border-color:var(--border);">
                            <td class="px-4 py-2 text-xs" style="color:var(--text-muted);">{{ $log->created_at->format('d M H:i') }}</td>
                            <td class="px-4 py-2">{{ $log->clientUser?->email ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $log->agency?->name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $log->contact ? trim(($log->contact->first_name ?? '') . ' ' . ($log->contact->last_name ?? '')) : '—' }}</td>
                            <td class="px-4 py-2"><span class="font-mono text-xs">{{ $log->event }}</span></td>
                            <td class="px-4 py-2 text-xs" style="color:var(--text-muted);">{{ $log->ip ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs" style="color:var(--text-muted);">{{ $log->device_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center" style="color:var(--text-muted);">No activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $logs->links() }}</div>
    @else
        <form method="GET" class="flex gap-2 items-end">
            <input type="hidden" name="tab" value="attempts">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1" style="color:var(--text-muted);">Search Identifier</label>
                <input name="q" value="{{ request('q') }}" class="corex-input" placeholder="email or phone">
            </div>
            <button class="corex-btn-primary text-sm">Filter</button>
        </form>

        <div class="rounded-md overflow-hidden" style="background:var(--surface); border:1px solid var(--border);">
            <table class="w-full text-sm">
                <thead style="background:var(--surface-2);">
                    <tr>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">When</th>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Identifier</th>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Matched</th>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Agencies</th>
                        <th class="text-left px-4 py-2 text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color:var(--border);">
                    @forelse($attempts as $a)
                        <tr style="border-color:var(--border);">
                            <td class="px-4 py-2 text-xs" style="color:var(--text-muted);">{{ $a->created_at->format('d M H:i') }}</td>
                            <td class="px-4 py-2">{{ $a->identifier }}</td>
                            <td class="px-4 py-2">
                                @if($a->matched)
                                    <span class="ds-badge ds-badge-success">Yes</span>
                                @else
                                    <span class="ds-badge ds-badge-danger">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $a->agency_count }}</td>
                            <td class="px-4 py-2 text-xs" style="color:var(--text-muted);">{{ $a->ip ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center" style="color:var(--text-muted);">No sign-in attempts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $attempts->links() }}</div>
    @endif
</div>
@endsection
