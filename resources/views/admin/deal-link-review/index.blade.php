@extends('layouts.corex-app')

@section('corex-content')
@php
    use App\Models\DealLinkReviewQueue;
    $tabs = [
        DealLinkReviewQueue::STATUS_PENDING           => 'Pending ('           . ($counts['pending']           ?? 0) . ')',
        DealLinkReviewQueue::STATUS_RESOLVED_LINKED   => 'Resolved — linked (' . ($counts['resolved_linked']   ?? 0) . ')',
        DealLinkReviewQueue::STATUS_RESOLVED_UNLINKED => 'Resolved — no match (' . ($counts['resolved_unlinked'] ?? 0) . ')',
        DealLinkReviewQueue::STATUS_RESOLVED_SKIP     => 'Deferred ('          . ($counts['resolved_skip']     ?? 0) . ')',
    ];
@endphp
<div style="max-width:1200px;margin:0 auto;padding:0 20px;">

    <div style="margin-bottom:14px;">
        <h1 style="font-size:1.25rem;font-weight:600;color:var(--text-primary);margin:0;">Deal → Property Link Review</h1>
        <p style="font-size:0.8125rem;color:var(--text-muted);margin:4px 0 0 0;">
            Phase 3i — deals where the auto-matcher found ambiguous or low-confidence property candidates. Review and resolve so HFC sales history surfaces correctly across the system.
        </p>
    </div>

    @if(session('status'))
        <div style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:6px;padding:10px 14px;font-size:0.8125rem;margin-bottom:14px;">
            {{ session('status') }}
        </div>
    @endif

    <div style="display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:14px;overflow-x:auto;">
        @foreach($tabs as $key => $label)
            @php $active = $status === $key; @endphp
            <a href="{{ route('corex.admin.deal-link-review.index', ['status' => $key]) }}"
               style="padding:8px 14px;font-size:0.8125rem;text-decoration:none;white-space:nowrap;
                      color:{{ $active ? 'var(--text-primary)' : 'var(--text-muted)' }};
                      border-bottom:2px solid {{ $active ? 'var(--brand-button)' : 'transparent' }};
                      font-weight:{{ $active ? '600' : '500' }};">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($rows->isEmpty())
        <div style="padding:24px;text-align:center;background:var(--surface);border:1px dashed var(--border);border-radius:6px;color:var(--text-muted);font-size:0.875rem;">
            No items in this tab.
        </div>
    @else
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
                <thead>
                    <tr style="background:var(--surface-2);color:var(--text-muted);font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.04em;">
                        <th style="text-align:left;padding:10px 12px;">Deal address</th>
                        <th style="text-align:left;padding:10px 12px;">Deal date</th>
                        <th style="text-align:right;padding:10px 12px;">Value</th>
                        <th style="text-align:center;padding:10px 12px;">Candidates</th>
                        <th style="text-align:left;padding:10px 12px;">Top candidate</th>
                        <th style="text-align:left;padding:10px 12px;">Waiting</th>
                        <th style="text-align:right;padding:10px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $r)
                    @php
                        $candidates = collect($r->candidates_json ?? []);
                        $top = $candidates->first();
                    @endphp
                    <tr style="border-top:1px solid var(--border);">
                        <td style="padding:10px 12px;color:var(--text-primary);">
                            {{ $r->deal?->property_address }}
                        </td>
                        <td style="padding:10px 12px;color:var(--text-secondary);font-size:0.75rem;">
                            {{ $r->deal?->registration_date?->format('j M Y') ?: '—' }}
                        </td>
                        <td style="padding:10px 12px;color:var(--text-secondary);font-size:0.75rem;text-align:right;">
                            @if($r->deal?->sale_price)
                                R {{ number_format((int) $r->deal->sale_price) }}
                            @elseif($r->deal?->property_value)
                                R {{ number_format((float) $r->deal->property_value, 0) }}
                            @else
                                —
                            @endif
                        </td>
                        <td style="padding:10px 12px;color:var(--text-secondary);font-size:0.75rem;text-align:center;">
                            {{ $candidates->count() }}
                        </td>
                        <td style="padding:10px 12px;color:var(--text-secondary);font-size:0.75rem;">
                            @if($top)
                                {{ \Illuminate\Support\Str::limit($top['address'] ?? '', 50) }}
                                <span style="font-size:0.6875rem;color:var(--text-muted);">
                                    (score {{ $top['score'] ?? 0 }}, {{ $top['confidence'] ?? '' }})
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td style="padding:10px 12px;color:var(--text-muted);font-size:0.75rem;">
                            {{ $r->matched_at?->diffForHumans() }}
                        </td>
                        <td style="padding:10px 12px;text-align:right;">
                            <a href="{{ route('corex.admin.deal-link-review.show', $r->id) }}"
                               class="corex-btn-primary" style="font-size:0.6875rem;padding:5px 12px;text-decoration:none;">
                                Review →
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:12px 4px;">{{ $rows->links() }}</div>
    @endif

</div>
@endsection
