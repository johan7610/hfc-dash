<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Letting Mandate — Home Finders Coastal</title>
    <style>
        /* ============================================================
           Letting Mandate V5 — Print-quality A4 document
           ============================================================ */
        @page {
            size: A4;
            margin: 18mm 20mm 15mm 20mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1a1a1a;
            background: white;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 18mm 20mm 15mm 20mm;
            background: white;
        }

        @media screen {
            body {
                background: #e5e7eb;
            }
            .page {
                box-shadow: 0 2px 16px rgba(0,0,0,0.15);
                margin-top: 20px;
                margin-bottom: 20px;
            }
        }

        @media print {
            body { background: white; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }

        /* ---- Company Header ---- */
        .company-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 10pt;
            border-bottom: 2pt solid #1a1a1a;
            margin-bottom: 12pt;
        }

        .header-left {
            flex: 1;
        }

        .header-logo {
            max-height: 60px;
            width: auto;
            margin-bottom: 4pt;
            display: block;
        }

        .trading-name {
            font-size: 9pt;
            color: #555;
            margin-bottom: 1pt;
        }

        .company-name {
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 0.5pt;
            color: #0b2a4a;
        }

        .tagline {
            font-size: 9pt;
            font-weight: bold;
            color: #0b2a4a;
            letter-spacing: 1pt;
            text-transform: uppercase;
            margin-top: 2pt;
        }

        .header-right {
            text-align: right;
            font-size: 8.5pt;
            color: #333;
            max-width: 52%;
        }

        .header-address {
            font-size: 8.5pt;
            margin-bottom: 3pt;
        }

        .header-details {
            margin-left: auto;
            border-collapse: collapse;
            font-size: 8.5pt;
        }

        .header-details td {
            padding: 0 0 0 8pt;
            text-align: right;
        }

        .header-details td:first-child {
            font-weight: 600;
            padding-left: 0;
        }

        .header-contact {
            margin-top: 3pt;
            font-size: 8pt;
            color: #555;
        }

        .header-contact span {
            margin-left: 8pt;
        }

        .header-contact span:first-child {
            margin-left: 0;
        }

        /* ---- Document Title ---- */
        .doc-title {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            margin: 14pt 0 10pt;
            text-decoration: underline;
        }

        /* ---- Field values (inline blanks) ---- */
        .field {
            display: inline-block;
            min-width: 180pt;
            border-bottom: 1pt solid #1a1a1a;
            padding: 0 4pt;
            text-align: left;
            vertical-align: bottom;
            line-height: 1.6;
        }

        .field:empty::after {
            content: '\00a0';
        }

        .field-short {
            min-width: 60pt;
        }

        .field-tiny {
            min-width: 30pt;
        }

        .field-medium {
            min-width: 120pt;
        }

        .field-wide {
            min-width: 100%;
        }

        .field-currency::before {
            content: 'R';
            margin-right: 2pt;
        }

        /* ---- Clauses ---- */
        .clause {
            margin: 6pt 0;
            padding-left: 20pt;
            text-indent: -20pt;
        }

        .clause-number {
            font-weight: bold;
            display: inline;
        }

        .clause-text {
            display: inline;
        }

        .section-label {
            font-weight: bold;
            text-decoration: underline;
            margin: 10pt 0 6pt;
        }

        /* ---- Bank Details Table ---- */
        .bank-details {
            width: 100%;
            margin: 6pt 0;
            border-collapse: collapse;
        }

        .bank-details td {
            padding: 3pt 4pt;
            vertical-align: bottom;
        }

        .bank-details td:first-child {
            width: 190pt;
            font-weight: 600;
        }

        .bank-details td:last-child {
            border-bottom: 1pt solid #1a1a1a;
        }

        /* ---- Signature Section ---- */
        .signature-section {
            margin-top: 16pt;
        }

        .signature-section p {
            margin-bottom: 4pt;
        }

        .signature-grid {
            display: grid;
            gap: 20pt;
            margin-top: 30pt;
        }

        .signature-col {
            text-align: center;
        }

        .signature-line {
            border-bottom: 1pt solid #1a1a1a;
            height: 30pt;
            margin-bottom: 3pt;
        }

        .signature-label {
            font-size: 9pt;
            font-weight: 600;
            text-transform: uppercase;
        }

        .print-line {
            border-bottom: 1pt dotted #999;
            height: 22pt;
            margin-top: 4pt;
            margin-bottom: 3pt;
        }

        .print-label {
            font-size: 8pt;
            color: #666;
        }

        /* ---- Footer ---- */
        .doc-footer {
            margin-top: 12pt;
            text-align: right;
            font-size: 8pt;
            color: #999;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Company Header --}}
    @include('docuperfect.web-templates.components.company-header')

    {{-- Document Title --}}
    <div class="doc-title">Mandate entered into between</div>

    {{-- The Parties --}}
    <p class="section-label">The Parties</p>

    <p>The Owner/s: <span class="field">{{ $lessor_name ?? '' }}</span>
        (hereinafter referred to as the &ldquo;Owner&rdquo;)</p>

    <p style="margin-top: 6pt;">Home Finders Coastal (Agent): <span class="field">{{ $agent_name ?? '' }}</span>
        (hereinafter referred to as the &ldquo;Agent&rdquo;)</p>

    {{-- Clause 1 --}}
    <div class="clause">
        <span class="clause-number">1.</span>
        <span class="clause-text">The Owner hereby grants the Agent a sole and exclusive mandate to lease the property
            described as <span class="field field-wide">{{ $property_address ?? '' }}</span>
            (hereinafter referred to as the &ldquo;Property&rdquo;).</span>
    </div>

    {{-- Clause 2 --}}
    <div class="clause">
        <span class="clause-number">2.</span>
        <span class="clause-text">The rental amount shall be
            <span class="field field-medium field-currency">{{ $rental_amount ?? '' }}</span>
            ({{ $rental_in_words ?? 'amount in words' }})
            per month, payable in advance on the 1st of each month. Commission as per clause 4.</span>
    </div>

    {{-- Clause 3 --}}
    <div class="clause">
        <span class="clause-number">3.</span>
        <span class="clause-text">This mandate shall commence on the date of signature and remain in full force
            until 22h00 on the <span class="field field-short">{{ $mandate_day ?? '' }}</span> day
            of <span class="field field-medium">{{ $mandate_month ?? '' }}</span>
            20<span class="field field-tiny">{{ $mandate_year ?? '' }}</span>,
            whereafter it shall automatically renew for successive periods of 12 months unless cancelled by
            either party giving 90 (ninety) days written notice.</span>
    </div>

    {{-- Clause 4 --}}
    <div class="clause">
        <span class="clause-number">4.</span>
        <span class="clause-text">The Owner agrees to pay the Agent commission of
            <span class="field field-short">{{ $commission_percent ?? '' }}</span>% plus VAT
            of the monthly rental, payable monthly in arrears from rental received.
            A procurement fee equal to the commission percentage of one month&rsquo;s rental plus VAT is payable
            upon conclusion of a lease agreement with a tenant procured by the Agent.</span>
    </div>

    {{-- Clause 5 --}}
    <div class="clause">
        <span class="clause-number">5.</span>
        <span class="clause-text">The Agent shall screen all prospective tenants and provide the Owner with
            relevant information to assist in the selection of a suitable tenant. The final decision on the
            acceptance of a tenant shall rest with the Owner.</span>
    </div>

    {{-- Clause 6 --}}
    <div class="clause">
        <span class="clause-number">6.</span>
        <span class="clause-text">The rental deposits and monthly rental shall be paid into the following bank account:</span>
    </div>

    <table class="bank-details">
        <tr>
            <td>Account Holder&rsquo;s Name:</td>
            <td>{{ $account_holder ?? '' }}</td>
        </tr>
        <tr>
            <td>Bank Name:</td>
            <td>{{ $bank_name ?? '' }}</td>
        </tr>
        <tr>
            <td>Account Number:</td>
            <td>{{ $account_number ?? '' }}</td>
        </tr>
        <tr>
            <td>Branch Name and Code:</td>
            <td>{{ $branch_name ?? '' }}</td>
        </tr>
    </table>

    <p style="margin-top: 8pt;">Owner&rsquo;s Contact details:
        <span class="field">{{ $owner_contact ?? '' }}</span></p>

    <p style="margin-top: 4pt;">Owner&rsquo;s Email Address:
        <span class="field">{{ $owner_email ?? '' }}</span></p>

    {{-- Clause 7 --}}
    <div class="clause">
        <span class="clause-number">7.</span>
        <span class="clause-text">The Owner acknowledges that they are responsible for the payment of water
            and electricity accounts for the Property. In the event that the tenant fails to pay the water
            and electricity accounts, the Owner shall be liable for such payments.</span>
    </div>

    {{-- Signature Block --}}
    @include('docuperfect.web-templates.components.signature-block', [
        'parties' => ['Owner', 'Owner', 'Agent'],
        'signed_at_location' => $signed_at_location ?? null,
        'signed_day' => $signed_day ?? null,
        'signed_month' => $signed_month ?? null,
        'signed_time' => $signed_time ?? null,
        'signed_ampm' => $signed_ampm ?? null,
    ])

    {{-- Footer --}}
    <div class="doc-footer">Version 5</div>

</div>
</body>
</html>
