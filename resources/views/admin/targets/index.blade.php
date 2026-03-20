@extends('layouts.corex')

@section('content')
    <style>
        .targets-table-wrap { overflow-x: auto; }
        .targets-table { min-width: 1100px; }
        .targets-sticky { position: sticky; left: 0; z-index: 2; background: var(--surface); }
        thead .targets-sticky { z-index: 3; background: var(--surface-2, var(--surface)); }
        .th-vert {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            padding: 10px 6px !important;
            height: 140px;
            vertical-align: bottom;
            text-align: left;
        }
        .cell-num { width: 72px; }
        .cell-num-sm { width: 60px; }
        .cell-num-lg { width: 110px; }
        .targets-table thead th { position: sticky; top: 0; z-index: 4; }
        .targets-table tbody tr { transition: all 300ms; }
        .targets-table tbody tr:hover td { background: var(--surface-2, rgba(0,0,0,0.03)); }
    </style>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        {{-- Page Header --}}
        <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Targets</h2>
                    <div class="text-sm text-white/60">
                        @if($isAdmin) Admin scope
                        @elseif($isBM) Branch Manager scope
                        @else Agent scope
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @if(!empty($isAdmin))
                        <a href="{{ route('admin.targets.activity.setup') }}" class="corex-btn-outline text-sm">Activity Setup</a>
                    @endif

                    @if($canEditTargets)
                        <form method="POST" action="{{ route('admin.targets.carry-forward') }}" class="inline" onsubmit="return confirm('Copy last month\'s targets to this month? Existing entries will not be overwritten.')">
                            @csrf
                            <button type="submit" class="corex-btn-outline text-sm">Copy Previous Month</button>
                        </form>
                    @endif

                    @if(!$isAgent)
                        <form method="GET" action="{{ route('admin.targets') }}" class="flex items-center gap-2">
                            <select name="period" class="rounded-md border border-white/20 bg-white/10 text-white text-sm px-3 py-1.5 transition-all duration-300 [&>option]:text-black">
                                @foreach($periods as $p)
                                    <option value="{{ $p }}" {{ $p === $period ? 'selected' : '' }}>{{ $p }}</option>
                                @endforeach
                            </select>
                            <button class="corex-btn-primary text-sm">View</button>
                        </form>
                    @else
                        <div class="text-white/80 text-sm font-medium">{{ $period }}</div>
                    @endif
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md px-4 py-3 text-sm" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10b981;">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="rounded-md px-4 py-3 text-sm" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444;">{{ $errors->first() }}</div>
        @endif

        {{-- MONTHLY TARGETS (Admin/BM only) --}}
        @if($canEditTargets)
            <form method="POST" action="{{ route('admin.targets.save') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="period" value="{{ $period }}"/>

                <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
                    <div class="px-5 py-4 flex items-center justify-between" style="border-bottom: 1px solid var(--border);">
                        <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Monthly Targets for {{ $period }}</h3>
                        <button class="corex-btn-primary text-sm">Save Targets</button>
                    </div>

                    <div class="targets-table-wrap">
                        <table class="min-w-full text-sm targets-table ds-table">
                            <thead>
                                <tr>
                                    <th class="text-left px-4 py-3 targets-sticky" style="color: var(--text-secondary);">Agent</th>
                                    <th class="text-left px-4 py-3" style="color: var(--text-secondary);">Monthly Points Target</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($users as $u)
                                    @php
                                        $t = $targets[$u->id] ?? null;
                                        $a = $actuals[$u->id] ?? null;

                                        $lt = (int)($t->listings_target ?? 0);
                                        $la = (int)($a->listings_actual ?? 0);
                                        $lv = $la - $lt;

                                        $dt = (int)($t->deals_target ?? 0);
                                        $da = (int)($a->deals_actual ?? 0);
                                        $dv = $da - $dt;

                                        $vt = (float)($t->value_target ?? 0);
                                        $va = (float)($a->value_actual ?? 0);
                                        $vv = $va - $vt;

                                        $branchName = $branchNames[$u->branch_id] ?? '-';
                                    @endphp

                                    <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);">
                                        <td class="px-4 py-3 font-semibold targets-sticky" style="color: var(--text-primary);">
                                            {{ $u->name }}
                                            <div class="text-xs mt-0.5" style="color: var(--text-muted);">Branch: {{ $branchName }}</div>
                                        </td>

                                        <td class="px-4 py-3">
                                            <input type="number" min="0"
                                                   class="rounded-md px-3 py-2 w-40 text-sm transition-all duration-300"
                                                   style="background: var(--surface-2, #f0f2f8); border: 1px solid var(--border); color: var(--text-primary);"
                                                   onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px rgba(14,165,233,0.15)'"
                                                   onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'"
                                                   name="targets[{{ $u->id }}][points_target]"
                                                   value="{{ old('targets.'.$u->id.'.points_target', (int)($t->points_target ?? 0)) }}">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="px-4 py-6 text-center" style="color: var(--text-muted);" colspan="10">No agents found in scope.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        @endif

    </div>
@endsection
