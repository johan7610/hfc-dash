<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mandate entered into between</title>
    <link href="/css/corex-document.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="corex-document-wrapper">
<div class="corex-page">

@include("docuperfect.web-templates.components.company-header")

<table class="corex-table"><tbody><tr><td>Johan and Elize Properties T/AShop 5 The Emporium, cnr King Rd &amp; Marine Drive, Shelly Beach                                              Fax No: 086 514 7632     Reg no:   2017/431318/07                                                                                                               FFC: 202615038880000Vat: 463087821Email Address:    @hfcoastal.co.za                                                                                    FIC AI/180629/0000019                Elize Reichel Cell:  071 351 0291                                                                                  Johan Reichel Cell: 076&nbsp;618 5578</td></tr></tbody></table><div class="corex-h2">Mandate entered into between</div>
<div class="corex-h2">The Parties</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">The Owner/s:	<span class="corex-field-value" data-field="lessor_full">{{ $lessor_full ?? '' }}</span>		</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Home Finders Coastal (Agent):	<span class="corex-field-value" data-field="agent_name">{{ $agent_name ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">1</span> <span class="corex-clause-text"> The owner hereby grants to the Agent a Mandate to offer to let the property known </span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">as <span class="corex-field-value" data-field="property_full">{{ $property_full ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">subject to the conditions set out in this agreement.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">2</span> <span class="corex-clause-text">The rental amount required by the Owner for the property is R<span class="corex-field-value" data-field="monthly_rental">{{ $monthly_rental ?? '' }}</span> which includes the commission as stated in clause 4.  In the event of the Agency not finding a suitable Tenant to rent the property at such rental amount, then, between the Owner and the Agency they will agree to an acceptable rental amount prior to allowing any tenant taking occupation of the said property, which includes commission as stated in clause 4.</span></div>
<div class="corex-signature-section"><div class="corex-signature-section-title">THUS DONE AND SIGNED</div><div class="corex-clause"><span class="corex-clause-text">The sole mandate hereby granted shall commence on date of signature hereof and shall remain in force until 22h00 on the <span class="corex-field-value" data-field="mandate_expiry">{{ $mandate_expiry ?? '' }}</span>. The Owner will pay to the Agent a commission, calculated at a percentage of  % plus VAT on the letting price of the property. The Agency will screen all possible tenants prior to occupation to ensure a hassle free letting of the property. The Agent will deposit the monthly rental collections into the following Bank Account supplied by the Owner, by no later than the 7th day of every month. Account Holder’s Name: Branch Name and Code: Owner’s Contact details: Owner’s Email Address: The Owner shall supply the Agency with water and lights service usage charges every month, so the Agency may add this to the statement forwarded to the Tenant.</span></div></div>

@include("docuperfect.web-templates.components.signature-block", ["parties" => ["Lessor", "Agent"]])

</div>
</div>

</body>
</html>
