<?php

declare(strict_types=1);

namespace App\Notifications\Presentations;

use App\Models\PresentationOutcome;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 8 — congratulatory ping when an agent records outcome=won_mandate
 * (or won_sale). Notifiables: the agent's branch BM + the agency principal.
 *
 * Mail + database. Mail is short and celebratory — no admin tone. Database
 * row drives a "team wins" widget on BM dashboards (not built in this phase,
 * but the notification row is the data source).
 */
final class WonMandateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $outcomeId) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $outcome = PresentationOutcome::with(['presentation.property', 'recorder', 'deal'])
            ->find($this->outcomeId);
        if (!$outcome) {
            return (new MailMessage())->subject('Mandate win — outcome removed');
        }

        $agent   = $outcome->recorder?->name ?? 'A team member';
        $address = $outcome->presentation?->property_address
            ?? ($outcome->presentation?->property?->address ?? 'a property');
        $isSale  = $outcome->outcome === PresentationOutcome::OUTCOME_WON_SALE;

        $mail = (new MailMessage())
            ->subject($agent . ' won a mandate: ' . $address)
            ->greeting('Nice one — a mandate just landed')
            ->line($agent . ' just recorded ' . ($isSale ? 'a sale' : 'a won mandate') . ' for ' . $address . '.')
            ->action('Open the presentation', route('presentations.show', $outcome->presentation_id));

        if ($outcome->notes) {
            $mail->line('Agent notes: "' . $outcome->notes . '"');
        }
        if ($outcome->deal) {
            $mail->line('Linked deal: #' . $outcome->deal->deal_no);
        }

        return $mail->line('Capturing outcomes like this is what turns lessons into wins.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'presentation_outcome_won',
            'outcome_id'        => $this->outcomeId,
        ];
    }
}
