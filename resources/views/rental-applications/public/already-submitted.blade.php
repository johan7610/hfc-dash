<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Application Received</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md text-center">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h1 class="text-xl font-bold text-slate-800 mb-2">Application already received</h1>
            <p class="text-sm text-slate-500">
                Your rental application is already being processed
                @if($application->submitted_at)
                    (submitted {{ $application->submitted_at->format('d M Y') }}).
                @else
                    .
                @endif
            </p>
            <p class="text-sm text-slate-500 mt-2">Please contact your agent if you need to change anything.</p>
        </div>
    </div>
</body>
</html>
