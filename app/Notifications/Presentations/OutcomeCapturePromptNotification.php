<?php

declare(strict_types=1);

namespace App\Notifications\Presentations;

use App\Models\Presentation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 8 — daily nudge sent by PromptOutcomeCaptureJob to the listing agent
 * for any presentation older than 30 days without a recorded outcome.
 *
 * Mail + database. Database row fills the in-app "outcome to capture" badge;
 * mail is the public chase. Each quick-link goes straight to the modal
 * pre-filled with that outcome (modal reads the ?prefill=... query string).
 */
final class OutcomeCapturePromptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $presentationId,
        public readonly int $daysSinceCreation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $presentation = Presentation::with('property', 'sellerContact')->find($this->presentationId);
        if (!$presentation) {
            return (new MailMessage())->subject('Presentation no longer exists');
        }

        $address = $presentation->property_address
            ?? ($presentation->property?->address ?? 'the property');
        $sellerFirst = $presentation->sellerContact?->first_name ?: ($presentation->seller_name ?: 'the seller');
        $presDate = $presentation->created_at?->format('j M Y') ?: 'recently';

        $showUrl = route('presentations.show', $presentation->id);

        return (new MailMessage())
            ->subject('How did the ' . $address . ' pitch go?')
            ->greeting('Quick check-in')
            ->line('You presented to ' . $sellerFirst . ' for ' . $address . ' on ' . $presDate . '.')
            ->line("It's been " . $this->daysSinceCreation . ' days. Let us know what happened so we can learn from this:')
            ->action('→ Won the mandate',          $showUrl . '?outcome=won_mandate')
            ->line('Or pick another outcome:')
            ->line('• Lost to another agency: ' . $showUrl . '?outcome=lost_to_competitor')
            ->line('• Still working on it: '    . $showUrl . '?outcome=still_pending')
            ->line('• Other outcome: '          . $showUrl . '?outcome=other')
            ->line('Capturing outcomes helps you (and your team) get better at winning mandates.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'                 => 'presentation_outcome_prompt',
            'presentation_id'      => $this->presentationId,
            'days_since_creation' => $this->daysSinceCreation,
        ];
    }
}
