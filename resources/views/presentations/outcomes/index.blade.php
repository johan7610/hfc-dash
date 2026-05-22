@extends('layouts.corex-app')

@section('corex-content')
@php
    use App\Models\PresentationOutcome;
    $reasonLabels = [
        'price_too_high_seller'      => 'Seller insisted on higher price',
        'price_too_low_seller'       => 'Seller wanted to underprice',
        'commission_concerns'        => 'Commission concerns',
        'sole_mandate_concerns'      => 'Sole mandate concerns',
        'family_pressure'            => 'Family pressure',
        'existing_relationship'      => 'Prior agent relationship',
        'agency_reputation'          => 'Agency reputation',
        'agent_personality'          => 'Agent personality fit',
        'timing_change'              => 'Timing change',
        'property_issues_discovered' => 'Property issues',
        'price_match_with_other'     => 'Competitor matched price',
        'other'                      => 'Other',
    ];
    $outcomeLabel = function (string $o): array {
        $map = [
            'won_mandate'           => ['Won mandate',         '#16a34a', '#dcfce7'],
            'won_sale'              => ['Won + sale',          '#0ea5e9', '#dbeafe'],
            'lost_to_competitor'    => ['Lost to competitor',  '#dc2626', '#fee2e2'],
            'lost_to_no_decision'   => ['No decision',         '#92400e', '#fef3c7'],
            'lost_to_price_dispute' => ['Price/strategy',      '#dc2626', '#fee2e2'],
            'lost_to_no_response'   => ['No response',         '#64748b', '#f1f5f9'],
            'still_pending'         => ['Pending',             '#0ea5e9', '#dbeafe'],
            'other'                 => ['Other',               '#64748b', '#f1f5f9'],
        ];
        return $map[$o] ?? ['Outcome', '#64748b', '#f1f5f9'];
    };
    $wonTotal = $wonMandate + $wonSale;
    $winRate  = $totalOutcomes > 0 ? round($wonTotal / $totalOutcomes * 100, 1) : 0.0;
    $lossMax  = !empty($lossReasons) ? max($lossReasons) : 1;
