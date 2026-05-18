{{--
    DESIGN SYSTEM COMPLIANCE: print/legal A4 document template (web-template
    convention, see marketing-permission-v6.blade.php), NOT app UI tokens.

    Sale-context Mandatory Disclosure — Property Practitioners Act 22 of 2019
    s70 + Regulations 2022 s36. Legal text reproduced VERBATIM from
    resources/views/docuperfect/web-templates/cds/template-117.blade.php
    (STANDARDS: Document Fidelity is Non-Negotiable).

    Why a new web blade rather than blade_view=template-117: template-117's
    YES/NO/N/A cells are empty <td></td> with no data-field, so the e-sign
    wizard cannot fill them (CdsRenderer's corex-radio-placeholder path is
    only used for cds_json-rendered templates, not direct blade_view ones).
    Each defect row here is a data-field + fields_json type:'selection'
    (Yes/No/N/A) — the proven interactive path (same as Marketing
    Permission V6). Letterhead via the shared, data-driven company-header
    component (so the corrected HFC agency data flows through).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Immovable Property Condition Report — Sale (Mandatory Disclosure)</title>
    <style>
        @page { size: A4; margin: 16mm 18mm 14mm 18mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 10pt; color: #1a1a1a; line-height: 1.45; }
        .doc { width: 210mm; max-width: 100%; margin: 0 auto; padding: 0 2mm; }
        .doc-title { text-align: center; margin: 10pt 0 8pt; }
        .doc-title h2 { font-size: 12pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #0b2a4a; }
        .clause-head { font-weight: 700; margin: 10pt 0 3pt; }
        p.clause { margin: 5pt 0; text-align: justify; }
        .num { font-weight: 700; margin-right: 4pt; }
        .field { display: inline-block; min-width: 60pt; border-bottom: 0.7pt dotted #555; line-height: 1.4; padding: 0 2pt; }
        .field-wide { min-width: 240pt; }
        table.defects { width: 100%; border-collapse: collapse; font-size: 9pt; margin: 8pt 0; }
        table.defects th, table.defects td { border: 0.6pt solid #b9c2cc; padding: 4pt 6pt; vertical-align: top; }
        table.defects th { background: #0b2a4a; color: #fff; text-transform: uppercase; font-size: 8pt; letter-spacing: 0.5pt; }
        table.defects td.resp { width: 90pt; text-align: center; }
        .addl { border: 0.6pt solid #b9c2cc; padding: 6pt; min-height: 48pt; margin: 6pt 0; }
        .signature-section { margin-top: 16pt; }
        .signature-grid { display: grid; gap: 18pt; margin-top: 16pt; }
        .signature-col { text-align: center; }
        .signature-line { border-bottom: 1pt solid #1a1a1a; height: 30pt; margin-bottom: 3pt; }
        .signature-label { font-size: 9pt; font-weight: 600; text-transform: uppercase; }
        .doc-footer { margin-top: 14pt; text-align: right; font-size: 8pt; color: #999; }
    </style>
</head>
<body>
<div class="doc corex-document-wrapper">

    @include('docuperfect.web-templates.components.company-header')

    <div class="doc-title">
        <h2><u>Immovable Property Condition Report in Relation to the Sale of Any Immovable Property</u><br>
        <span style="font-size:9pt;">(Property Practitioner Act 22 of 2019, Section 70 &ndash; Property Practitioners Regulations 2022 Section 36 &ndash; Mandatory Disclosure)</span></h2>
    </div>

    <div class="clause-head"><span class="num">1</span>Disclaimer</div>
    <p class="clause">This condition report concerns the immovable property situated at
        <span class="field field-wide" data-field="property_address">{{ $property_address ?? '' }}</span>
        (the &ldquo;Property&rdquo;). This report does not constitute a guarantee or warranty of any kind by the owner of the Property or by the property practitioners representing that owner in any transaction. This report should, therefore, not be regarded as a substitute for any inspections or warranties that prospective purchasers may wish to obtain prior to concluding an agreement of sale in respect of the Property.</p>

    <div class="clause-head"><span class="num">2</span>Definitions</div>
    <p class="clause">In this form &ndash;</p>
    <p class="clause"><span class="num">2.1</span>&ldquo;to be aware&rdquo; means to have actual notice or knowledge of a certain fact or state of affairs; and</p>
    <p class="clause"><span class="num">2.2</span>&ldquo;defect&rdquo; means any condition, whether latent or patent, that would or could have a significant deleterious or adverse impact on, or affect, the value of the property, that would or could significantly impair or impact upon the health or safety of any future occupants of the property or that, if not repaired, removed, or replaced, would or could significantly shorten or adversely affect the expected normal lifespan of the Property.</p>

    <div class="clause-head"><span class="num">3</span>Disclosure of information</div>
    <p class="clause">The owner of the Property discloses the information hereunder in the full knowledge that, even though this is not to be construed as a warranty, prospective purchasers of the Property may rely on such information when deciding whether, and on what terms, to purchase the Property. The owner hereby authorises the appointed property practitioner marketing the Property for sale to provide a copy of this statement, and to disclose any information contained in this statement, to any person in connection with any actual or anticipated sale of the Property.</p>

    <div class="clause-head"><span class="num">4</span>Provision of additional information</div>
    <p class="clause">The owner represents that to the best of his or her knowledge the responses to the statements in respect of the Property contained herein have been accurately noted as &ldquo;yes&rdquo;, &ldquo;no&rdquo; or &ldquo;not applicable&rdquo;. Should the owner have responded to any of the statements with a &ldquo;yes&rdquo;, the owner shall be obliged to provide, in the additional information area of this form, a full explanation as to the response to the statement concerned.</p>

    <div class="clause-head"><span class="num">5</span>Statements in connection with Property</div>
    <table class="defects">
        <thead><tr><th>Statement</th><th>Response (Yes / No / N/A)</th></tr></thead>
        <tbody>
        <tr><td>I am aware of the defects in the roof</td><td class="resp"><span class="field" data-field="defect_roof">{{ $defect_roof ?? '' }}</span></td></tr>
        <tr><td>I am aware of the defects in the electrical systems</td><td class="resp"><span class="field" data-field="defect_electrical">{{ $defect_electrical ?? '' }}</span></td></tr>
        <tr><td>I am aware of the defects in the plumbing system, including in the swimming pool (if any)</td><td class="resp"><span class="field" data-field="defect_plumbing">{{ $defect_plumbing ?? '' }}</span></td></tr>
        <tr><td>I am aware of the defects in the heating and air conditioning systems, including the air filters and humidifiers</td><td class="resp"><span class="field" data-field="defect_hvac">{{ $defect_hvac ?? '' }}</span></td></tr>
        <tr><td>I am aware of the defects in the septic or other sanitary disposal systems</td><td class="resp"><span class="field" data-field="defect_septic">{{ $defect_septic ?? '' }}</span></td></tr>
        <tr><td>I am aware of any defects to the property and/or in the basement or foundations of the property, including cracks, seepage and bulges. Other such defects include, but are not limited to, flooding, dampness or wet walls and unsafe concentrations of mould or defects in drain tiling or sump pumps</td><td class="resp"><span class="field" data-field="defect_foundations">{{ $defect_foundations ?? '' }}</span></td></tr>
        <tr><td>I am aware of structural defects in the Property</td><td class="resp"><span class="field" data-field="defect_structural">{{ $defect_structural ?? '' }}</span></td></tr>
        <tr><td>I am aware of boundary line dispute, encroachments, or encumbrances in connection with the Property</td><td class="resp"><span class="field" data-field="defect_boundary">{{ $defect_boundary ?? '' }}</span></td></tr>
        <tr><td>I am aware that remodelling and refurbishment have affected the structure of the Property</td><td class="resp"><span class="field" data-field="defect_remodelling">{{ $defect_remodelling ?? '' }}</span></td></tr>
        <tr><td>I am aware that any additions or improvements made to or any erections made on the property, have been done or were made, only after the required consents, permissions and permits to do so were properly obtained.</td><td class="resp"><span class="field" data-field="defect_consents">{{ $defect_consents ?? '' }}</span></td></tr>
        <tr><td>I am aware that a structure on the Property has been earmarked as a historic structure or heritage site</td><td class="resp"><span class="field" data-field="defect_heritage">{{ $defect_heritage ?? '' }}</span></td></tr>
        </tbody>
    </table>
    <div class="clause-head">Additional information</div>
    <div class="addl"><span class="field field-wide" data-field="additional_information" style="border:none;min-width:100%;">{{ $additional_information ?? '' }}</span></div>

    <div class="clause-head"><span class="num">6</span>Owner&rsquo;s certification</div>
    <p class="clause">The owner hereby certifies that the information provided in this report is, to the best of the owner&rsquo;s knowledge and belief, true and correct as at the date when the owner signs this report.</p>

    <div class="clause-head"><span class="num">7</span>Certification by person supplying information</div>
    <p class="clause">If a person other than the owner of the property provides the required information that person must certify that he/she is duly authorised by the owner to supply the information and that he/she has supplied the correct information on which the owner relied for the purposes of this report and, in addition, that the information contained herein is, to the best of that person&rsquo;s knowledge and belief, true and correct as at the date on which that person signs this report.</p>

    <div class="clause-head"><span class="num">8</span>Notice regarding advice or inspections</div>
    <p class="clause">Both the owner as well as potential buyers of the property may wish to obtain professional advice and/or to undertake a professional inspection of the property. Under such circumstances adequate provisions must be contained in any agreement of sale to be concluded between the parties pertaining to the obtaining of any such professional advice and/or the conducting of required inspections and/or the disclosure of defects and/or the making of required warranties.</p>

    <div class="clause-head"><span class="num">9</span>Buyer&rsquo;s acknowledgement</div>
    <p class="clause">The prospective buyer acknowledges that he/she has been informed that professional expertise and/or technical skill and knowledge may be required to detect defects in, and non-compliant aspects concerning, the property. The prospective buyer acknowledges receipt of a copy of this statement.</p>

    <div class="signature-section">
        <div class="signature-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="signature-col" data-marker-party="owner" data-marker-index="0">
                <div class="signature-line"></div>
                <div class="signature-label">Seller / Owner</div>
            </div>
            <div class="signature-col" data-marker-party="agent" data-marker-index="0">
                <div class="signature-line"></div>
                <div class="signature-label">Agent</div>
            </div>
            <div class="signature-col" data-marker-party="buyer" data-marker-index="0">
                <div class="signature-line"></div>
                <div class="signature-label">Buyer</div>
            </div>
        </div>
    </div>

    <div class="doc-footer">Sales Mandatory Disclosure</div>

</div>
</body>
</html>
