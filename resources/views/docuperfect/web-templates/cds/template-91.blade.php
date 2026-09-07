<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZZZ CANDIDATE AUTHORISER PROOF - NATURAL PERSONS ADDENDUM</title>
    <link href="/css/corex-document.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="corex-document-wrapper">
<div class="corex-page">

@include("docuperfect.web-templates.components.company-header")

<div class="corex-h1">ZZZ CANDIDATE AUTHORISER PROOF - NATURAL PERSONS ADDENDUM</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">This addendum confirms the agreement between the Seller and the Agent, both natural persons.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">1. The Seller acknowledges the terms of this addendum.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">2. The Agent confirms the mandate on behalf of the agency.</span></div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Signed by the Seller: @include("docuperfect.web-templates.components.signature-line", ['party' => 'seller'])</span></div>

@include("docuperfect.web-templates.components.signature-block", ["parties" => ["Lessor", "Agent"]])

</div>
</div>

</body>
</html>