@endphp
<div style="max-width:1200px;margin:0 auto;padding:0 20px;">

    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;">
        <div>
            <h1 style="font-size:1.25rem;font-weight:600;color:var(--text-primary);margin:0;">Outcomes Dashboard</h1>
            <p style="font-size:0.8125rem;color:var(--text-muted);margin:4px 0 0 0;">
                Win rate, loss reasons, and pipeline health across {{ $totalPresentations }} presentation{{ $totalPresentations === 1 ? '' : 's' }} in the selected window.
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
        <div>
            <label style="display:block;font-size:0.6875rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">Outcome</label>
            <select name="outcome" style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:4px;font-size:0.8125rem;">
                <option value="">All</option>
                @foreach(PresentationOutcome::ALL_OUTCOMES as $o)
                    <option value="{{ $o }}" @selected($outcomeFilter === $o)>{{ $outcomeLabel($o)[0] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display:block;font-size:0.6875rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">Loss reason</label>
            <select name="reason" style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:4px;font-size:0.8125rem;">
                <option value="">All</option>
                @foreach($reasonLabels as $k => $v)
                    <option value="{{ $k }}" @selected($reasonFilter === $k)>{{ $v }}</option>
                @endforeach
            </select>
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

    {{-- Top metrics --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:16px;">
        @php
            $metrics = [
                ['Total outcomes',     $totalOutcomes, null],
                ['Won mandates',       $wonTotal,      $totalOutcomes > 0 ? round($wonTotal / $totalOutcomes * 100, 1) . '%' : '—'],
                ['Lost to competitor', $lostComp,      $totalOutcomes > 0 ? round($lostComp / $totalOutcomes * 100, 1) . '%' : '—'],
                ['No decision',        $lostNoDec,     $totalOutcomes > 0 ? round($lostNoDec / $totalOutcomes * 100, 1) . '%' : '—'],
                ['Avg days to outcome', $avgDays !== null ? ($avgDays . 'd') : '—', null],
                ['Still pending',      $stillPending,  null],
            ];
        @endphp
        @foreach($metrics as [$lbl, $val, $sub])
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:12px 14px;">
                <div style="font-size:0.625rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;font-weight:600;">{{ $lbl }}</div>
                <div style="font-size:1.25rem;color:var(--text-primary);font-weight:700;margin-top:3px;">{{ $val }}</div>
                @if($sub)
                    <div style="font-size:0.6875rem;color:var(--text-muted);margin-top:1px;">{{ $sub }}</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Loss reasons chart --}}
    @if(!empty($lossReasons))
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:14px;margin-bottom:16px;">
            <h2 class="ds-section-header" style="margin:0 0 10px 0;">Loss reasons breakdown</h2>
            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach($lossReasons as $key => $count)
                    @php $pct = max(2, round(($count / $lossMax) * 100)); @endphp
                    <div style="display:grid;grid-template-columns:200px 1fr 30px;align-items:center;gap:10px;">
                        <div style="font-size:0.75rem;color:var(--text-secondary);">{{ $reasonLabels[$key] ?? $key }}</div>
                        <div style="background:#fee2e2;height:18px;border-radius:3px;overflow:hidden;">
                            <div style="background:#dc2626;height:100%;width:{{ $pct }}%;"></div>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-secondary);text-align:right;">{{ $count }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Outcomes list --}}
    @if($outcomes->isEmpty())
        <div style="padding:24px;text-align:center;background:var(--surface);border:1px dashed var(--border);border-radius:6px;color:var(--text-muted);font-size:0.875rem;">
            No outcomes match the current filters.
        </div>
    @else
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
                <thead>
                    <tr style="background:var(--surface-2);color:var(--text-muted);font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.04em;">
                        <th style="text-align:left;padding:10px 12px;">Property</th>
                        <th style="text-align:left;padding:10px 12px;">Outcome</th>
                        <th style="text-align:left;padding:10px 12px;">Reason / Competitor</th>
                        <th style="text-align:left;padding:10px 12px;">Recorded by</th>
                        <th style="text-align:left;padding:10px 12px;">Decision</th>
                        <th style="text-align:left;padding:10px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($outcomes as $o)
                    @php [$ol, $oc, $obg] = $outcomeLabel($o->outcome); @endphp
                    <tr style="border-top:1px solid var(--border);">
                        <td style="padding:10px 12px;color:var(--text-primary);">
                            <a href="{{ route('presentations.show', $o->presentation_id) }}" style="color:var(--text-primary);font-weight:500;text-decoration:none;">
                                {{ $o->presentation?->property_address ?: 'Presentation #' . $o->presentation_id }}
                            </a>
                            @if($o->presentation?->suburb)
                                <div style="font-size:0.6875rem;color:var(--text-muted);">{{ $o->presentation->suburb }}</div>
                            @endif
                        </td>
                        <td style="padding:10px 12px;">
                            <span style="display:inline-block;padding:3px 8px;border-radius:99px;font-size:0.6875rem;font-weight:600;background:{{ $obg }};color:{{ $oc }};">
                                {{ $ol }}
                            </span>
                            @if($o->locked)
                                <span title="Locked for analytics" style="font-size:0.625rem;color:var(--text-muted);">🔒</span>
                            @endif
                        </td>
                        <td style="padding:10px 12px;color:var(--text-secondary);font-size:0.75rem;">
                            @if($o->cancellation_reason)
                                {{ $reasonLabels[$o->cancellation_reason] ?? $o->cancellation_reason }}
                            @endif
                            @if($o->cancellation_competitor_agency)
                                <div style="font-size:0.6875rem;color:var(--text-muted);">{{ $o->cancellation_competitor_agency }}@if($o->cancellation_competitor_price) @ R {{ number_format((int) $o->cancellation_competitor_price) }}@endif</div>
                            @endif
                        </td>
                        <td style="padding:10px 12px;color:var(--text-secondary);font-size:0.75rem;">
                            {{ $o->recorder?->name }}
                            <div style="font-size:0.6875rem;color:var(--text-muted);">{{ $o->recorded_at?->diffForHumans() }}</div>
                        </td>
                        <td style="padding:10px 12px;color:var(--text-secondary);font-size:0.75rem;">
                            {{ $o->decision_at?->format('j M Y') ?: '—' }}
                        </td>
                        <td style="padding:10px 12px;text-align:right;">
                            <a href="{{ route('presentations.show', $o->presentation_id) }}" style="font-size:0.6875rem;color:var(--brand-button);text-decoration:none;">Open →</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:12px 4px;">{{ $outcomes->links() }}</div>
    @endif

</div>
@endsection
