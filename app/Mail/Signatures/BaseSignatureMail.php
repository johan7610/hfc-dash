<?php

namespace App\Mail\Signatures;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

abstract class BaseSignatureMail extends Mailable
{
    use Queueable, SerializesModels;

    protected ?User $agent = null;

    /**
     * Set the agent (sender) for this email.
     * Returns $this for fluent chaining.
     */
    public function fromAgent(User $agent): static
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * Get the "from" address — uses the agent's email if set, otherwise falls back to system default.
     */
    protected function getFromAddress(): Address
    {
        if ($this->agent) {
            return new Address($this->agent->email, $this->agent->name . ' — Home Finders Coastal');
        }

        return new Address(
            config('mail.from.address', 'noreply@hfcoastal.co.za'),
            config('mail.from.name', 'Home Finders Coastal')
        );
    }

    /**
     * Get the reply-to address — agent's email so replies go to the agent.
     */
    protected function getReplyTo(): array
    {
        if ($this->agent) {
            return [new Address($this->agent->email, $this->agent->name)];
        }

        return [];
    }

    /**
     * Get agent footer data for the email template.
     */
    protected function getAgentFooter(): array
    {
        if ($this->agent) {
            return [
                'name' => $this->agent->name,
                'email' => $this->agent->email,
                'phone' => $this->agent->phone ?? null,
            ];
        }

        return [
            'name' => 'Home Finders Coastal',
            'email' => config('mail.from.address', 'noreply@hfcoastal.co.za'),
            'phone' => null,
        ];
    }
}
