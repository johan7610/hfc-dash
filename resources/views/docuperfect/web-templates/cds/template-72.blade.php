<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSign — Other Conditions Demo (Staging)</title>
    <link href="/css/corex-document.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="corex-document-wrapper">
<div class="corex-page">

@include("docuperfect.web-templates.components.company-header")

<p>This document confirms the agreed sale terms between the parties.</p><p>The purchase price and deposit terms are as recorded above.</p><p>Occupation date and possession are as agreed between the parties.</p>

@include("docuperfect.web-templates.components.signature-block", ["parties" => ["Lessor", "Lessee", "Agent"]])

</div>
</div>

</body>
</html>
