<x-app-layout>

{{-- Master Alpine state for the entire page --}}
<div x-data="p24Alerts()" class="max-w-7xl mx-auto px-4 py-6 space-y-5">

    {{-- ===== STICKY HEADER ===== --}}
    <x-list-header
        title="P24 Alerts"
        :count="$activeListings"
        :total="$totalListings"
        search-placeholder="Search suburb, P24 ref, address, type..."
        search-model="globalSearch"
    >
        <x-slot:filters>
            <select x-model="globalSuburb" class="list-header-filter">
                <option value="">All suburbs</option>
                @foreach($allSuburbs as $s)
                <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
            <select x-model="globalType" class="list-header-filter">
                <option value="">All types</option>
                @foreach($allPropertyTypes as $t)
                <option value="{{ $t }}">{{ $t }}</option>
                @endforeach
            </select>
        </x-slot:filters>
        <x-slot:actions>
            <a href="{{ route('admin.p24.listings') }}" class="corex-btn-outline text-xs">Browse All</a>
            <form method="POST" action="{{ route('admin.p24.import') }}" class="inline">
                @csrf
                <button type="submit" class="corex-btn-primary text-sm">Run Import</button>
            </form>
        </x-slot:actions>
    </x-list-header>

    {{-- ===== IMPORT STATUS + STATS ===== --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <div class="ds-status-card p-3">
            <div class="text-[11px] uppercase tracking-wider" style="color: var(--text-muted);">Last Import</div>
            <div class="text-sm font-semibold mt-1" style="color: var(--text-primary);">{{ $lastImport ? $lastImport->created_at->diffForHumans() : 'Never' }}</div>
        </div>
        <div class="ds-status-card p-3">
            <div class="text-[11px] uppercase tracking-wider" style="color: var(--text-muted);">Emails (30d)</div>
            <div class="text-sm font-semibold mt-1" style="color: var(--text-primary);">{{ number_format($emailsProcessed30d) }}</div>
        </div>
        <div class="ds-status-card p-3">
            <div class="text-[11px] uppercase tracking-wider" style="color: var(--text-muted);">Active Listings</div>
            <div class="text-sm font-semibold mt-1" style="color: var(--text-primary);">{{ number_format($activeListings) }}</div>
        </div>
        <div class="ds-status-card p-3">
            <div class="text-[11px] uppercase tracking-wider" style="color: var(--text-muted);">New This Month</div>
            <div class="text-sm font-semibold mt-1" style="color: var(--text-primary);">
                {{ number_format($newThisMonth) }}
                @if($monthChangePercent !== null)
                <span class="text-xs {{ $monthChangePercent >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $monthChangePercent >= 0 ? '+' : '' }}{{ $monthChangePercent }}%
                </span>
                @endif
            </div>
        </div>
        <div class="ds-status-card p-3">
            <div class="text-[11px] uppercase tracking-wider" style="color: var(--text-muted);">Avg Price</div>
            <div class="text-sm font-semibold mt-1" style="color: var(--text-primary);">R {{ number_format($avgAskingPrice, 0, '.', ' ') }}</div>
        </div>
        <div class="ds-status-card p-3">
            <div class="text-[11px] uppercase tracking-wider" style="color: var(--text-muted);">IMAP</div>
            <div class="text-sm font-semibold mt-1 flex items-center gap-1.5" style="color: var(--text-primary);">
                <span class="inline-block w-2 h-2 rounded-full {{ $imapConfigured ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                {{ $imapConfigured ? 'OK' : 'N/A' }}
            </div>
        </div>
    </div>

    {{-- ===== SECTION 1: LISTINGS BY SUBURB (Collapsible) ===== --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
        <button type="button"
                @click="toggleSection('suburbs')"
                class="w-full text-left px-4 py-3 flex items-center justify-between transition-all duration-300"
                style="background: var(--surface-2);"
                onmouseover="this.style.background='color-mix(in srgb, var(--surface-2) 80%, var(--text-primary) 5%)'"
                onmouseout="this.style.background='var(--surface-2)'">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-bold uppercase tracking-wider" style="color: var(--text-primary);">Listings by Suburb</h3>
                <span class="text-xs" style="color: var(--text-muted);">{{ $suburbStats->count() }} suburbs &middot; {{ $suburbStats->sum('listing_count') }} listings</span>
            </div>
            <svg class="w-4 h-4 transition-transform duration-300" style="color: var(--text-muted);" :class="sections.suburbs && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
        </button>

        <div x-show="sections.suburbs" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            @if($suburbStats->isEmpty())
                <div class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">No active listings by suburb.</div>
            @else
                {{-- Summary table --}}
                <div class="overflow-x-auto" style="border-bottom: 1px solid var(--border);">
                    <table class="ds-table min-w-full text-sm">
                        <thead>
                            <tr style="background: var(--surface-2);">
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Suburb</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Active</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Avg Price</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Min</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Max</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">New/Month</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($suburbStats as $row)
                            <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                                <td class="px-4 py-2.5 font-medium" style="color: var(--text-primary);">{{ $row->suburb }}</td>
                                <td class="px-4 py-2.5 text-right" style="color: var(--text-primary);">{{ number_format($row->listing_count) }}</td>
                                <td class="px-4 py-2.5 text-right" style="color: var(--text-primary);">R {{ number_format($row->avg_price, 0, '.', ' ') }}</td>
                                <td class="px-4 py-2.5 text-right" style="color: var(--text-secondary);">R {{ number_format($row->min_price, 0, '.', ' ') }}</td>
                                <td class="px-4 py-2.5 text-right" style="color: var(--text-secondary);">R {{ number_format($row->max_price, 0, '.', ' ') }}</td>
                                <td class="px-4 py-2.5 text-right" style="color: var(--text-primary);">{{ number_format($row->new_this_month) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Collapsible per-suburb listing groups --}}
                @foreach($listingsBySuburb as $suburbName => $listings)
                <div style="border-bottom: 1px solid var(--border);" class="last:border-b-0">
                    <button type="button"
                            @click="toggleSuburb('{{ md5($suburbName) }}')"
                            class="w-full text-left px-4 py-2.5 flex items-center justify-between transition-all duration-300"
                            onmouseover="this.style.background='var(--surface-2)'"
                            onmouseout="this.style.background='transparent'">
                        <div class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 transition-transform duration-300" style="color: var(--text-muted);" :class="openSuburbs['{{ md5($suburbName) }}'] && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $suburbName }}</span>
                            <span class="text-xs px-1.5 py-0.5 rounded-md" style="background: var(--surface-2); color: var(--text-muted);">{{ $listings->count() }}</span>
                        </div>
                    </button>

                    <div x-show="openSuburbs['{{ md5($suburbName) }}']" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="overflow-x-auto">
                            <table class="ds-table min-w-full text-sm">
                                <thead>
                                    <tr style="background: var(--surface-2);">
                                        <th class="text-left px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">P24 Ref</th>
                                        <th class="text-left px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Type</th>
                                        <th class="text-right px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Price</th>
                                        <th class="text-right px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Beds</th>
                                        <th class="text-right px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Baths</th>
                                        <th class="text-right px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Days</th>
                                        <th class="text-left px-4 py-2 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($listings as $listing)
                                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                                        <td class="px-4 py-2">
                                            @if($listing->p24_url)
                                            <a href="{{ $listing->p24_url }}" target="_blank" rel="noopener" class="hover:underline font-medium transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);">{{ $listing->p24_listing_number }}</a>
                                            @else
                                            <span style="color: var(--text-secondary);">{{ $listing->p24_listing_number }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2" style="color: var(--text-secondary);">{{ $listing->property_type ?? '—' }}</td>
                                        <td class="px-4 py-2 text-right font-medium" style="color: var(--text-primary);">R {{ number_format($listing->asking_price, 0, '.', ' ') }}</td>
                                        <td class="px-4 py-2 text-right" style="color: var(--text-secondary);">{{ $listing->bedrooms ?? '—' }}</td>
                                        <td class="px-4 py-2 text-right" style="color: var(--text-secondary);">{{ $listing->bathrooms ?? '—' }}</td>
                                        <td class="px-4 py-2 text-right" style="color: var(--text-muted);">{{ $listing->days_on_market ?? '—' }}</td>
                                        <td class="px-4 py-2">
                                            <span class="ds-badge
                                                @if($listing->listing_status === 'active') ds-badge-success
                                                @elseif($listing->listing_status === 'sold') ds-badge-info
                                                @else ds-badge-default @endif
                                            ">{{ ucfirst($listing->listing_status ?? 'unknown') }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ===== SECTION 2: PRICE CHANGES (Collapsible + Sortable + Filterable) ===== --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
        <button type="button"
                @click="toggleSection('priceChanges')"
                class="w-full text-left px-4 py-3 flex items-center justify-between transition-all duration-300"
                style="background: var(--surface-2);"
                onmouseover="this.style.background='color-mix(in srgb, var(--surface-2) 80%, var(--text-primary) 5%)'"
                onmouseout="this.style.background='var(--surface-2)'">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-bold uppercase tracking-wider" style="color: var(--text-primary);">Price Changes</h3>
                <span class="text-xs" style="color: var(--text-muted);" x-text="filteredPriceChanges().length + ' of {{ $priceChanges->count() }}'"></span>
            </div>
            <svg class="w-4 h-4 transition-transform duration-300" style="color: var(--text-muted);" :class="sections.priceChanges && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
        </button>

        <div x-show="sections.priceChanges" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            @if($priceChanges->isEmpty())
                <div class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">No price changes in the last 30 days.</div>
            @else
                {{-- Section filters --}}
                <div class="flex flex-wrap items-center gap-2 px-4 py-2.5" style="background: var(--surface-2); border-bottom: 1px solid var(--border);">
                    <select x-model="pcSuburb" class="list-header-filter text-xs">
                        <option value="">All suburbs</option>
                        @foreach($priceChanges->pluck('listing.suburb')->filter()->unique()->sort()->values() as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                    <select x-model="pcDirection" class="list-header-filter text-xs">
                        <option value="">Up & Down</option>
                        <option value="down">Reductions only</option>
                        <option value="up">Increases only</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="ds-table min-w-full text-sm">
                        <thead>
                            <tr style="background: var(--surface-2);">
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider cursor-pointer transition-all duration-300" style="color: var(--text-muted);" @click="sortPc('change_date')" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                                    Date <span x-text="pcSortIcon('change_date')"></span>
                                </th>
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">P24 Ref</th>
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider cursor-pointer transition-all duration-300" style="color: var(--text-muted);" @click="sortPc('suburb')" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                                    Suburb <span x-text="pcSortIcon('suburb')"></span>
                                </th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider cursor-pointer transition-all duration-300" style="color: var(--text-muted);" @click="sortPc('old_price')" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                                    Old Price <span x-text="pcSortIcon('old_price')"></span>
                                </th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider cursor-pointer transition-all duration-300" style="color: var(--text-muted);" @click="sortPc('new_price')" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                                    New Price <span x-text="pcSortIcon('new_price')"></span>
                                </th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider cursor-pointer transition-all duration-300" style="color: var(--text-muted);" @click="sortPc('pct')" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                                    Change <span x-text="pcSortIcon('pct')"></span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in filteredPriceChanges()" :key="row.id">
                                <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                                    <td class="px-4 py-2.5 text-xs" style="color: var(--text-muted);" x-text="row.change_date"></td>
                                    <td class="px-4 py-2.5">
                                        <template x-if="row.p24_url">
                                            <a :href="row.p24_url" target="_blank" rel="noopener" class="hover:underline font-medium transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);" x-text="row.p24_number"></a>
                                        </template>
                                        <template x-if="!row.p24_url">
                                            <span style="color: var(--text-secondary);" x-text="row.p24_number"></span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5" style="color: var(--text-secondary);" x-text="row.suburb"></td>
                                    <td class="px-4 py-2.5 text-right" style="color: var(--text-muted);" x-text="'R ' + Number(row.old_price).toLocaleString('en-ZA')"></td>
                                    <td class="px-4 py-2.5 text-right font-medium" style="color: var(--text-primary);" x-text="'R ' + Number(row.new_price).toLocaleString('en-ZA')"></td>
                                    <td class="px-4 py-2.5 text-right font-semibold"
                                        :class="row.pct < 0 ? 'text-emerald-600' : 'text-red-600'"
                                        x-text="(row.pct > 0 ? '+' : '') + row.pct + '%'"></td>
                                </tr>
                            </template>
                            <tr x-show="filteredPriceChanges().length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">No price changes match your filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== SECTION 3: RECENT LISTINGS (Collapsible + Sortable + Filterable) ===== --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
        <button type="button"
                @click="toggleSection('recentListings')"
                class="w-full text-left px-4 py-3 flex items-center justify-between transition-all duration-300"
                style="background: var(--surface-2);"
                onmouseover="this.style.background='color-mix(in srgb, var(--surface-2) 80%, var(--text-primary) 5%)'"
                onmouseout="this.style.background='var(--surface-2)'">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-bold uppercase tracking-wider" style="color: var(--text-primary);">Recent Listings</h3>
                <span class="text-xs" style="color: var(--text-muted);" x-text="filteredRecent().length + ' of {{ $recentListings->count() }}'"></span>
            </div>
            <svg class="w-4 h-4 transition-transform duration-300" style="color: var(--text-muted);" :class="sections.recentListings && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
        </button>

        <div x-show="sections.recentListings" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            @if($recentListings->isEmpty())
                <div class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">No listings imported yet. Run an import to get started.</div>
            @else
                {{-- Section filters --}}
                <div class="flex flex-wrap items-center gap-2 px-4 py-2.5" style="background: var(--surface-2); border-bottom: 1px solid var(--border);">
                    <select x-model="rlSuburb" class="list-header-filter text-xs">
                        <option value="">All suburbs</option>
                        @foreach($recentListings->pluck('suburb')->filter()->unique()->sort()->values() as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                    <select x-model="rlType" class="list-header-filter text-xs">
                        <option value="">All types</option>
                        @foreach($recentListings->pluck('property_type')->filter()->unique()->sort()->values() as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                    <select x-model="rlBeds" class="list-header-filter text-xs">
                        <option value="">Any beds</option>
                        @foreach($recentListings->pluck('bedrooms')->filter()->unique()->sort()->values() as $b)
                        <option value="{{ $b }}">{{ $b }}+ beds</option>
                        @endforeach
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="ds-table min-w-full text-sm">
                        <thead>
                            <tr style="background: var(--surface-2);">
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider cursor-pointer transition-all duration-300" style="color: var(--text-muted);" @click="sortRl('first_seen')" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                                    Listed <span x-text="rlSortIcon('first_seen')"></span>
                                </th>
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">P24 Ref</th>
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider cursor-pointer transition-all duration-300" style="color: var(--text-muted);" @click="sortRl('suburb')" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                                    Suburb <span x-text="rlSortIcon('suburb')"></span>
                                </th>
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Type</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider cursor-pointer transition-all duration-300" style="color: var(--text-muted);" @click="sortRl('price')" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                                    Price <span x-text="rlSortIcon('price')"></span>
                                </th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider cursor-pointer transition-all duration-300" style="color: var(--text-muted);" @click="sortRl('beds')" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
                                    Beds <span x-text="rlSortIcon('beds')"></span>
                                </th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Baths</th>
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in filteredRecent()" :key="row.id">
                                <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                                    <td class="px-4 py-2.5 text-xs" style="color: var(--text-muted);" x-text="row.first_seen"></td>
                                    <td class="px-4 py-2.5">
                                        <template x-if="row.p24_url">
                                            <a :href="row.p24_url" target="_blank" rel="noopener" class="hover:underline font-medium transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);" x-text="row.p24_number"></a>
                                        </template>
                                        <template x-if="!row.p24_url">
                                            <span style="color: var(--text-secondary);" x-text="row.p24_number"></span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5" style="color: var(--text-secondary);" x-text="row.suburb"></td>
                                    <td class="px-4 py-2.5" style="color: var(--text-secondary);" x-text="row.type || '—'"></td>
                                    <td class="px-4 py-2.5 text-right font-medium" style="color: var(--text-primary);" x-text="'R ' + Number(row.price).toLocaleString('en-ZA')"></td>
                                    <td class="px-4 py-2.5 text-right" style="color: var(--text-secondary);" x-text="row.beds || '—'"></td>
                                    <td class="px-4 py-2.5 text-right" style="color: var(--text-secondary);" x-text="row.baths || '—'"></td>
                                    <td class="px-4 py-2.5">
                                        <span class="ds-badge"
                                              :class="{
                                                  'ds-badge-success': row.status === 'active',
                                                  'ds-badge-info': row.status === 'sold',
                                                  'ds-badge-default': row.status !== 'active' && row.status !== 'sold'
                                              }"
                                              x-text="row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : 'Unknown'"></span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredRecent().length === 0">
                                <td colspan="8" class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">No recent listings match your filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== SECTION 4: IMPORT LOG (Collapsible) ===== --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
        <button type="button"
                @click="toggleSection('importLog')"
                class="w-full text-left px-4 py-3 flex items-center justify-between transition-all duration-300"
                style="background: var(--surface-2);"
                onmouseover="this.style.background='color-mix(in srgb, var(--surface-2) 80%, var(--text-primary) 5%)'"
                onmouseout="this.style.background='var(--surface-2)'">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-bold uppercase tracking-wider" style="color: var(--text-primary);">Import Log</h3>
                <span class="text-xs" style="color: var(--text-muted);">{{ $importLog->count() }} runs</span>
            </div>
            <svg class="w-4 h-4 transition-transform duration-300" style="color: var(--text-muted);" :class="sections.importLog && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
        </button>

        <div x-show="sections.importLog" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            @if($importLog->isEmpty())
                <div class="px-4 py-8 text-center text-sm" style="color: var(--text-muted);">No import runs yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="ds-table min-w-full text-sm">
                        <thead>
                            <tr style="background: var(--surface-2);">
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Date</th>
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Subject</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Found</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">New</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Updated</th>
                                <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($importLog as $log)
                            <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                                <td class="px-4 py-2.5 text-xs" style="color: var(--text-muted);">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-2.5 max-w-xs truncate" style="color: var(--text-secondary);" title="{{ $log->email_subject }}">{{ Str::limit($log->email_subject, 60) }}</td>
                                <td class="px-4 py-2.5 text-right" style="color: var(--text-primary);">{{ $log->listings_found }}</td>
                                <td class="px-4 py-2.5 text-right" style="color: var(--text-primary);">{{ $log->listings_new }}</td>
                                <td class="px-4 py-2.5 text-right" style="color: var(--text-primary);">{{ $log->listings_updated }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="ds-badge
                                        @if($log->status === 'success') ds-badge-success
                                        @elseif($log->status === 'error') ds-badge-danger
                                        @else ds-badge-warning @endif
                                    ">{{ ucfirst($log->status) }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- ===== ALPINE COMPONENT ===== --}}
<script>
function p24Alerts() {
    // Hydrate section states from sessionStorage
    const stored = JSON.parse(sessionStorage.getItem('p24_sections') || '{}');
    const storedSuburbs = JSON.parse(sessionStorage.getItem('p24_suburbs') || '{}');

    return {
        // Global search/filter (bound to list-header)
        globalSearch: '',
        globalSuburb: '',
        globalType: '',

        // Section collapse state (default: all expanded except importLog)
        sections: {
            suburbs: stored.suburbs !== undefined ? stored.suburbs : true,
            priceChanges: stored.priceChanges !== undefined ? stored.priceChanges : true,
            recentListings: stored.recentListings !== undefined ? stored.recentListings : true,
            importLog: stored.importLog !== undefined ? stored.importLog : false,
        },

        // Per-suburb collapse state (default: all collapsed)
        openSuburbs: storedSuburbs,

        toggleSection(key) {
            this.sections[key] = !this.sections[key];
            sessionStorage.setItem('p24_sections', JSON.stringify(this.sections));
        },

        toggleSuburb(hash) {
            this.openSuburbs[hash] = !this.openSuburbs[hash];
            sessionStorage.setItem('p24_suburbs', JSON.stringify(this.openSuburbs));
        },

        // ── Price Changes sort + filter ──
        pcSort: 'change_date',
        pcDir: 'desc',
        pcSuburb: '',
        pcDirection: '',

        pcData: @json($priceChangesData),

        sortPc(field) {
            if (this.pcSort === field) {
                this.pcDir = this.pcDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.pcSort = field;
                this.pcDir = field === 'change_date' ? 'desc' : 'asc';
            }
        },

        pcSortIcon(field) {
            if (this.pcSort !== field) return '';
            return this.pcDir === 'asc' ? '\u2191' : '\u2193';
        },

        filteredPriceChanges() {
            let data = [...this.pcData];
            const gs = this.globalSearch.toLowerCase();

            // Global search
            if (gs) {
                data = data.filter(r =>
                    r.suburb.toLowerCase().includes(gs) ||
                    r.p24_number.toLowerCase().includes(gs)
                );
            }
            // Global suburb filter
            if (this.globalSuburb) {
                data = data.filter(r => r.suburb === this.globalSuburb);
            }

            // Section filters
            if (this.pcSuburb) {
                data = data.filter(r => r.suburb === this.pcSuburb);
            }
            if (this.pcDirection === 'down') {
                data = data.filter(r => r.pct < 0);
            } else if (this.pcDirection === 'up') {
                data = data.filter(r => r.pct > 0);
            }

            // Sort
            const dir = this.pcDir === 'asc' ? 1 : -1;
            const sortKey = this.pcSort;
            data.sort((a, b) => {
                let va = a[sortKey], vb = b[sortKey];
                if (typeof va === 'string') { va = va.toLowerCase(); vb = (vb || '').toLowerCase(); }
                if (va < vb) return -1 * dir;
                if (va > vb) return 1 * dir;
                return 0;
            });

            return data;
        },

        // ── Recent Listings sort + filter ──
        rlSort: 'first_seen',
        rlDir: 'desc',
        rlSuburb: '',
        rlType: '',
        rlBeds: '',

        rlData: @json($recentListingsData),

        sortRl(field) {
            if (this.rlSort === field) {
                this.rlDir = this.rlDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.rlSort = field;
                this.rlDir = field === 'first_seen' || field === 'price' ? 'desc' : 'asc';
            }
        },

        rlSortIcon(field) {
            if (this.rlSort !== field) return '';
            return this.rlDir === 'asc' ? '\u2191' : '\u2193';
        },

        filteredRecent() {
            let data = [...this.rlData];
            const gs = this.globalSearch.toLowerCase();

            // Global search
            if (gs) {
                data = data.filter(r =>
                    r.suburb.toLowerCase().includes(gs) ||
                    r.p24_number.toLowerCase().includes(gs) ||
                    (r.type || '').toLowerCase().includes(gs)
                );
            }
            // Global filters
            if (this.globalSuburb) {
                data = data.filter(r => r.suburb === this.globalSuburb);
            }
            if (this.globalType) {
                data = data.filter(r => r.type === this.globalType);
            }

            // Section filters
            if (this.rlSuburb) {
                data = data.filter(r => r.suburb === this.rlSuburb);
            }
            if (this.rlType) {
                data = data.filter(r => r.type === this.rlType);
            }
            if (this.rlBeds) {
                const minBeds = parseInt(this.rlBeds);
                data = data.filter(r => r.beds !== null && r.beds >= minBeds);
            }

            // Sort
            const dir = this.rlDir === 'asc' ? 1 : -1;
            const sortKey = this.rlSort;
            data.sort((a, b) => {
                let va = a[sortKey], vb = b[sortKey];
                if (typeof va === 'string') { va = va.toLowerCase(); vb = (vb || '').toLowerCase(); }
                if (va === null || va === undefined) va = '';
                if (vb === null || vb === undefined) vb = '';
                if (va < vb) return -1 * dir;
                if (va > vb) return 1 * dir;
                return 0;
            });

            return data;
        },
    };
}
</script>

</x-app-layout>
