{{-- E-Sign V3 Phase 1B.9 (FIX 1) — Flag Removal invalid-token screen. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consent link unavailable</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
</head>
<body style="font-family: 'Figtree', Arial, sans-serif; background: #f4f6fb; margin: 0; padding: 4rem 1rem; color: #1f2937;">
<div style="max-width: 480px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 8px; text-align: center;">

    @php
        $message = match ($reason ?? '') {
            'unknown_token' => 'This consent link is not recognised. It may have been mistyped or has been used already.',
            'consented'     => 'This consent decision has already been recorded as AUTHORISED.',
            'rejected'      => 'This consent decision has already been recorded as DECLINED.',
            'expired'       => 'This consent link has expired. Ask your agent to send a new request.',
            'cancelled'     => 'The agent cancelled this removal request.',
            default         => 'This consent link is no longer active.',
        };
    @endphp

    <h1 style="color: #92400e; margin: 0 0 0.6rem;">Consent link unavailable</h1>
    <p style="color: #4b5563;">{{ $message }}</p>

    @if(isset($removal) && $removal->status === 'consented')
        <p style="font-size: 0.85rem; color: #6b7280; margin-top: 1rem;">
            Recorded on {{ $removal->consent_received_at?->format('d M Y H:i') }}.
        </p>
    @endif
</div>
</body>
</html>
