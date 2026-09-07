<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AT390 PROOF DOCUMENT</title>
    <link href="/css/corex-document.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="corex-document-wrapper">
<div class="corex-page">

@include("docuperfect.web-templates.components.company-header")

<div class="corex-h1">AT390 PROOF DOCUMENT</div>
<div class="corex-clause corex-clause-indent-1"><span class="corex-clause-text">Applicant name: @include("docuperfect.web-templates.components.signature-line")INPUT@include("docuperfect.web-templates.components.signature-line", ['party' => 'landlord'])</span></div>

@include("docuperfect.web-templates.components.signature-block", ["parties" => ["Lessor", "Lessee", "Agent"]])

</div>
</div>

</body>
</html>
