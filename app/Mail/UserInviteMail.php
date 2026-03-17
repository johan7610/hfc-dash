<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class UserInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $setupUrl;
    public string $userName;

    public function __construct(public User $user)
    {
        $this->userName = $user->name;

        // Signed URL — expires in 7 days
        $this->setupUrl = URL::temporarySignedRoute(
            'account.setup',
            now()->addDays(7),
            ['user' => $user->id]
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve been invited to CoreX OS',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invite',
        );
    }
}
