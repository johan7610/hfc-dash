<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $document->title }} — {{ $agency->name }}</title>
    <style>
        :root {
            --brand: {{ $agency->default_color ?? '#0b2a4a' }};
            --text: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --surface: #ffffff;
            --surface-2: #f9fafb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--text); background: var(--surface-2);
            line-height: 1.6;
        }
        header.brand-bar {
            background: var(--brand); color: #fff;
            padding: 18px 24px;
            display: flex; align-items: center; gap: 16px;
        }
        header.brand-bar img { height: 44px; width: auto; background: #fff; padding: 4px; border-radius: 4px; }
        header.brand-bar .agency-name { font-size: 1.05rem; font-weight: 600; }
        main.legal-content {
            max-width: 760px; margin: 32px auto; padding: 32px;
            background: var(--surface); border: 1px solid var(--border); border-radius: 6px;
        }
        main.legal-content h1 {
            font-size: 1.5rem; margin: 0 0 8px; color: var(--text);
            border-bottom: 2px solid var(--brand); padding-bottom: 8px;
        }
        main.legal-content h2 { font-size: 1.15rem; margin: 24px 0 8px; color: var(--text); }
        main.legal-content h3 { font-size: 1.0rem; margin: 18px 0 6px; color: var(--text); }
        main.legal-content p  { margin: 0 0 12px; color: var(--text); }
        main.legal-content ul, main.legal-content ol { margin: 0 0 12px; padding-left: 24px; }
        main.legal-content a { color: var(--brand); }
        main.legal-content code { background: var(--surface-2); padding: 2px 6px; border-radius: 3px; font-size: 0.875rem; }
        footer.doc-footer {
            max-width: 760px; margin: 0 auto 32px; padding: 0 32px;
            font-size: 0.75rem; color: var(--text-muted); text-align: center;
        }
    </style>
</head>
<body>
    <header class="brand-bar">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $agency->name }}">
        @endif
        <span class="agency-name">{{ $agency->name }}</span>
    </header>

    <main class="legal-content">
        <h1>{{ $document->title }}</h1>
        {{-- The rendered HTML is produced server-side from markdown (or stored
             as HTML); content is operator-authored and trusted at write time. --}}
        {!! $renderedHtml !!}
    </main>

    <footer class="doc-footer">
        Last updated {{ ($document->published_at ?? $document->updated_at)->format('j F Y') }}
        @if($agency->email)
            · For queries contact <a href="mailto:{{ $agency->email }}" style="color: var(--text-muted);">{{ $agency->email }}</a>
        @endif
    </footer>
</body>
</html>
