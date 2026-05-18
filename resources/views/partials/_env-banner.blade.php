{{--
    DESIGN SYSTEM COMPLIANCE: UI_DESIGN_SYSTEM.md v 2026-04-20

    Environment banner. Source of truth = config('app.env_label')
    (env APP_ENV_LABEL) — NOT APP_ENV (demo, staging AND live all run
    APP_ENV=production). Empty/unset => renders absolutely NOTHING (no
    element, no flex item, no layout shift). This is exactly how LIVE
    behaves — clean for clients.

    Colour rules (F.7): only existing, non-agency-branded design tokens
    via the var(--token, #fallback) pattern.
      - STAGING : --ds-amber  bg  +  --ds-navy text  (dark-on-amber,
                  theme-independent — both tokens are single-value)
      - DEMO    : --ds-navy   bg  +  white text       (the DS "Info"
                  token; theme-independent; high contrast both modes)
      - LOCAL   : --surface-2 bg  +  --text-primary    (neutral, the
                  theme-PAIRED tokens flip together so it stays readable
                  in light AND dark — avoids the white-on-white trap)
--}}
@php
    $envLabel = strtoupper(trim((string) config('app.env_label', '')));
@endphp
@if ($envLabel !== '')
    @php
        $host = request()->getHost();
        $themes = [
            'DEMO' => [
                'bg'   => 'var(--ds-navy, #0b2a4a)',
                'fg'   => '#ffffff',
                'text' => 'DEMO ENVIRONMENT · ' . $host . ' · data may reset',
            ],
            'STAGING' => [
                'bg'   => 'var(--ds-amber, #f59e0b)',
                'fg'   => 'var(--ds-navy, #0b2a4a)',
                'text' => 'STAGING · ' . $host . ' · testing only — not live',
            ],
            'LOCAL' => [
                'bg'   => 'var(--surface-2, #f0f2f8)',
                'fg'   => 'var(--text-primary, #111827)',
                'text' => 'LOCAL DEV · ' . $host,
            ],
        ];
        $c = $themes[$envLabel] ?? [
            'bg'   => 'var(--ds-navy, #0b2a4a)',
            'fg'   => '#ffffff',
            'text' => $envLabel . ' · ' . $host,
        ];
    @endphp
    <div role="status" aria-label="Environment: {{ $envLabel }}"
         style="flex:0 0 auto; width:100%; height:24px; line-height:24px;
                background:{{ $c['bg'] }}; color:{{ $c['fg'] }};
                border-bottom:1px solid var(--border, rgba(0,0,0,0.14));
                font-size:11px; font-weight:700; letter-spacing:.09em;
                text-transform:uppercase; text-align:center;
                white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                padding:0 12px; user-select:none;">
        {{ $c['text'] }}
    </div>
@endif
