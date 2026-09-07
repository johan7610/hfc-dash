<?php

namespace App\Mail;

use App\Models\RentalApplication;
use App\Models\RentalApplicationDeclineEmailSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * AT-392 authoriser flow — the applicant-facing decline email. Wording is
 * agency-configurable (RentalApplicationDeclineEmailSetting), a suggested
 * default until the agency saves their own. Built and ready; NOT yet wired
 * into an actual decline() action — that lives in the authorisation
 * controller, held pending the RO/CO tier confirmation.
 */
class RentalApplicationDeclineMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subject_;
    public string $bodyText;

    public function __construct(public RentalApplication $application)
    {
        $applicantName = $application->contact->full_name ?: 'there';
        $agencyName = $application->agency->name ?? config('mail.from.name', 'CoreX OS');

        $wording = RentalApplicationDeclineEmailSetting::forAgency((int) $application->agency_id);

        $this->subject_ = RentalApplicationDeclineEmailSetting::render($wording['subject'], $applicantName, $agencyName);
        $this->bodyText = RentalApplicationDeclineEmailSetting::render($wording['body'], $applicantName, $agencyName);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject_);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.rental-application-decline');
    }
}
