{{-- MIC Phase G2 — BM Team dashboard. Spec §10.2. --}}
@extends('layouts.corex-app')

@section('corex-content')
<div style="max-width: 1400px; margin: 0 auto; padding: 0 20px;">

    @include('corex.market-intelligence.partials.tabs')

    <div style="margin-bottom: 16px;">
        <h1 style="font-size: 1.125rem; font-weight: 600; color: var(--text-primary); margin: 0 0 4px 0;">Team</h1>
        <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0;">
            Per-agent claim + outreach health. Sorted worst performers first — high stale count, low feedback rate.
        </p>
    </div>

    @if($rows->isEmpty())
        <div style="padding: 24px; text-align: center; background: var(--surface); border: 1px dashed var(--border); border-radius: 6px;">
            <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0;">No agents in this agency yet.</p>
        </div>
    @else
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 6px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                <thead>
                    <tr style="background: var(--surface-2); color: var(--text-muted); font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.04em;">
                        <th style="text-align: left; padding: 8px 12px;">Agent</th>
                        <th style="text-align: right; padding: 8px 12px;">Active claims</th>
                        <th style="text-align: right; padding: 8px 12px;">Feedback %</th>
                        <th style="text-align: right; padding: 8px 12px;">Expiring 24h</th>
                        <th style="text-align: right; padding: 8px 12px;">Stale flagged</th>
                        <th style="text-align: right; padding: 8px 12px;">Pitches 30d</th>
                        <th style="text-align: right; padding: 8px 12px;">Presentations 30d</th>
                        <th style="padding: 8px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php
                            $feedbackColor = match (true) {
                                $row['feedback_rate'] === null => 'var(--text-muted)',
                                $row['feedback_rate'] >= 80    => 'var(--ds-green, #10b981)',
                                $row['feedback_rate'] >= 50    => 'var(--ds-amber, #d97706)',
                                default                        => 'var(--ds-crimson, #dc2626)',
                            };
                            $staleColor = $row['stale_flagged'] > 0 ? 'var(--ds-crimson, #dc2626)' : 'var(--text-muted)';
                        @endphp
                        <tr style="border-top: 1px solid var(--border);">
                            <td style="padding: 8px 12px; color: var(--text-primary); font-weight: 500;">
                                {{ $row['agent']->name }}
                                <span style="font-size: 0.625rem; color: var(--text-muted); margin-left: 6px;">{{ $row['agent']->role }}</span>
                            </td>
                            <td style="padding: 8px 12px; text-align: right; color: var(--text-primary);">{{ number_format($row['active_claims']) }}</td>
                            <td style="padding: 8px 12px; text-align: right; color: {{ $feedbackColor }}; font-weight: 600;">
                                {{ $row['feedback_rate'] === null ? '—' : $row['feedback_rate'] . '%' }}
                            </td>
                            <td style="padding: 8px 12px; text-align: right; color: {{ $row['expiring_24h'] > 0 ? 'var(--ds-amber, #d97706)' : 'var(--text-muted)' }};">
                                {{ $row['expiring_24h'] > 0 ? $row['expiring_24h'] : '—' }}
                            </td>
                            <td style="padding: 8px 12px; text-align: right; color: {{ $staleColor }}; font-weight: 600;">
                                {{ $row['stale_flagged'] > 0 ? $row['stale_flagged'] : '—' }}
                            </td>
                            <td style="padding: 8px 12px; text-align: right; color: var(--text-secondary);">{{ number_format($row['pitches_30d']) }}</td>
                            <td style="padding: 8px 12px; text-align: right; color: var(--text-secondary);">{{ number_format($row['presentations_30d']) }}</td>
                            <td style="padding: 8px 12px; text-align: right;">
                                @if($row['stale_flagged'] >= 3)
                                    <button type="button"
                                            onclick="alert('Coaching feature lands in Phase K — for now, take {{ $row['agent']->name }} for a one-on-one this week.')"
                                            style="padding: 4px 10px; font-size: 0.6875rem; font-weight: 500;
                                                   background: var(--surface); color: var(--ds-amber, #d97706);
                                                   border: 1px solid var(--ds-amber, #d97706); border-radius: 3px; cursor: pointer;">
                                        Coaching nudge
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p style="font-size: 0.6875rem; color: var(--text-muted); margin: 14px 0;">
        Stats reflect the last 30 days. Stale flag set by the hourly FlagStaleClaimsJob once a claim sits 48h+ without feedback.
    </p>

</div>
@endsection
