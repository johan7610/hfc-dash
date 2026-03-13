@extends('layouts.corex')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        {{-- Page Header --}}
        <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Worksheet Market &mdash; Company</h2>
                    <p class="text-sm text-white/60 mt-1">Set planned average sale price per agent</p>
                </div>
                <form method="GET" class="flex items-center gap-2">
                    <input type="month" name="period" value="{{ $period }}"
                           class="rounded-md text-sm px-3 py-1.5 border border-white/20 bg-white/10 text-white transition-all duration-300 [&::-webkit-calendar-picker-indicator]:invert" />
                    <button type="submit" class="corex-btn-primary text-sm">Go</button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md px-4 py-3 text-sm" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10b981;">
                {{ session('status') }}
            </div>
        @endif

        @php
            $aw = $avgWindow ?? 'period';
            $sf = $stageFilter ?? ['pending'=>true,'granted'=>true,'registered'=>true];
            $mb = $marketByBranch ?? [];
            $amb = $agentMarketByBranch ?? [];
        @endphp

        {{-- Filters --}}
        <div class="rounded-md p-5" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Deal Register Market Averages (per branch)</h3>
                    <p class="text-xs mt-1" style="color: var(--text-muted);">
                        Window + stage filters apply.
                        @if(!empty($dateFrom) && !empty($dateTo))
                            <span class="ml-2"><strong>Window:</strong> {{ $dateFrom }} &rarr; {{ $dateTo }}</span>
                        @endif
                    </p>
                </div>

                <form method="GET" class="flex flex-wrap gap-3 items-end">
                    <input type="hidden" name="period" value="{{ $period }}" />
                    <div>
                        <label class="block mb-1 text-xs font-medium" style="color: var(--text-secondary);">Window</label>
                        <select name="avg_window"
                                class="rounded-md px-3 py-2 text-sm transition-all duration-300"
                                style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                            <option value="period" {{ $aw==='period'?'selected':'' }}>This month</option>
                            <option value="3m" {{ $aw==='3m'?'selected':'' }}>Last 3 months</option>
                            <option value="6m" {{ $aw==='6m'?'selected':'' }}>Last 6 months</option>
                            <option value="all" {{ $aw==='all'?'selected':'' }}>All time</option>
                        </select>
                    </div>

                    <div class="flex gap-3 items-center">
                        <label class="text-sm flex items-center gap-1.5" style="color: var(--text-secondary);">
                            <input type="checkbox" name="st_pending" value="1" class="rounded-sm" style="accent-color: var(--brand-button, #0ea5e9);" {{ !empty($sf['pending'])?'checked':'' }}> Pending
                        </label>
                        <label class="text-sm flex items-center gap-1.5" style="color: var(--text-secondary);">
                            <input type="checkbox" name="st_granted" value="1" class="rounded-sm" style="accent-color: var(--brand-button, #0ea5e9);" {{ !empty($sf['granted'])?'checked':'' }}> Granted
                        </label>
                        <label class="text-sm flex items-center gap-1.5" style="color: var(--text-secondary);">
                            <input type="checkbox" name="st_registered" value="1" class="rounded-sm" style="accent-color: var(--brand-button, #0ea5e9);" {{ !empty($sf['registered'])?'checked':'' }}> Registered
                        </label>
                    </div>

                    <button class="corex-btn-primary text-sm">Apply</button>
                </form>
            </div>
        </div>

        {{-- Branch Sections --}}
        @php
            $agentsByBranch = $agents->groupBy('branch_id');
            $bm = $branchMarket ?? [];
        @endphp

        @foreach($agentsByBranch as $bid => $group)
            @php
                $branchName = $bid ? ($branches[$bid]->name ?? '-') : '-';
                $ma = $bm[(int)$bid] ?? ['deals_count'=>0,'avg_sale_price_inc_vat'=>0,'avg_sale_price_ex_vat'=>0,'effective_commission_percent_ex_vat'=>0];
            @endphp

            <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">

                {{-- Branch Header --}}
                <div class="px-5 py-4" style="border-bottom: 1px solid var(--border);">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                        <div>
                            <h3 class="text-base font-bold" style="color: var(--text-primary);">{{ $branchName }}</h3>
                            <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                                Deal Register Market Averages
                                @php
                                    $st = [];
                                    if (!empty($sf['pending'])) $st[] = 'Pending';
                                    if (!empty($sf['granted'])) $st[] = 'Granted';
                                    if (!empty($sf['registered'])) $st[] = 'Registered';
                                @endphp
                                @if(!empty($dateFrom) && !empty($dateTo))
                                    <span class="ml-2"><strong>Window:</strong> {{ $dateFrom }} &rarr; {{ $dateTo }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Market Averages KPIs --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-5">
                    <div class="rounded-md p-4" style="background: var(--surface-2); border: 1px solid var(--border);">
                        <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-muted);">Deals counted</div>
                        <div class="text-xl font-bold mt-1" style="color: var(--text-primary);">{{ (int)($ma['deals_count'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-md p-4" style="background: var(--surface-2); border: 1px solid var(--border);">
                        <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-muted);">Avg Sale Price (Incl VAT)</div>
                        <div class="text-xl font-bold mt-1" style="color: var(--text-primary);">R {{ number_format((float)($ma['avg_sale_price_inc_vat'] ?? 0), 2) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted);">Ex VAT: R {{ number_format((float)($ma['avg_sale_price_ex_vat'] ?? 0), 2) }}</div>
                    </div>
                    <div class="rounded-md p-4" style="background: var(--surface-2); border: 1px solid var(--border);">
                        <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-muted);">Effective Comm % (Ex VAT)</div>
                        <div class="text-xl font-bold mt-1" style="color: var(--text-primary);">{{ number_format((float)($ma['effective_commission_percent_ex_vat'] ?? 0), 2) }}%</div>
                    </div>
                </div>

                {{-- Agent Table --}}
                <form method="POST" action="{{ route('admin.worksheet-market.store', request()->query()) }}">
                    @csrf
                    <input type="hidden" name="period" value="{{ $period }}" />
                    <input type="hidden" name="branch_id" value="{{ $bid }}" />

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr style="background: var(--surface-2); border-bottom: 1px solid var(--border);">
                                    <th class="text-left px-4 py-3 font-medium" style="color: var(--text-secondary);">Agent</th>
                                    <th class="text-left px-4 py-3 font-medium" style="color: var(--text-secondary);">Avg Sales Override</th>
                                    <th class="text-left px-4 py-3 font-medium" style="color: var(--text-secondary);">Comm % Override (Ex VAT)</th>
                                    <th class="text-left px-4 py-3 font-medium" style="color: var(--text-secondary);">Lock</th>
                                    <th class="text-left px-4 py-3 font-medium" style="color: var(--text-secondary);">Actual Deals</th>
                                    <th class="text-left px-4 py-3 font-medium" style="color: var(--text-secondary);">Actual Avg Sale (Inc)</th>
                                    <th class="text-left px-4 py-3 font-medium" style="color: var(--text-secondary);">Actual Eff Comm % (Ex)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group as $a)
                                    @php
                                        $w = $worksheets->get($a->id);
                                        $curAvg = $w->avg_sale_price_admin ?? null;
                                        $plannedComm = $w->commission_percent ?? null;
                                        $curComm = $w->commission_percent_admin ?? null;
                                        $lockComm = (bool)($w->commission_percent_locked ?? false);
                                        $m = ($amb[(int)$bid][(int)$a->id] ?? ['deals_count'=>0,'avg_sale_price_inc_vat'=>0,'effective_commission_percent_ex_vat'=>0]);
                                    @endphp

                                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                                        onmouseenter="this.style.background='var(--surface-2)'" onmouseleave="this.style.background='transparent'">
                                        <td class="px-4 py-3 whitespace-nowrap min-w-[220px]">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold" style="color: var(--text-primary);">{{ $a->name }}</span>
                                                @if(($a->role ?? '') === 'branch_manager')
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-semibold"
                                                          style="background: var(--brand-icon, #0ea5e9); color: #fff;">BM</span>
                                                @endif
                                            </div>
                                        </td>

                                        <td class="px-4 py-3">
                                            <input type="number" step="0.01" name="avg[{{ $a->id }}]" value="{{ old('avg.'.$a->id, $curAvg) }}"
                                                   class="rounded-md px-3 py-2 w-40 text-sm transition-all duration-300"
                                                   style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                                                   onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px rgba(14,165,233,0.15)'"
                                                   onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'"
                                                   placeholder="e.g. 1200000" />
                                            <div class="text-xs mt-1" style="color: var(--text-muted);">
                                                Current: {{ $curAvg === null ? 'NULL' : ('R ' . number_format((float)$curAvg, 2)) }}
                                            </div>
                                        </td>

                                        <td class="px-4 py-3">
                                            <input id="comm_{{ $a->id }}" type="number" step="0.01" name="comm[{{ $a->id }}]" value="{{ old('comm.'.$a->id, $curComm) }}"
                                                   class="rounded-md px-3 py-2 w-32 text-sm transition-all duration-300"
                                                   style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                                                   onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px rgba(14,165,233,0.15)'"
                                                   onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'"
                                                   placeholder="e.g. 7.50" {{ $lockComm ? 'readonly' : '' }}
                                                   @if($lockComm) data-locked="1" @endif />
                                            <div class="text-xs mt-1" style="color: var(--text-muted);">
                                                Planned: {{ $plannedComm === null ? 'NULL' : (number_format((float)$plannedComm, 2) . '%') }}
                                                — Current: {{ $curComm === null ? 'NULL' : (number_format((float)$curComm, 2) . '%') }}
                                            </div>
                                        </td>

                                        <td class="px-4 py-3">
                                            <label class="inline-flex items-center gap-2 text-sm" style="color: var(--text-secondary);">
                                                <input type="checkbox" name="lock[{{ $a->id }}]" value="1"
                                                       style="accent-color: var(--brand-button, #0ea5e9);"
                                                       data-comm="#comm_{{ $a->id }}" {{ old('lock.'.$a->id, $lockComm ? 1 : 0) ? 'checked' : '' }}>
                                                Locked
                                            </label>
                                        </td>

                                        <td class="px-4 py-3" style="color: var(--text-secondary);">{{ (int)($m['deals_count'] ?? 0) }}</td>
                                        <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">R {{ number_format((float)($m['avg_sale_price_inc_vat'] ?? 0), 2) }}</td>
                                        <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">{{ number_format((float)($m['effective_commission_percent_ex_vat'] ?? 0), 2) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-4 flex items-center justify-between" style="border-top: 1px solid var(--border);">
                        <span class="text-xs" style="color: var(--text-muted);">Saves only this branch's users.</span>
                        <button class="corex-btn-primary text-sm font-semibold">Save {{ $branchName }}</button>
                    </div>
                </form>
            </div>
        @endforeach

    </div>

    <script>
    document.addEventListener('change', function (e) {
        const el = e.target;
        if (!el || el.type !== 'checkbox' || !el.name || el.name.indexOf('lock[') !== 0) return;
        const sel = el.getAttribute('data-comm');
        if (!sel) return;
        const input = document.querySelector(sel);
        if (!input) return;
        input.readOnly = !!el.checked;
        if (el.checked) {
            input.style.opacity = '0.5';
        } else {
            input.style.opacity = '1';
        }
    });

    // Init locked state on load
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[type="checkbox"][name^="lock["]').forEach(function (cb) {
            if (cb.checked) {
                const sel = cb.getAttribute('data-comm');
                if (sel) {
                    const input = document.querySelector(sel);
                    if (input) input.style.opacity = '0.5';
                }
            }
        });
    });
    </script>
@endsection
