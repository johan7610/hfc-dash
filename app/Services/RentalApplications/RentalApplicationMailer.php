<?php

namespace App\Services\RentalApplications;

use App\Mail\RentalApplicationInviteMail;
use App\Models\RentalApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RentalApplicationMailer
{
    /**
     * Best-effort — a mail failure must never break the send action itself
     * (the record is already saved and the links are shown on-screen either
     * way, same "never Mail::to('') and die" posture as the e-sign mailer).
     */
    public function sendInvite(RentalApplication $application): bool
    {
        $recipientEmail = $application->recipientEmail();

        if (! $recipientEmail) {
            return false;
        }

        try {
            Mail::to($recipientEmail)->send(new RentalApplicationInviteMail($application));

            return true;
        } catch (\Throwable $e) {
            Log::warning('AT-392 rental application invite mail failed', [
                'rental_application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
