{{--
    Signature Block — reusable across all web document templates

    Usage: @include('docuperfect.web-templates.components.signature-block', [
        'parties' => ['Owner', 'Owner', 'Agent'],
        'signed_at_location' => $signed_at_location ?? null,
        'signed_day' => $signed_day ?? null,
        'signed_month' => $signed_month ?? null,
        'signed_time' => $signed_time ?? null,
        'signed_ampm' => $signed_ampm ?? null,
    ])
--}}
<div class="signature-section">
    <p>This Agreement has been accepted and signed by the Owner/s at
        <span class="field">{{ $signed_at_location ?? '' }}</span>
    </p>
    <p>on this <span class="field field-short">{{ $signed_day ?? '' }}</span> day of
        <span class="field">{{ $signed_month ?? '' }}</span>
        at <span class="field field-short">{{ $signed_time ?? '' }}</span>
        (<span class="field field-tiny">{{ $signed_ampm ?? '' }}</span>)
    </p>

    <div class="signature-grid" style="grid-template-columns: repeat({{ count($parties ?? ['Owner','Owner','Agent']) }}, 1fr);">
        @foreach(($parties ?? ['Owner', 'Owner', 'Agent']) as $party)
            <div class="signature-col">
                <div class="signature-line"></div>
                <div class="signature-label">{{ $party }}</div>
                <div class="print-line"></div>
                <div class="print-label">Print Name</div>
            </div>
        @endforeach
    </div>
</div>
