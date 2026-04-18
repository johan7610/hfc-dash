@extends('layouts.onboarding-portal')

@section('portal-content')
<div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4"
     x-data="portalReview('{{ $portal->token }}')">

    {{-- Filters --}}
    <form method="GET" class="rounded-md bg-surface p-4 border border-subtle/30 sticky top-0 z-10">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <select name="status" class="rounded-md bg-surface-2 border border-subtle px-2 py-1.5 text-sm">
                @foreach (['pending','confirmed','excluded','error','all'] as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select name="listing_type" class="rounded-md bg-surface-2 border border-subtle px-2 py-1.5 text-sm">
                <option value="all" @selected($type === 'all')>All types</option>
                <option value="Sale" @selected($type === 'Sale')>Sale</option>
                <option value="Rental" @selected($type === 'Rental')>Rental</option>
            </select>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search address / listing #"
                   class="rounded-md bg-surface-2 border border-subtle px-2 py-1.5 text-sm">
            <button type="submit" class="rounded-md px-3 py-1.5 text-xs bg-surface-2 border border-subtle">Apply</button>
        </div>
        <div class="flex items-center justify-between mt-3 flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <button type="button" @click="bulkConfirm()"
                        class="portal-cta rounded-md px-3 py-1.5 text-xs font-semibold">
                    Confirm selected
                </button>
                <button type="button" @click="bulkExclude()"
                        class="rounded-md px-3 py-1.5 text-xs bg-surface-2 border border-subtle">
                    Exclude selected
                </button>
                <button type="button" @click="confirmAllPending()"
                        class="rounded-md px-3 py-1.5 text-xs bg-surface-2 border border-subtle">
                    Confirm all pending ({{ $rows->total() }})
                </button>
                <span class="text-xs text-muted" x-text="selected.length + ' selected'"></span>
            </div>
            <a href="{{ route('onboarding.portal.finish', $portal->token) }}"
               class="rounded-md px-3 py-1.5 text-xs border border-subtle">
                Finish review →
            </a>
        </div>
    </form>

    {{-- Progress bar --}}
    <div x-show="progress.active" x-cloak class="rounded-md bg-surface p-3 border border-subtle/30">
        <div class="flex items-center justify-between text-xs mb-2">
            <span x-text="progress.label + ' — ' + progress.done + ' of ' + progress.total"></span>
            <span x-text="progress.total ? Math.round((progress.done / progress.total) * 100) + '%' : '0%'"></span>
        </div>
        <div class="w-full bg-surface-2 rounded-md h-2 overflow-hidden">
            <div class="h-full transition-all duration-200"
                 :style="'width: ' + (progress.total ? (progress.done / progress.total) * 100 : 0) + '%; background: var(--brand-button);'"></div>
        </div>
        <div x-show="progress.errors > 0" class="mt-2 text-xs text-red-500"
             x-text="progress.errors + ' listing(s) failed — check the Errors tab'"></div>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs">
        <div class="rounded-md bg-surface p-2 text-center"><span class="text-muted">Pending</span> <span x-text="counts.pending" class="font-semibold ml-1">{{ $counts['pending'] }}</span></div>
        <div class="rounded-md bg-surface p-2 text-center"><span class="text-muted">In progress</span> <span x-text="counts.processing" class="font-semibold ml-1">{{ $counts['processing'] }}</span></div>
        <div class="rounded-md bg-surface p-2 text-center"><span class="text-muted">Confirmed</span> <span x-text="counts.confirmed" class="font-semibold ml-1">{{ $counts['confirmed'] }}</span></div>
        <div class="rounded-md bg-surface p-2 text-center"><span class="text-muted">Excluded</span> <span x-text="counts.excluded" class="font-semibold ml-1">{{ $counts['excluded'] }}</span></div>
        <div class="rounded-md bg-surface p-2 text-center"><span class="text-muted">Errors</span> <span x-text="counts.error" class="font-semibold ml-1">{{ $counts['error'] }}</span></div>
    </div>

    {{-- Table --}}
    <div class="rounded-md bg-surface border border-subtle/30">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-xs uppercase text-muted border-b border-subtle">
                <tr>
                    <th class="px-2 py-2"><input type="checkbox" @change="toggleAll($event)"></th>
                    <th class="px-2 py-2 text-left">Photo</th>
                    <th class="px-2 py-2 text-left">Listing #</th>
                    <th class="px-2 py-2 text-left">Address</th>
                    <th class="px-2 py-2 text-left">Type</th>
                    <th class="px-2 py-2 text-left">Price</th>
                    <th class="px-2 py-2 text-left">Beds/Baths</th>
                    <th class="px-2 py-2 text-left">Agent</th>
                    <th class="px-2 py-2 text-left">Photos</th>
                    <th class="px-2 py-2 text-left">Status</th>
                    <th class="px-2 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                @php
                    $m = $row->mapped_json ?? [];
                    $errs = (array) ($row->errors_json ?? []);
                    $isProcessing = $row->isProcessing();
                    $imgs = (array) $row->image_urls_json;
                    $firstImg = $imgs[0] ?? null;
                @endphp
                <tr class="border-b border-subtle/40"
                    :class="{'opacity-60': rowState[{{ $row->id }}]?.busy, 'hidden': rowState[{{ $row->id }}]?.hidden}"
                    data-row="{{ $row->id }}">
                    <td class="px-2 py-2">
                        <template x-if="canSelect({{ $row->id }}, '{{ $row->status }}', {{ $isProcessing ? 'true' : 'false' }})">
                            <input type="checkbox" value="{{ $row->id }}" @change="toggleRow({{ $row->id }}, $event)" :checked="selected.includes({{ $row->id }})">
                        </template>
                    </td>
                    <td class="px-2 py-2">
                        @if ($firstImg)
                            <img src="{{ $firstImg }}" alt="" loading="lazy"
                                 class="w-16 h-12 object-cover rounded-md border border-subtle/40 bg-surface-2"
                                 onerror="this.style.display='none'">
                        @else
                            <div class="w-16 h-12 rounded-md border border-subtle/40 bg-surface-2 flex items-center justify-center text-muted text-[10px]">no image</div>
                        @endif
                    </td>
                    <td class="px-2 py-2 font-mono text-xs">{{ $row->external_id }}</td>
                    <td class="px-2 py-2">{{ $m['address'] ?? '—' }}</td>
                    <td class="px-2 py-2">{{ $m['listing_type'] ?? '' }}</td>
                    <td class="px-2 py-2">
                        @if (!empty($m['price'])) R {{ number_format((float)$m['price'], 0, '.', ',') }}
                        @elseif (!empty($m['rental_amount'])) R {{ number_format((float)$m['rental_amount'], 0, '.', ',') }} /m
                        @else — @endif
                    </td>
                    <td class="px-2 py-2 text-xs">{{ $m['beds'] ?? 0 }}b / {{ $m['baths'] ?? 0 }}ba</td>
                    <td class="px-2 py-2">
                        <select class="rounded-md bg-surface-2 border border-subtle px-1 py-0.5 text-xs"
                                @change="reassignAgent({{ $row->id }}, $event.target.value)">
                            <option value="">— unassigned —</option>
                            @foreach ($agents as $a)
                                <option value="{{ $a->id }}" @selected($row->resolved_agent_id == $a->id)>{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-2 py-2 text-xs">{{ count($imgs) }}</td>
                    <td class="px-2 py-2">
                        <template x-if="rowState[{{ $row->id }}]?.status">
                            <span class="px-2 py-0.5 rounded-md text-xs"
                                  :class="{
                                      'bg-amber-500/20 text-amber-700': rowState[{{ $row->id }}].status === 'processing',
                                      'bg-emerald-500/20 text-emerald-700': rowState[{{ $row->id }}].status === 'confirmed',
                                      'bg-red-500/20 text-red-700': rowState[{{ $row->id }}].status === 'error',
                                      'bg-surface-2': ['excluded'].includes(rowState[{{ $row->id }}].status),
                                  }"
                                  x-text="rowState[{{ $row->id }}].status === 'processing' ? 'processing…' : rowState[{{ $row->id }}].status"></span>
                        </template>
                        <template x-if="!rowState[{{ $row->id }}]?.status">
                            @if ($isProcessing)
                                <span class="px-2 py-0.5 rounded-md text-xs bg-amber-500/20 text-amber-700">processing…</span>
                            @elseif (!empty($errs))
                                <span class="px-2 py-0.5 rounded-md text-xs bg-red-500/20 text-red-700" title="{{ implode('; ', $errs) }}">error</span>
                            @else
                                <span class="px-2 py-0.5 rounded-md text-xs bg-surface-2">{{ $row->status }}</span>
                            @endif
                        </template>
                    </td>
                    <td class="px-2 py-2 text-right whitespace-nowrap">
                        <template x-if="canAct({{ $row->id }}, '{{ $row->status }}', {{ $isProcessing ? 'true' : 'false' }})">
                            <span>
                                <button type="button" @click="confirmRow({{ $row->id }})"
                                        class="portal-accent text-xs mr-2 font-semibold"
                                        :disabled="rowState[{{ $row->id }}]?.busy">Confirm</button>
                                <button type="button" @click="excludeRow({{ $row->id }})"
                                        class="text-xs text-red-500"
                                        :disabled="rowState[{{ $row->id }}]?.busy">Exclude</button>
                            </span>
                        </template>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="py-10 text-center text-muted">No listings match your filters.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div class="p-4">{{ $rows->links() }}</div>
    </div>
</div>

<script>
function portalReview(token) {
    return {
        selected: [],
        rowState: {},
        counts: @json($counts),
        progress: { active: false, total: 0, done: 0, errors: 0, label: '' },
        csrf: document.querySelector('meta[name=csrf-token]')?.content ?? '',

        canSelect(id, status, isProcessing) {
            const st = this.rowState[id];
            if (st?.hidden) return false;
            const current = st?.status ?? status;
            return (current === 'pending' || current === 'error') && !(st?.busy) && !isProcessing;
        },
        canAct(id, status, isProcessing) {
            const st = this.rowState[id];
            if (st?.hidden) return false;
            const current = st?.status ?? status;
            return (current === 'pending' || current === 'error') && !isProcessing;
        },
        toggleRow(id, e) {
            if (e.target.checked) {
                if (!this.selected.includes(id)) this.selected.push(id);
            } else {
                this.selected = this.selected.filter(x => x !== id);
            }
        },
        toggleAll(e) {
            const boxes = document.querySelectorAll('tbody input[type=checkbox]');
            this.selected = [];
            boxes.forEach(b => {
                b.checked = e.target.checked;
                if (b.checked) this.selected.push(parseInt(b.value));
            });
        },
        async post(url, body = {}) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify(body),
            });
            let data = null;
            try { data = await res.json(); } catch (e) {}
            return { ok: res.ok, status: res.status, data };
        },
        setRow(id, patch) {
            this.rowState = { ...this.rowState, [id]: { ...(this.rowState[id] ?? {}), ...patch } };
        },
        async confirmSingle(id) {
            this.setRow(id, { busy: true, status: 'processing' });
            const r = await this.post(`/onboarding/${token}/rows/${id}/confirm`);
            if (r.ok && r.data?.status === 'confirmed') {
                this.setRow(id, { busy: false, status: 'confirmed' });
                if (r.data.counts) this.counts = r.data.counts;
                this.selected = this.selected.filter(x => x !== id);
                return { ok: true };
            }
            const msg = r.data?.errors?.join?.('; ') || ('HTTP ' + r.status);
            this.setRow(id, { busy: false, status: 'error', error: msg });
            return { ok: false, error: msg };
        },
        async confirmRow(id) {
            await this.confirmSingle(id);
        },
        async excludeRow(id) {
            if (!confirm('Exclude this listing from going live?')) return;
            this.setRow(id, { busy: true });
            const r = await this.post(`/onboarding/${token}/rows/${id}/exclude`);
            if (r.ok) {
                this.setRow(id, { busy: false, status: 'excluded', hidden: true });
                this.selected = this.selected.filter(x => x !== id);
                await this.refreshCounts();
            } else {
                this.setRow(id, { busy: false });
                alert('Could not exclude this listing.');
            }
        },
        async reassignAgent(id, userId) {
            if (!userId) return;
            const r = await this.post(`/onboarding/${token}/rows/${id}/reassign`, {user_id: parseInt(userId)});
            if (!r.ok) alert('Could not reassign agent.');
        },
        async runBatch(ids, label) {
            if (!ids.length) return;
            this.progress = { active: true, total: ids.length, done: 0, errors: 0, label };
            for (const id of ids) {
                const r = await this.confirmSingle(id);
                this.progress.done += 1;
                if (!r.ok) this.progress.errors += 1;
            }
            setTimeout(() => { this.progress = { active: false, total: 0, done: 0, errors: 0, label: '' }; }, 1500);
        },
        async bulkConfirm() {
            if (!this.selected.length) {
                alert('Select at least one listing first.');
                return;
            }
            if (!confirm(`Confirm ${this.selected.length} listings?`)) return;
            const ids = [...this.selected];
            await this.runBatch(ids, 'Confirming selected');
        },
        async bulkExclude() {
            if (!this.selected.length) {
                alert('Select at least one listing first.');
                return;
            }
            if (!confirm(`Exclude ${this.selected.length} listings?`)) return;
            const ids = [...this.selected];
            for (const id of ids) {
                await this.excludeRow(id);
            }
        },
        async confirmAllPending() {
            const boxes = document.querySelectorAll('tbody input[type=checkbox]');
            const ids = [];
            boxes.forEach(b => { const v = parseInt(b.value); if (v) ids.push(v); });
            if (!ids.length) { alert('No pending listings on this page.'); return; }
            if (!confirm(`Confirm ${ids.length} pending listings on this page?`)) return;
            await this.runBatch(ids, 'Confirming all pending');
        },
        async refreshCounts() {
            try {
                const res = await fetch(`/onboarding/${token}/status`, {headers:{Accept:'application/json'}});
                const data = await res.json();
                if (data?.counts) this.counts = data.counts;
            } catch (e) {}
        },
    };
}
</script>
@endsection
