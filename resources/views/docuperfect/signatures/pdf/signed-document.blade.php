<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; size: A4 portrait; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; }

        .page-container {
            position: relative;
            width: 210mm;
            height: 297mm;
            overflow: hidden;
            page-break-after: always;
        }
        .page-container:last-child {
            page-break-after: auto;
        }

        .page-image {
            width: 210mm;
            height: 297mm;
            display: block;
        }

        .audit-page {
            width: 210mm;
            min-height: 297mm;
            padding: 0;
        }
    </style>
</head>
<body>
    @foreach($pages as $i => $pageData)
        @if(str_starts_with($pageData, 'audit:'))
            <div class="page-container audit-page">
                {!! base64_decode(substr($pageData, 6)) !!}
            </div>
        @else
            <div class="page-container">
                <img class="page-image" src="{{ $pageData }}" alt="Page {{ $i + 1 }}">
            </div>
        @endif
    @endforeach
</body>
</html>
