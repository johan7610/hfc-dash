@extends('layouts.corex-app')

@section('corex-content')
@php
    use App\Models\PresentationRefreshRequest;
    $statusBadge = function (string $s): array {
        return match ($s) {
            PresentationRefreshRequest::STATUS_PENDING      => ['Pending',      '#fef3c7', '#92400e'],
            PresentationRefreshRequest::STATUS_ACKNOWLEDGED => ['Acknowledged', '#dbeafe', '#1e40af'],
            PresentationRefreshRequest::STATUS_RESOLVED     => ['Resolved',     '#d1fae5', '#065f46'],
            PresentationRefreshRequest::STATUS_DECLINED     => ['Declined',     '#fee2e2', '#991b1b'],
            default                                         => ['Cancelled',    '#e5e7eb', '#374151'],
        };
    };
    $tabs = [
        'open'         => 'Open ('         . ($counts['open']         ?? 0) . ')',
        'pending'      => 'Pending ('      . ($counts['pending']      ?? 0) . ')',
        'acknowledged' => 'Acknowledged (' . ($counts['acknowledged'] ?? 0) . ')',
        'resolved'     => 'Resolved ('     . ($counts['resolved']     ?? 0) . ')',
        'declined'     => 'Declined ('     . ($counts['declined']     ?? 0) . ')',
        'all'          => 'All',
    ];
