<?php

namespace App\Mail\Signatures;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SupersededNotificationMail extends BaseSignatureMail
{
    public function __construct(
        public string $signerName,
        public string $documentName,
        public string $agentName,
        public ?string $agentEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->getFromAddress(),
            replyTo: $this->getReplyTo(),
            subject: "Document revised: {$this->documentName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signatures.superseded-notification',
            with: [
                'agentFooter' => $this->getAgentFooter(),
            ],
        );
    }
}
