@extends('layouts.corex')

@section('corex-content')
<div class="max-w-5xl mx-auto space-y-5">

    {{-- Header --}}
    <div class="rounded-2xl px-6 py-4 flex items-start justify-between gap-4" style="background:var(--brand-default, #0b2a4a);">
        <div>
            <h2 class="text-xl font-bold text-white">P24 Locations</h2>
            <div class="text-sm mt-0.5" style="color:rgba(255,255,255,0.6);">
                The Property24 location tree cached locally — browse Region → Town → Suburb.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.importer.p24-locations.refresh') }}"
              onsubmit="this.querySelector('button').disabled=true; this.querySelector('button span').textContent='Refreshing (15–30 min)…';">
            @csrf
            <button type="submit"
                    class="px-4 py-2 rounded-lg text-sm font-semibold text-white transition-colors"
                    style="background:var(--brand-button, #0ea5e9);">
                <span>Refresh from Property24</span>
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="rounded-xl border px-4 py-3 text-sm" style="background:#ecfdf5;border-color:#a7f3d0;color:#065f46;">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border px-4 py-3 text-sm" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats + last sync --}}
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Provinces</div>
            <div class="text-2xl font-bold mt-1" style="color:var(--brand-default,#0b2a4a);">{{ number_format($totals['provinces']) }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Cities / Towns</div>
            <div class="text-2xl font-bold mt-1" style="color:var(--brand-default,#0b2a4a);">{{ number_format($totals['cities']) }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Suburbs</div>
            <div class="text-2xl font-bold mt-1" style="color:var(--brand-default,#0b2a4a);">{{ number_format($totals['suburbs']) }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Last Synced</div>
            <div class="text-sm font-bold mt-1" style="color:var(--brand-default,#0b2a4a);">
                {{ $lastSyncedAt ? $lastSyncedAt->diffForHumans() : 'never' }}
            </div>
            @if($lastSyncedAt)
                <div class="text-[10px] text-slate-400 mt-0.5">{{ $lastSyncedAt->format('Y-m-d H:i') }}</div>
            @endif
        </div>
    </div>

    @if($lastSyncError)
        <div class="rounded-xl border px-4 py-3 text-xs" style="background:#fffbeb;border-color:#fcd34d;color:#92400e;">
            <span class="font-semibold">Last sync error:</span>
            <span class="break-all">{{ \Illuminate\Support\Str::limit($lastSyncError, 500) }}</span>
        </div>
    @endif

    {{-- Tree --}}
    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
        @forelse($provinces as $province)
            <div x-data="p24Province({{ $province->id }})" class="border-b border-slate-200 last:border-b-0">
                <button type="button" @click="toggle()"
                        class="w-full flex items-center justify-between px-5 py-3 hover:bg-slate-50 transition-colors text-left">
                    <div class="flex items-center gap-3">
                        <svg :class="open ? 'rotate-90' : ''" class="w-4 h-4 text-slate-400 transition-transform"
                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                        <span class="text-sm font-semibold" style="color:var(--brand-default,#0b2a4a);">{{ $province->name }}</span>
                        <span class="text-[11px] text-slate-400">{{ $province->cities_count }} {{ $province->cities_count === 1 ? 'city' : 'cities' }}</span>
                    </div>
                </button>
                <div x-show="open" x-cloak class="bg-slate-50/50 px-5 pb-3">
                    <template x-if="loading">
                        <div class="text-xs text-slate-400 py-2">Loading cities…</div>
                    </template>

                    <template x-for="city in cities" :key="city.id">
                        <div class="border-l-2 border-slate-200 ml-2 pl-3 py-1" x-data="p24City(city.id)">
                            <button type="button" @click="toggle()"
                                    class="w-full flex items-center gap-2 py-1 text-left hover:text-blue-600">
                                <svg :class="open ? 'rotate-90' : ''" class="w-3 h-3 text-slate-400 transition-transform"
                                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                                <span class="text-xs font-medium text-slate-700" x-text="city.name"></span>
                            </button>
                            <div x-show="open" x-cloak class="ml-5 mt-1">
                                <template x-if="loading">
                                    <div class="text-[11px] text-slate-400">Loading suburbs…</div>
                                </template>
                                <template x-if="!loading && suburbs.length === 0">
                                    <div class="text-[11px] text-slate-400 italic">No suburbs cached for this city.</div>
                                </template>
                                <ul class="space-y-0.5">
                                    <template x-for="s in suburbs" :key="s.id">
                                        <li class="text-[11px] text-slate-600 flex items-center gap-2">
                                            <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                            <span x-text="s.name"></span>
                                            <span class="text-slate-400 font-mono">#<span x-text="s.p24_id"></span></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </template>

                    <template x-if="!loading && cities.length === 0">
                        <div class="text-[11px] text-slate-400 italic py-2">No cities cached for this province.</div>
                    </template>
                </div>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-sm text-slate-500">
                No P24 locations cached yet. Click <span class="font-semibold">Refresh from Property24</span> above to pull the full tree.
            </div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
function p24Province(id) {
    return {
        open: false, loading: false, loaded: false, cities: [],
        async toggle() {
            this.open = !this.open;
            if (this.open && !this.loaded) await this.load();
        },
        async load() {
            this.loading = true;
            try {
                const r = await fetch('/api/v1/p24/cities?all=1&province_id=' + id, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                const j = await r.json();
                this.cities = j.data || [];
                this.loaded = true;
            } finally { this.loading = false; }
        },
    };
}
function p24City(id) {
    return {
        open: false, loading: false, loaded: false, suburbs: [],
        async toggle() {
            this.open = !this.open;
            if (this.open && !this.loaded) await this.load();
        },
        async load() {
            this.loading = true;
            try {
                const r = await fetch('/api/v1/p24/suburbs?all=1&city_id=' + id, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                const j = await r.json();
                this.suburbs = j.data || [];
                this.loaded = true;
            } finally { this.loading = false; }
        },
    };
}
</script>
@endpush
@endsection