@endphp
<div style="max-width: 1200px; margin: 0 auto; padding: 0 20px;" x-data="{ panel: null }">

    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;">
        <div>
            <h1 style="font-size:1.25rem;font-weight:600;color:var(--text-primary);margin:0;">Refresh Requests</h1>
            <p style="font-size:0.8125rem;color:var(--text-muted);margin:4px 0 0 0;">
                Sellers' asks to refresh share-link data. Acknowledge to mark seen; issue a refreshed link to resolve.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:6px;padding:10px 14px;font-size:0.8125rem;margin-bottom:14px;">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:6px;padding:10px 14px;font-size:0.8125rem;margin-bottom:14px;">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Tabs --}}
    <div style="display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:14px;overflow-x:auto;">
        @foreach($tabs as $key => $label)
            @php $active = $status === $key; @endphp
            <a href="{{ route('corex.presentations.refresh-requests.index', ['status' => $key]) }}"
               style="padding:8px 14px;font-size:0.8125rem;text-decoration:none;white-space:nowrap;
                      color:{{ $active ? 'var(--text-primary)' : 'var(--text-muted)' }};
                      border-bottom:2px solid {{ $active ? 'var(--brand-button)' : 'transparent' }};
                      font-weight:{{ $active ? '600' : '500' }};">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($requests->isEmpty())
        <div style="padding:32px;text-align:center;background:var(--surface);border:1px dashed var(--border);border-radius:6px;color:var(--text-muted);font-size:0.875rem;">
            No refresh requests in this view.
        </div>
    @else
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
                <thead>
                    <tr style="background:var(--surface-2);color:var(--text-muted);font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.04em;">
                        <th style="text-align:left;padding:10px 12px;">Property</th>
                        <th style="text-align:left;padding:10px 12px;">Requester</th>
                        <th style="text-align:left;padding:10px 12px;">Message</th>
                        <th style="text-align:left;padding:10px 12px;">Status</th>
                        <th style="text-align:left;padding:10px 12px;">When</th>
                        <th style="text-align:right;padding:10px 12px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($requests as $r)
                    @php [$bLabel, $bBg, $bFg] = $statusBadge($r->status); @endphp
                    <tr style="border-top:1px solid var(--border);">
                        <td style="padding:10px 12px;color:var(--text-primary);">
                            <a href="{{ route('presentations.show', $r->presentation_id) }}"
                               style="color:var(--text-primary);text-decoration:none;font-weight:500;">
                                {{ $r->presentation?->property_address ?: 'Presentation #' . $r->presentation_id }}
                            </a>
                            @if($r->presentation?->suburb)
                                <div style="font-size:0.6875rem;color:var(--text-muted);">{{ $r->presentation->suburb }}</div>
                            @endif
                        </td>
                        <td style="padding:10px 12px;color:var(--text-primary);">
                            <div style="font-weight:500;">{{ $r->requester_name }}</div>
                            @if($r->requester_email)
                                <div style="font-size:0.6875rem;color:var(--text-muted);">{{ $r->requester_email }}</div>
                            @endif
                            @if($r->requester_phone)
                                <div style="font-size:0.6875rem;color:var(--text-muted);">{{ $r->requester_phone }}</div>
                            @endif
                        </td>
                        <td style="padding:10px 12px;font-size:0.75rem;color:var(--text-secondary);max-width:300px;">
                            @if($r->message)
                                {{ \Illuminate\Support\Str::limit($r->message, 140) }}
                            @else
                                <span style="color:var(--text-muted);font-style:italic;">No message</span>
                            @endif
                        </td>
                        <td style="padding:10px 12px;">
                            <span style="display:inline-block;padding:3px 8px;border-radius:99px;font-size:0.6875rem;font-weight:600;background:{{ $bBg }};color:{{ $bFg }};">
                                {{ $bLabel }}
                            </span>
                        </td>
                        <td style="padding:10px 12px;color:var(--text-muted);font-size:0.75rem;">
                            {{ $r->created_at?->diffForHumans() }}
                        </td>
                        <td style="padding:10px 12px;text-align:right;white-space:nowrap;">
                            @if($r->isOpen())
                                @if($r->status === PresentationRefreshRequest::STATUS_PENDING)
                                    <form method="POST" action="{{ route('corex.presentations.refresh-requests.acknowledge', $r) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit"
                                                style="font-size:0.6875rem;padding:4px 8px;border:1px solid var(--border);background:var(--surface);color:var(--text-secondary);border-radius:4px;cursor:pointer;">
                                            Acknowledge
                                        </button>
                                    </form>
                                @endif
                                <button type="button" @click="panel = panel === {{ $r->id }} + '-resolve' ? null : {{ $r->id }} + '-resolve'"
                                        style="font-size:0.6875rem;padding:4px 10px;border:0;background:var(--brand-button);color:#fff;border-radius:4px;cursor:pointer;font-weight:600;">
                                    Issue refresh
                                </button>
                                <button type="button" @click="panel = panel === {{ $r->id }} + '-decline' ? null : {{ $r->id }} + '-decline'"
                                        style="font-size:0.6875rem;padding:4px 8px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:4px;cursor:pointer;">
                                    Decline
                                </button>
                            @elseif($r->isResolved() && $r->resultingLink)
                                <a href="{{ route('presentation.public.show', $r->resultingLink->token) }}" target="_blank"
                                   style="font-size:0.6875rem;color:var(--brand-button);text-decoration:none;">
                                    View new link ↗
                                </a>
                            @endif
                        </td>
                    </tr>

                    {{-- Inline Resolve panel --}}
                    <tr x-show="panel === '{{ $r->id }}-resolve'" x-cloak>
                        <td colspan="6" style="background:#f8fafc;padding:14px 18px;border-top:1px solid var(--border);">
                            <form method="POST" action="{{ route('corex.presentations.refresh-requests.resolve', $r) }}">
                                @csrf
                                <h3 style="margin:0 0 8px 0;font-size:0.875rem;color:var(--text-primary);">Issue a refreshed share link</h3>
                                <p style="margin:0 0 12px 0;font-size:0.75rem;color:var(--text-muted);">
                                    A new /p/{token} link will be created using the same recipient + mode. The original link will be marked superseded so visitors are redirected to the new one.
                                </p>
                                <label style="display:flex;align-items:center;gap:6px;font-size:0.8125rem;color:var(--text-secondary);margin-bottom:8px;">
                                    <input type="checkbox" name="keep_old_link_active" value="1">
                                    Keep the old link active (don't supersede)
                                </label>
                                <label style="display:block;font-size:0.6875rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px;">Resolution note (optional)</label>
                                <textarea name="resolution_note" rows="2" maxlength="2000"
                                          placeholder="e.g. Refreshed comps for Q2 — fixed asking range up 5%."
                                          style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:4px;font-size:0.8125rem;font-family:inherit;"></textarea>
                                <div style="margin-top:10px;display:flex;gap:8px;">
                                    <button type="submit"
                                            style="font-size:0.8125rem;padding:7px 14px;background:var(--brand-button);color:#fff;border:0;border-radius:4px;font-weight:600;cursor:pointer;">
                                        Issue refresh &amp; resolve
                                    </button>
                                    <button type="button" @click="panel = null"
                                            style="font-size:0.8125rem;padding:7px 14px;background:transparent;color:var(--text-muted);border:1px solid var(--border);border-radius:4px;cursor:pointer;">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>

                    {{-- Inline Decline panel --}}
                    <tr x-show="panel === '{{ $r->id }}-decline'" x-cloak>
                        <td colspan="6" style="background:#fef2f2;padding:14px 18px;border-top:1px solid var(--border);">
                            <form method="POST" action="{{ route('corex.presentations.refresh-requests.decline', $r) }}">
                                @csrf
                                <h3 style="margin:0 0 8px 0;font-size:0.875rem;color:#991b1b;">Decline this request</h3>
                                <label style="display:block;font-size:0.6875rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px;">Reason (shown to the requester) *</label>
                                <textarea name="decline_reason" rows="2" maxlength="2000" required minlength="5"
                                          placeholder="e.g. The current report is still accurate — comps haven't moved enough to warrant a refresh."
                                          style="width:100%;padding:8px 10px;border:1px solid #fecaca;border-radius:4px;font-size:0.8125rem;font-family:inherit;background:#fff;"></textarea>
                                <label style="display:flex;align-items:center;gap:6px;font-size:0.8125rem;color:var(--text-secondary);margin-top:8px;">
                                    <input type="checkbox" name="notify_requester" value="1" checked>
                                    Email the requester with this reason
                                </label>
                                <div style="margin-top:10px;display:flex;gap:8px;">
                                    <button type="submit"
                                            style="font-size:0.8125rem;padding:7px 14px;background:#991b1b;color:#fff;border:0;border-radius:4px;font-weight:600;cursor:pointer;">
                                        Decline request
                                    </button>
                                    <button type="button" @click="panel = null"
                                            style="font-size:0.8125rem;padding:7px 14px;background:transparent;color:var(--text-muted);border:1px solid var(--border);border-radius:4px;cursor:pointer;">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:12px 4px;">{{ $requests->links() }}</div>
    @endif

</div>
@endsection
