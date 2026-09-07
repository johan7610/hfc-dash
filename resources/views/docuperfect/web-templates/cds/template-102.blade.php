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

<div class="corex-h2">Mandate entered into between</div>
<div class="corex-h2">The Parties</div>
<div class="corex-clause corex-clause-indent-1" data-role-block="lessor"><span class="corex-clause-text">The Owner/s:			<span class="corex-field-value" data-field="lessor_name">{{ $lessor_name ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Home Finders Coastal (Agent):	<span class="corex-field-value" data-field="agent_name">{{ $agent_name ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">1</span> <span class="corex-clause-text"> The owner hereby grants to the Agent a Mandate to offer to let the property known </span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">as <span class="corex-field-value" data-field="property_address_suburb">{{ $property_address_suburb ?? '' }}</span> subject to the conditions set out in this agreement.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">2</span> <span class="corex-clause-text">The rental amount required by the Owner for the property is R<span class="corex-field-value" data-field="monthly_rental">{{ $monthly_rental ?? '' }}</span> which includes the commission as stated in clause 4.  In the event of the Agency not finding a suitable Tenant to rent the property at such rental amount, then, between the Owner and the Agency they will agree to an acceptable rental amount prior to allowing any tenant taking occupation of the said property, which includes commission as stated in clause 4.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">3</span> <span class="corex-clause-text">The sole mandate hereby granted shall commence on date of signature hereof and shall remain in force until 22h00 on the <span class="corex-field-value" data-field="mandate_start_date">{{ $mandate_start_date ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">4</span> <span class="corex-clause-text">The Owner will pay to the Agent a commission, calculated at a percentage of <span class="corex-field-value" data-field="property_commission_percent">{{ $property_commission_percent ?? '' }}</span>% plus VAT on the letting price of the property.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">5</span> <span class="corex-clause-text">The Agency will screen all possible tenants prior to occupation to ensure a hassle free letting of the property.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">6</span> <span class="corex-clause-text">The Agent will deposit the monthly rental collections into the following Bank Account supplied by the Owner, by no later than the 7th day of every month.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Account Holder’s Name:		<span class="corex-field-value" data-field="account_holder_name">{{ $account_holder_name ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Bank Name:			<span class="corex-field-value" data-field="account_holder_bank">{{ $account_holder_bank ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Account Number:		<span class="corex-field-value" data-field="account_number">{{ $account_number ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Branch Name and Code:	<span class="corex-field-value" data-field="account_branch_and_code">{{ $account_branch_and_code ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1" data-role-block="lessor" data-role-block-segment="contact"><span class="corex-clause-text">Owner’s Contact details:	<span class="corex-field-value" data-field="lessor_cell">{{ $lessor_cell ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1" data-role-block="lessor" data-role-block-segment="contact"><span class="corex-clause-text">Owner’s Email Address:		<span class="corex-field-value" data-field="lessor_email">{{ $lessor_email ?? '' }}</span></span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-number">7</span> <span class="corex-clause-text">  The Owner shall supply the Agency with water and lights service usage charges every month, so the Agency may add this to the statement forwarded to the Tenant.<br><br>@include("docuperfect.web-templates.components.signature-line", ['party' => 'landlord'])
@include("docuperfect.web-templates.components.signature-line", ['party' => 'agent'])<br></span></div>

@include("docuperfect.web-templates.components.signature-block", ["parties" => ["Lessor", "Agent"]])

</div>
</div>

</body>
</html>
