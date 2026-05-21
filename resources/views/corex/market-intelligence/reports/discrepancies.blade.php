{{-- MIC Phase F — discrepancies list. --}}
@extends('layouts.corex-app')

@section('corex-content')
<div style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
    @include('corex.market-intelligence.partials.tabs')

    <nav style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 12px;">
        <a href="{{ route('market-intelligence.reports.show', $report) }}" style="color: var(--brand-button); text-decoration: none;">← Back to report</a>
    </nav>

    <h1 style="font-size: 1.0625rem; font-weight: 600; color: var(--text-primary); margin: 0 0 4px 0;">
        Discrepancies — {{ $report->file_name }}
    </h1>
    <p style="font-size: 0.8125rem; color: var(--text-muted); margin: 0 0 16px 0;">
        {{ $discrepancies->total() }} {{ $discrepancies->total() === 1 ? 'point' : 'points' }} flagged by spot-check audit. Each row shows the parser's value vs. what the audit found.
    </p>

    @if($discrepancies->isEmpty())
        <div style="padding: 24px; text-align: center; background: var(--surface); border: 1px dashed var(--border); border-radius: 6px;">
            <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0;">No discrepancies — spot check passed.</p>
        </div>
    @else
        <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 6px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                <thead>
                    <tr style="background: var(--surface-2); color: var(--text-muted); font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.04em;">
                        <th style="text-align: left; padding: 8px 12px;">Metric</th>
                        <th style="text-align: left; padding: 8px 12px;">Parsed</th>
                        <th style="text-align: left; padding: 8px 12px;">Audit found</th>
                        <th style="text-align: left; padding: 8px 12px;">Type</th>
                        <th style="text-align: left; padding: 8px 12px;">Severity</th>
                        <th style="text-align: left; padding: 8px 12px;">Resolved</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($discrepancies as $d)
                        @php
                            $severityColor = match ($d->severity) {
                                'high'   => '#dc2626',
                                'medium' => '#d97706',
                                'low'    => 'var(--text-muted)',
                                default  => 'var(--text-muted)',
                            };
                        @endphp
                        <tr style="border-top: 1px solid var(--border);">
                            <td style="padding: 8px 12px; color: var(--text-primary); font-family: ui-monospace, monospace; font-size: 0.75rem;">{{ $d->dataPoint?->metric_key ?? '—' }}</td>
                            <td style="padding: 8px 12px; color: var(--text-secondary);">{{ $d->parsed_value }}</td>
                            <td style="padding: 8px 12px; color: var(--text-secondary);">{{ $d->audit_value }}</td>
                            <td style="padding: 8px 12px; color: var(--text-secondary); font-size: 0.75rem;">{{ str_replace('_', ' ', $d->discrepancy_type) }}</td>
                            <td style="padding: 8px 12px; color: {{ $severityColor }}; font-weight: 600;">{{ ucfirst($d->severity) }}</td>
                            <td style="padding: 8px 12px; color: var(--text-secondary);">{{ $d->resolved ? 'Yes' : 'No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding: 12px 4px;">{{ $discrepancies->links() }}</div>
    @endif
</div>
@endsection
