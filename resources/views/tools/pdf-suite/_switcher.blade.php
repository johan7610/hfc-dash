@php
    $pills = [
        ['route' => 'tools.pdf_suite.hub',          'label' => 'Hub'],
        ['route' => 'tools.pdf_splitter.index',     'label' => 'Split'],
        ['route' => 'tools.pdf_suite.compress',     'label' => 'Compress'],
        ['route' => 'tools.pdf_suite.merge',        'label' => 'Merge'],
        ['route' => 'tools.pdf_suite.image-to-pdf', 'label' => 'Image → PDF'],
        ['route' => 'tools.pdf_suite.rotate',       'label' => 'Rotate'],
        ['route' => 'tools.pdf_suite.reorder',      'label' => 'Reorder'],
        ['route' => 'tools.pdf_suite.protect',      'label' => 'Protect'],
        ['route' => 'tools.pdf_suite.redact',       'label' => 'Redact'],
    ];
@endphp
<div style="background: var(--surface); border-bottom: 1px solid var(--border);">
    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-3 flex flex-wrap gap-2 overflow-x-auto">
        @foreach($pills as $p)
            @if(\Illuminate\Support\Facades\Route::has($p['route']))
                @php
                    $active = $p['route'] === 'tools.pdf_suite.hub'
                        ? request()->routeIs('tools.pdf_suite.hub')
                        : request()->routeIs($p['route']);
                @endphp
                <a href="{{ route($p['route']) }}"
                   class="text-xs font-semibold px-3.5 py-2 rounded-md transition-all duration-300 whitespace-nowrap"
                   style="@if($active) background: var(--brand-button, #0ea5e9); color: white; box-shadow: 0 2px 8px -2px color-mix(in srgb, var(--brand-button, #0ea5e9) 50%, transparent); @else background: transparent; color: var(--text-secondary); @endif">
                    {{ $p['label'] }}
                </a>
            @endif
        @endforeach
    </div>
</div>
