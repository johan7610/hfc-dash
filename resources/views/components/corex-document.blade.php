@props([
    'title' => 'Document',
    'subtitle' => null,
    'reference' => null,
    'date' => null,
    'parties' => [],
    'showHeader' => true,
    'showFooter' => true,
    'pageNumber' => null,
    'totalPages' => null,
])

@php
    // Resolve agency/branch from current user
    $headerAgency = null;
    $headerBranch = null;

    if (Auth::check()) {
        $branchId = Auth::user()->effectiveBranchId();
        if ($branchId) {
            $headerBranch = \App\Models\Branch::with('agency')->find($branchId);
            $headerAgency = $headerBranch?->agency;
        }
    }
    if (!$headerAgency) {
        $headerAgency = \App\Models\Agency::where('slug', 'hfc-coastal')->first();
    }

    // Branch-or-agency field resolver (same pattern as company-header)
    $d = function(string $field) use ($headerBranch, $headerAgency): string {
        if ($headerBranch && !empty($headerBranch->{$field})) {
            return $headerBranch->{$field};
        }
        return $headerAgency?->{$field} ?? '';
    };

    $accentColour = $d('sidebar_color') ?: '#0d9488';

    // Logo resolution
    $logoPath = null;
    if ($headerBranch && $headerBranch->logo_path) {
        $logoPath = asset('storage/' . $headerBranch->logo_path);
    } elseif ($headerAgency && $headerAgency->logo_path) {
        $logoPath = asset('storage/' . $headerAgency->logo_path);
    }
@endphp

<div class="corex-document-wrapper" style="--agency-accent: {{ $accentColour }}">
    <div class="corex-page">

        @if($showHeader)
        <div class="corex-header">
            <div class="corex-header-left">
                @if($logoPath)
                    <img src="{{ $logoPath }}"
                         class="corex-header-logo"
                         alt="{{ $d('name') }}">
                @else
                    <div style="width:48px;height:48px;background:#0f172a;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:{{ $accentColour }};letter-spacing:1px;">
                        {{ strtoupper(substr($d('name') ?: 'CX', 0, 3)) }}
                    </div>
                @endif
                <div>
                    <div class="corex-header-agency">
                        {{ $d('trading_name') ?: $d('name') }}
                    </div>
                    <div class="corex-header-details">
                        @if($d('ffc_no'))
                            PPRA: {{ $d('ffc_no') }}
                        @endif
                        @if($d('fic_no'))
                            &nbsp;|&nbsp; FIC: {{ $d('fic_no') }}
                        @endif
                    </div>
                </div>
            </div>
            @if($reference)
            <div class="corex-header-right">
                <div class="corex-header-ref-label">Reference</div>
                <div class="corex-header-ref">{{ $reference }}</div>
            </div>
            @endif
        </div>
        @endif

        <div class="corex-title-banner">
            <h1 class="corex-doc-title">{{ $title }}</h1>
            @if($subtitle)
                <div class="corex-doc-subtitle">{{ $subtitle }}</div>
            @endif
            @if($date)
                <div class="corex-doc-subtitle">Date: {{ $date }}</div>
            @endif
        </div>

        <div class="corex-body">
            {{ $slot }}
        </div>

        @if($showFooter)
        <div class="corex-footer">
            <div>{{ $d('trading_name') ?: $d('name') }}
                @if($d('phone'))
                    &nbsp;|&nbsp; {{ $d('phone') }}
                @endif
            </div>
            <div>
                @if($pageNumber && $totalPages)
                    Page {{ $pageNumber }} of {{ $totalPages }}
                @endif
            </div>
            @if(count($parties) > 0)
            <div class="corex-initials-row">
                @foreach($parties as $party)
                <div class="corex-initial-block">
                    {{ collect(explode(' ', $party['name'] ?? ''))->map(fn($w) => strtoupper(substr($w, 0, 1)))->join('') }}
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

    </div>
</div>
