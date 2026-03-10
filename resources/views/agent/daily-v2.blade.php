@extends('layouts.corex')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="text-sm text-white/60">
                    <a class="hover:underline text-white/60" href="{{ route('agent.daily.summary') }}">&larr; Daily Summary</a>
                </div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight mt-1">Daily Activity</h2>
                <div class="text-sm text-white/60">
                    Date: <span class="font-medium text-white">{{ $selectedDate }}</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('agent.daily.print', ['date' => $selectedDate]) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-md border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-medium text-white transition-all duration-300 hover:bg-white/20">
                   Print Sheet
                </a>
                <form method="GET" action="{{ route('agent.daily') }}" class="flex items-center gap-2">
                    <input
                        type="date"
                        name="date"
                        value="{{ $selectedDate }}"
                        class="rounded-md border-0 bg-white/10 text-white text-sm px-3 py-1.5 placeholder:text-white/40 transition-all duration-300"
                        onchange="this.form.submit()"
                    />
                </form>
            </div>
        </div>
    </div>

    {{-- Week strip --}}
    @if(isset($agentDailyWeek) && isset($agentDailyWeek['days']))
        <div class="rounded-md p-3" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="flex flex-wrap gap-2">
                @foreach($agentDailyWeek['days'] as $d)
                    <a href="{{ route('agent.daily', ['date' => $d['date']]) }}"
                       class="px-3 py-2 rounded-md text-sm transition-all duration-300
                       {{ $d['is_selected'] ? 'text-white' : '' }}"
                       style="{{ $d['is_selected']
                           ? 'background: var(--brand-default, #0b2a4a); border: 1px solid var(--brand-default, #0b2a4a); color: #fff;'
                           : 'background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);' }}"
                       onmouseover="{{ !$d['is_selected'] ? "this.style.background='var(--surface)'" : '' }}"
                       onmouseout="{{ !$d['is_selected'] ? "this.style.background='var(--surface-2)'" : '' }}">
                        <div class="font-medium">{{ $d['label'] }}</div>
                        @if($d['is_today'])
                            <div class="text-xs opacity-80">today</div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Monthly summary --}}
    <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border); border-left: 3px solid var(--brand-icon, #0ea5e9);">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-muted);">Month</div>
                <div class="text-lg font-semibold mt-0.5" style="color: var(--text-primary);">{{ $period }}</div>
            </div>

            <div class="grid grid-cols-3 gap-6 text-right">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-muted);">Monthly target</div>
                    <div class="text-lg font-semibold mt-0.5" style="color: var(--text-primary);">{{ (int)($monthlyTarget ?? 0) }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-muted);">Points MTD</div>
                    <div class="text-lg font-semibold mt-0.5" style="color: var(--text-primary);">{{ (int)($mtdPoints ?? 0) }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide" style="color: var(--text-muted);">Remaining</div>
                    <div class="text-lg font-semibold mt-0.5" style="color: var(--text-primary);">{{ (int)($remainingPoints ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Capture activity form --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
        <form method="POST" action="{{ route('agent.daily') }}">
            @csrf
            <input type="hidden" name="activity_date" value="{{ $selectedDate }}"/>

            <div class="px-5 py-4 flex items-center justify-between gap-4" style="border-bottom: 1px solid var(--border);">
                <div>
                    <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Capture activity</h3>
                    <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                        Date: <span class="font-medium" style="color: var(--text-primary);">{{ $selectedDate }}</span>
                    </div>
                </div>

                <button class="inline-flex items-center gap-1.5 rounded-md px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all duration-300 hover:opacity-90"
                        style="background: var(--brand-button, #0ea5e9);">
                    Save
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm ds-table">
                    <thead>
                        <tr style="background: var(--surface-2);">
                            <th class="text-left px-4 py-2.5 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-secondary);">Activity</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold uppercase tracking-wide w-32" style="color: var(--text-secondary);">Weight</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold uppercase tracking-wide w-40" style="color: var(--text-secondary);">Done / Qty</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold uppercase tracking-wide w-40" style="color: var(--text-secondary);">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($definitions as $def)
                            @php
                                $val = (int)($values[$def->id] ?? 0);
                                $pts = $val * (int)$def->weight;
                            @endphp
                            <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);"
                                onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                                <td class="px-4 py-3">
                                    <div class="font-medium" style="color: var(--text-primary);">{{ $def->name }}</div>
                                </td>
                                <td class="px-4 py-3" style="color: var(--text-secondary);">{{ (int)$def->weight }}</td>
                                <td class="px-4 py-3">
                                    @php($mode = (string)($def->scoring_mode ?? 'count'))
                                    @if($mode === 'once')
                                        <div class="flex items-center gap-3">
                                            <input type="hidden" name="values[{{ $def->id }}]" value="0">
                                            <label class="inline-flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    name="values[{{ $def->id }}]"
                                                    value="1"
                                                    @checked($val > 0)
                                                    class="h-5 w-5 rounded-md transition-all duration-300"
                                                    style="border-color: var(--border);"
                                                >
                                                <span class="text-sm" style="color: var(--text-secondary);">Done</span>
                                            </label>
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-muted);">Tick to score once today.</div>
                                    @else
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            name="values[{{ $def->id }}]"
                                            value="{{ $val }}"
                                            class="w-28 rounded-md px-3 py-2 text-sm transition-all duration-300"
                                            style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                                            onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px color-mix(in srgb, var(--brand-button, #0ea5e9) 20%, transparent)'"
                                            onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'"
                                        />
                                        <div class="text-xs mt-1" style="color: var(--text-muted);">Enter quantity to score per action.</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3" style="color: var(--text-primary);">
                                    {{ $pts }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center" style="color: var(--text-muted);">
                                    No enabled activity definitions found for your branch.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3" style="border-top: 1px solid var(--border);">
                <span class="text-sm font-medium" style="color: var(--text-primary);">Total points today:</span>
                <span class="text-sm font-semibold ml-1" style="color: var(--brand-icon, #0ea5e9);">{{ $totalPoints }}</span>
            </div>
        </form>
    </div>

    <div class="text-xs" style="color: var(--text-muted);">
        v2 uses activity_definitions + daily_activity_entries (no legacy dynamic columns).
    </div>
</div>
@endsection
