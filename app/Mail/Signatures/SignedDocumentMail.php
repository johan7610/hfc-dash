<?php

namespace App\Mail\Signatures;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SignedDocumentMail extends BaseSignatureMail
{
    public function __construct(
        public string $recipientName,
        public string $documentName,
        public ?string $downloadUrl = null,
        public array $progress = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->getFromAddress(),
            replyTo: $this->getReplyTo(),
            subject: "Fully signed: {$this->documentName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signatures.signed-document',
            with: [
                'agentFooter' => $this->getAgentFooter(),
            ],
        );
    }
}
