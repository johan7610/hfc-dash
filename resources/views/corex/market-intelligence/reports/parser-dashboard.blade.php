{{-- MIC Phase F — Parser accuracy dashboard. Permission: mic.view_ai_costs. --}}
@extends('layouts.corex-app')

@section('corex-content')
<div style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
    @include('corex.market-intelligence.partials.tabs')

    <nav style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 12px;">
        <a href="{{ route('market-intelligence.reports.index') }}" style="color: var(--brand-button); text-decoration: none;">← Reports</a>
    </nav>

    <h1 style="font-size: 1.0625rem; font-weight: 600; color: var(--text-primary); margin: 0 0 4px 0;">Parser accuracy</h1>
    <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0 0 16px 0;">
        Per-parser stats: how many reports each parser has handled, how often the spot-check audit agrees, and the current trust status.
    </p>

    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 6px; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
            <thead>
                <tr style="background: var(--surface-2); color: var(--text-muted); font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.04em;">
                    <th style="text-align: left; padding: 8px 12px;">Type</th>
                    <th style="text-align: left; padding: 8px 12px;">Parser</th>
                    <th style="text-align: right; padding: 8px 12px;">Parsed</th>
                    <th style="text-align: right; padding: 8px 12px;">Avg points</th>
                    <th style="text-align: right; padding: 8px 12px;">Passed</th>
                    <th style="text-align: right; padding: 8px 12px;">Flagged</th>
                    <th style="text-align: right; padding: 8px 12px;">Pass rate</th>
                    <th style="text-align: left; padding: 8px 12px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stats as $row)
                    @php
                        $statusColor = match ($row['status']) {
                            'Trusted'  => '#10b981',
                            'Active'   => '#0ea5e9',
                            'Review'   => '#dc2626',
                            default    => 'var(--text-muted)',
                        };
                    @endphp
                    <tr style="border-top: 1px solid var(--border);">
                        <td style="padding: 8px 12px; color: var(--text-primary);">{{ $row['type']->display_name }}</td>
                        <td style="padding: 8px 12px; color: var(--text-secondary); font-family: ui-monospace, monospace; font-size: 0.6875rem;" title="{{ $row['type']->parser_class }}">{{ class_basename($row['type']->parser_class) }}</td>
                        <td style="padding: 8px 12px; text-align: right;">{{ number_format($row['parsed_count']) }}</td>
                        <td style="padding: 8px 12px; text-align: right;">{{ $row['avg_points'] > 0 ? $row['avg_points'] : '—' }}</td>
                        <td style="padding: 8px 12px; text-align: right; color: var(--ds-green, #10b981);">{{ number_format($row['passed']) }}</td>
                        <td style="padding: 8px 12px; text-align: right; color: var(--ds-amber, #d97706);">{{ number_format($row['flagged']) }}</td>
                        <td style="padding: 8px 12px; text-align: right; font-weight: 600;">{{ $row['pass_rate'] === null ? '—' : $row['pass_rate'] . '%' }}</td>
                        <td style="padding: 8px 12px; color: {{ $statusColor }}; font-weight: 600;">{{ $row['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p style="font-size: 0.6875rem; color: var(--text-muted); margin: 12px 0;">
        Status tiers: <strong>Trusted</strong> = ≥98% pass rate over 500+ audits ·
        <strong>Active</strong> = ≥90% pass rate ·
        <strong>Review</strong> = &lt;90% pass rate ·
        <strong>Untested</strong> = no audits yet.
    </p>
</div>
@endsection
