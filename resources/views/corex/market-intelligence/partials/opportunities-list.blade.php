{{--
    MIC Phase D4 — Opportunities tab paginated row list (spec §5.4.1).
    Address-aware: missing-address TPs surface a click-to-add affordance.
    Sort defaults to strong_match_count DESC so high-signal TPs land at top.
--}}
@if($tps->isEmpty())
    <div style="padding: 24px; text-align: center;
                background: var(--surface); border: 1px solid var(--border); border-radius: 6px;">
        <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0;">
            No tracked properties match these filters. Try clearing some, or "All" to see everything.
        </p>
    </div>
@else
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 6px; overflow: hidden;">
        @foreach($tps as $tp)
            @php
                $detailUrl = route('market-intelligence.opportunities.show', $tp);
                $primary = $tp->primaryAddress;
                $hasStreet = $primary && !empty($primary->street_name);
                $isPromoted = ($tp->status === \App\Models\Prospecting\TrackedProperty::STATUS_PROMOTED)
                              || $tp->promoted_to_property_id !== null;
                $sourceSet = $tp->externalRefs->pluck('source_type')->unique()->values();
                $strong = (int) ($tp->strong_match_count ?? 0);
            @endphp
            <a href="{{ $detailUrl }}"
               style="display: block; text-decoration: none; color: inherit;
                      border-bottom: 1px solid var(--border);
                      transition: background 100ms ease;"
               onmouseover="this.style.background='var(--surface-2)'"
               onmouseout="this.style.background='var(--surface)'">
                <div style="padding: 12px 16px; display: flex; align-items: flex-start; gap: 16px;">
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap;">
                            <span style="font-weight: 500; color: var(--text-primary); font-size: 0.875rem;">
                                @if($hasStreet)
                                    {{ $primary->formatted_address }}
                                @else
                                    <span style="color: var(--text-muted); font-style: italic;">Address pending</span>
                                    <span style="font-size: 0.6875rem; color: var(--brand-button); margin-left: 4px;">click to add →</span>
                                @endif
                            </span>
                            @if($isPromoted)
                                <span style="font-size: 0.625rem; font-weight: 600; padding: 2px 6px;
                                             background: color-mix(in srgb, var(--ds-amber, #f59e0b) 18%, transparent);
                                             color: var(--ds-amber, #d97706); border-radius: 3px;">
                                    IN STOCK
                                </span>
                            @endif
                            @if($primary)
                                @php
                                    $confColor = match ($primary->confidence) {
                                        'verified' => '#0d9488',
                                        'high'     => '#16a34a',
                                        'medium'   => '#d97706',
                                        default    => 'var(--text-muted)',
                                    };
                                @endphp
                                <span style="font-size: 0.625rem; font-weight: 600; padding: 2px 6px;
                                             background: color-mix(in srgb, {{ $confColor }} 14%, transparent);
                                             color: {{ $confColor }}; border-radius: 3px;">
                                    {{ strtoupper($primary->confidence) }}
                                </span>
                            @endif
                        </div>
                        <div style="font-size: 0.8125rem; color: var(--text-secondary);">
                            {{ $tp->suburb ?? '—' }}
                            @if($tp->property_type) · {{ $tp->property_type }} @endif
                            @if($tp->bedrooms) · {{ $tp->bedrooms }}-bed @endif
                            @if($tp->erf_number) · erf {{ $tp->erf_number }} @endif
                        </div>
                        <div style="font-size: 0.6875rem; color: var(--text-muted); margin-top: 2px;">
                            @foreach($sourceSet as $src)
                                <span style="display: inline-block; margin-right: 8px;">{{ strtoupper(str_replace('_', ' ', $src)) }}</span>
                            @endforeach
                            @if($tp->last_enriched_at) · {{ $tp->last_enriched_at->diffForHumans() }} @endif
                        </div>
                    </div>
                    <div style="text-align: right; font-size: 0.8125rem; white-space: nowrap;">
                        @if($strong > 0)
                            <div style="font-weight: 600; color: var(--ds-green, #10b981);">
                                {{ $strong }} strong match{{ $strong === 1 ? '' : 'es' }}
                            </div>
                        @endif
                        @if(($tp->listing_count ?? 0) > 0)
                            <div style="font-size: 0.6875rem; color: var(--text-muted); margin-top: 2px;">
                                {{ $tp->listing_count }} portal listing{{ $tp->listing_count === 1 ? '' : 's' }}
                            </div>
                        @endif
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    <div style="padding: 12px 4px;">
        {{ $tps->links() }}
    </div>
@endif
