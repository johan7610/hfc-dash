<?php

namespace App\Mail\Signatures;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DocumentCancelledMail extends BaseSignatureMail
{
    public function __construct(
        public string $signerName,
        public string $documentName,
        public string $agentName,
        public string $cancellationReason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->getFromAddress(),
            replyTo: $this->getReplyTo(),
            subject: "Document cancelled: {$this->documentName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signatures.document-cancelled',
            with: [
                'agentFooter' => $this->getAgentFooter(),
            ],
        );
    }
}
