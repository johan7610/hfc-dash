@extends('layouts.corex-app')

@section('corex-content')
<div style="max-width:1200px;margin:0 auto;padding:0 20px;">

    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;">
        <div>
            <h1 style="font-size:1.25rem;font-weight:600;color:var(--text-primary);margin:0;">Presentations Analytics</h1>
            <p style="font-size:0.8125rem;color:var(--text-muted);margin:4px 0 0 0;">
                Lifecycle pipeline: generated → shared → viewed → leads → outcomes.
            </p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:14px;margin-bottom:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;">
        <div>
            <label style="display:block;font-size:0.6875rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">From</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}"
                   style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:4px;font-size:0.8125rem;">
        </div>
        <div>
            <label style="display:block;font-size:0.6875rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">To</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}"
                   style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:4px;font-size:0.8125rem;">
        </div>
        @if($isManager)
            <div>
                <label style="display:block;font-size:0.6875rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">Agent</label>
                <select name="agent_id" style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:4px;font-size:0.8125rem;">
                    <option value="">All</option>
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}" @selected((int) $agentFilter === (int) $a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div style="display:flex;align-items:flex-end;">
            <button type="submit" class="corex-btn-primary" style="font-size:0.8125rem;padding:7px 14px;">Apply</button>
        </div>
    </form>

    {{-- Funnel metrics --}}
    @php
        $sharedPct = $generatedCount > 0 ? round($sharedCount / $generatedCount * 100, 1) : 0;
        $viewedPct = $generatedCount > 0 ? round($viewedCount / $generatedCount * 100, 1) : 0;
        $recordedPct = $generatedCount > 0 ? round($outcomeRecordedCount / $generatedCount * 100, 1) : 0;
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:16px;">
        @foreach([
            ['Presentations',  $generatedCount, null,                       null],
            ['Shared',         $sharedCount,    $sharedPct . '%',           'of generated'],
            ['Viewed at least once', $viewedCount, $viewedPct . '%',         'of generated'],
            ['Leads captured', $leadCount,      null,                       'from teaser links'],
            ['Outcomes recorded', $outcomeRecordedCount, $recordedPct . '%', 'of generated'],
            ['Outcomes pending',  $outcomePendingCount,  null,               'no outcome yet'],
            ['Win rate',          $winRate . '%',         null,              ($wonCount > 0 ? $wonCount . ' won' : 'no wins yet')],
        ] as [$label, $value, $pct, $sub])
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:12px 14px;">
                <div style="font-size:0.625rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;">{{ $label }}</div>
                <div style="font-size:1.25rem;color:var(--text-primary);font-weight:700;margin-top:3px;">{{ $value }}</div>
                @if($pct)
                    <div style="font-size:0.6875rem;color:var(--text-muted);margin-top:1px;">{{ $pct }} {{ $sub }}</div>
                @elseif($sub)
                    <div style="font-size:0.6875rem;color:var(--text-muted);margin-top:1px;">{{ $sub }}</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Funnel visualization --}}
    @if($generatedCount > 0)
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:14px;margin-bottom:16px;">
            <h2 class="ds-section-header" style="margin:0 0 10px 0;">Funnel</h2>
            @php
                $rows = [
                    ['Generated',          $generatedCount,  '#0f172a'],
                    ['Shared with seller', $sharedCount,     '#0ea5e9'],
                    ['Viewed by seller',   $viewedCount,     '#00b594'],
                    ['Outcome recorded',   $outcomeRecordedCount, '#8b5cf6'],
                    ['Won mandate / sale', $wonCount,        '#16a34a'],
                ];
            @endphp
            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach($rows as [$lbl, $n, $colour])
                    @php $w = $generatedCount > 0 ? max(2, round(($n / $generatedCount) * 100)) : 0; @endphp
                    <div style="display:grid;grid-template-columns:180px 1fr 50px;align-items:center;gap:10px;">
                        <div style="font-size:0.8125rem;color:var(--text-secondary);">{{ $lbl }}</div>
                        <div style="background:var(--surface-2);height:18px;border-radius:3px;overflow:hidden;">
                            <div style="background:{{ $colour }};height:100%;width:{{ $w }}%;"></div>
                        </div>
                        <div style="font-size:0.8125rem;color:var(--text-primary);font-weight:600;text-align:right;">{{ $n }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Per-agent leaderboard (managers only) --}}
    @if($isManager && $byAgent->isNotEmpty())
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;overflow:hidden;">
            <div style="padding:14px;border-bottom:1px solid var(--border);">
                <h2 class="ds-section-header" style="margin:0;">Per-agent breakdown</h2>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
                <thead>
                    <tr style="background:var(--surface-2);color:var(--text-muted);font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.04em;">
                        <th style="text-align:left;padding:10px 12px;">Agent</th>
                        <th style="text-align:right;padding:10px 12px;">Generated</th>
                        <th style="text-align:right;padding:10px 12px;">Outcomes recorded</th>
                        <th style="text-align:right;padding:10px 12px;">Won</th>
                        <th style="text-align:right;padding:10px 12px;">Win rate</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($byAgent as $a)
                    <tr style="border-top:1px solid var(--border);">
                        <td style="padding:10px 12px;color:var(--text-primary);">{{ $a['name'] }}</td>
                        <td style="padding:10px 12px;text-align:right;color:var(--text-primary);font-weight:500;">{{ $a['generated'] }}</td>
                        <td style="padding:10px 12px;text-align:right;color:var(--text-secondary);">{{ $a['recorded'] }}</td>
                        <td style="padding:10px 12px;text-align:right;color:#16a34a;font-weight:600;">{{ $a['won'] }}</td>
                        <td style="padding:10px 12px;text-align:right;color:var(--text-primary);font-weight:500;">
                            {{ $a['win_rate'] !== null ? $a['win_rate'] . '%' : '—' }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($generatedCount === 0)
        <div style="padding:24px;text-align:center;background:var(--surface);border:1px dashed var(--border);border-radius:6px;color:var(--text-muted);font-size:0.875rem;">
            No presentations generated in the selected window.
        </div>
    @endif

</div>
@endsection
