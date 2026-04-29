@extends('layouts.corex')

@section('title', 'Private Property — Agents on Branch')

@section('corex-content')
<div class="p-6 space-y-6" x-data="ppAgents()">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold" style="color:var(--text-primary);">Private Property — Agents on Branch</h1>
            <p class="text-sm mt-1" style="color:var(--text-muted);">
                Every agent profile PP currently has for this branch. Rows highlighted in red share an email with another row — those are duplicate profiles that need cleanup.
            </p>
        </div>
        <a href="{{ route('admin.pp.agents') }}"
           class="px-4 py-2 rounded-md text-sm font-medium"
           style="background:var(--surface-2); color:var(--text-secondary); border:1px solid var(--border);">
            Refresh from PP
        </a>
    </div>

    @if($error)
        <div class="rounded-md p-4 text-sm" style="background:rgba(239,68,68,0.12); color:#ef4444; border:1px solid rgba(239,68,68,0.3);">
            {{ $error }}
        </div>
    @endif

    <div class="rounded-xl overflow-hidden" style="background:var(--surface); border:1px solid var(--border);">
        <table class="w-full text-sm">
            <thead style="background:var(--surface-2); color:var(--text-secondary);">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold uppercase tracking-wider text-xs">External Ref</th>
                    <th class="text-left px-4 py-3 font-semibold uppercase tracking-wider text-xs">Name</th>
                    <th class="text-left px-4 py-3 font-semibold uppercase tracking-wider text-xs">Email</th>
                    <th class="text-left px-4 py-3 font-semibold uppercase tracking-wider text-xs">Cell</th>
                    <th class="text-left px-4 py-3 font-semibold uppercase tracking-wider text-xs">CoreX User</th>
                    <th class="text-left px-4 py-3 font-semibold uppercase tracking-wider text-xs">Encrypted ID</th>
                    <th class="text-right px-4 py-3 font-semibold uppercase tracking-wider text-xs">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agents as $a)
                    <tr style="border-top:1px solid var(--border); {{ $a['is_duplicate_external_ref'] ? 'background:rgba(239,68,68,0.06);' : '' }}">
                        <td class="px-4 py-3 font-mono" style="color:var(--text-primary);">{{ $a['agent_id'] }}</td>
                        <td class="px-4 py-3" style="color:var(--text-primary);">{{ trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) }}</td>
                        <td class="px-4 py-3" style="color:var(--text-secondary);">{{ $a['email'] }}</td>
                        <td class="px-4 py-3" style="color:var(--text-secondary);">{{ $a['contact_number'] }}</td>
                        <td class="px-4 py-3" style="color:var(--text-secondary);">
                            @if($a['corex_user_id'])
                                #{{ $a['corex_user_id'] }} {{ $a['corex_user_name'] }}
                            @else
                                <span style="color:var(--text-muted);">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs" style="color:var(--text-muted);">{{ $a['pp_encrypted_id'] }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button"
                                    @click='deactivate(@json($a))'
                                    :disabled="busy === '{{ $a['pp_encrypted_id'] }}'"
                                    class="px-3 py-1.5 rounded-md text-xs font-medium"
                                    style="color:#ef4444; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.08);">
                                <span x-show="busy !== '{{ $a['pp_encrypted_id'] }}'">Deactivate</span>
                                <span x-show="busy === '{{ $a['pp_encrypted_id'] }}'" x-cloak>...</span>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center" style="color:var(--text-muted);">No agents returned by PP.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p x-show="msg" x-cloak class="text-sm font-medium"
       :style="ok ? 'color:#22c55e' : 'color:#ef4444'" x-text="msg"></p>
</div>

<script>
function ppAgents() {
    return {
        busy: null, msg: '', ok: null,
        async deactivate(a) {
            if (!confirm('Deactivate PP profile ' + a.agent_id + ' (' + a.first_name + ' ' + a.last_name + ')? PP will refuse if this profile has active listings.')) return;
            this.busy = a.pp_encrypted_id; this.msg = ''; this.ok = null;
            try {
                const res = await fetch('{{ route('admin.pp.agents.deactivate') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        pp_encrypted_id: a.pp_encrypted_id,
                        agent_id:        a.agent_id,
                        first_name:      a.first_name,
                        last_name:       a.last_name,
                        email:           a.email,
                        tel_cell:        a.contact_number,
                    }),
                });
                const data = await res.json();
                this.ok = data.success; this.msg = data.message;
                if (data.success) setTimeout(() => location.reload(), 1200);
            } catch (e) { this.ok = false; this.msg = 'Network error'; }
            this.busy = null;
        }
    };
}
</script>
@endsection
