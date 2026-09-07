<?php

namespace App\Mail;

use App\Models\RentalApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * AT-392 authoriser flow — the applicant-facing approval email. Johan:
 * "accept flow should essentially maybe email applicant... congrats you are
 * approved to rent for x amount." Deliberately NOT agency-configurable
 * (unlike the decline email) — not asked for, and NOT built with any
 * "matching properties" content — Johan was explicit that idea is still
 * unsettled and not to build it: "here is a list of properties that
 * matches wishlist? ... IDEA, not settled. Do NOT build it."
 */
class RentalApplicationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $applicantName;
    public string $agencyName;
    public string $amount;

    public function __construct(public RentalApplication $application)
    {
        $this->applicantName = $application->contact->full_name ?: 'there';
        $this->agencyName = $application->agency->name ?? config('mail.from.name', 'CoreX OS');
        $this->amount = number_format((float) $application->approved_rental_amount, 2);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Congratulations — you're approved to rent! — {$this->agencyName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.rental-application-approved');
    }
}
