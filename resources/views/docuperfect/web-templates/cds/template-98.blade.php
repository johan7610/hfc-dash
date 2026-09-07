<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MARKETING PERMISSION</title>
    <link href="/css/corex-document.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="corex-document-wrapper">
<div class="corex-page">

@include("docuperfect.web-templates.components.company-header")

<div class="corex-h1">MARKETING PERMISSION</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">I / We <span class="corex-field-value" data-field="seller_full">{{ $seller_full ?? '' }}</span> the undersigned, being the registered owner/s, or duly authorised representative/s of the Lessor of the</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text"> Property Erf / Sectional Scheme / Unit no <span class="corex-field-value" data-field="unit_no">{{ $unit_no ?? '' }}</span> in the Complex / Estate known as</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text"><span class="corex-field-value" data-field="property_complex_name">{{ $property_complex_name ?? '' }}</span> in <span class="corex-field-value" data-field="property_street">{{ $property_street ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">(Street) in <span class="corex-field-value" data-field="property_town">{{ $property_town ?? '' }}</span> (Township) <span class="corex-field-value" data-field="property_district">{{ $property_district ?? '' }}</span> (District) together with all fixtures and fittings of a permanent nature pertaining to the property, do hereby, irrevocably, grant to Home Finders Coastal the marketing permission, right and authority to rent the abovementioned property.</span></div>
<div class="corex-h1">1.  DOMICILUM CITANDI ET EXECUTANDI</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Lessor:  </span></div>
<div class="corex-clause corex-clause-indent-1" data-role-block="lessor" data-role-block-segment="address"><span class="corex-clause-text">Physical address <span class="corex-field-value" data-field="lessor_address">{{ $lessor_address ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1" data-role-block="lessor" data-role-block-segment="contact"><span class="corex-clause-text">Tel: <span class="corex-field-value" data-field="lessor_cell">{{ $lessor_cell ?? '' }}</span> Email: <span class="corex-field-value" data-field="lessor_email">{{ $lessor_email ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-2"><span class="corex-clause-number">1.1</span> <span class="corex-clause-text">The rental amount required by the owner of the property is R<span class="corex-field-value" data-field="monthly_rental">{{ $monthly_rental ?? '' }}</span>(<span class="corex-field-value" data-field="price_in_words">{{ $price_in_words ?? '' }}</span>)</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Which includes Agencies commission of <span class="corex-field-value" data-field="property_commission_percent">{{ $property_commission_percent ?? '' }}</span>% plus VAT</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">The Lessor hereby gives consent to Home Finders Coastal to place a “To Let” board on the Property.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Other: </span></div>
<div class="corex-h1">~~~~OTHER_CONDITIONS~~~~</div>
<div class="corex-h2">Addendum A – Service Fee</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">The parties hereby agree that the agent will be responsible for the following:</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">1.  Source a 	lessee</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">2.  Negotiate a rental contract 	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">3.  Secure deposit	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">4.  Secure first month’s rental	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">5.  Report on defects to the lessor	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">6.  Collect the monthly rental	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">7.  Ongoing liaison with the lessee	</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">8.      Collect the monthly Municipal/Eskom account from lessor and pay over to the selected person.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">The Agent shall earn an ongoing Service Fee equal to 10 %( plus vat) of the monthly rental for the duration of the lease and any extension thereof.</span></div>
<div class="corex-h2">Breakdown</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Total Rental Amount 				<span class="corex-field-value" data-field="monthly_rental">{{ $monthly_rental ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Less Agent’s Service Fee (Including VAT)	<span class="corex-field-value" data-field="agents_fee_including_vat">{{ $agents_fee_including_vat ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Let’s Assist					<span class="corex-field-value" data-field="lets_assist_fee">{{ $lets_assist_fee ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Net Amount to Lessor				<span class="corex-field-value" data-field="nett_amount_to_lessor">{{ $nett_amount_to_lessor ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">             @include("docuperfect.web-templates.components.signature-line", ['party' => 'landlord'])
@include("docuperfect.web-templates.components.signature-line", ['party' => 'agent'])</span></div>

@include("docuperfect.web-templates.components.signature-block", ["parties" => ["Lessor", "Agent"]])

</div>
</div>

</body>
</html>
