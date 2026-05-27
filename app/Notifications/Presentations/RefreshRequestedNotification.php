<?php

declare(strict_types=1);

namespace App\Notifications\Presentations;

use App\Models\PresentationRefreshRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 7 — fired when a seller submits a refresh request via /p/{token}/refresh.
 *
 * Mail + database. Mail goes to the link's creator (the agent who issued it);
 * database channel feeds the sidebar "Refresh requests" badge.
 */
final class RefreshRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $requestId) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = PresentationRefreshRequest::with(['presentation.property', 'link'])->find($this->requestId);
        if (!$request) {
            return (new MailMessage())->subject('Refresh request unavailable');
        }

        $address = $request->presentation?->property_address
            ?? ($request->presentation?->property?->address ?? 'a property');

        $mail = (new MailMessage())
            ->subject('Refresh requested: ' . $address)
            ->greeting('A seller has asked for an updated presentation')
            ->line($request->requester_name . ' has requested a refresh of the presentation for ' . $address . '.');

        if ($request->message) {
            $mail->line('Their message: "' . $request->message . '"');
        }
        if ($request->requester_email) {
            $mail->line('Reply-to: ' . $request->requester_email);
        }
        if ($request->requester_phone) {
            $mail->line('Phone: ' . $request->requester_phone);
        }

        return $mail
            ->action('Open refresh requests', route('corex.presentations.refresh-requests.index'))
            ->line('Acknowledge, decline, or issue a refreshed link from the dashboard.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'presentation_refresh_requested',
            'request_id'       => $this->requestId,
            'presentation_id'  => PresentationRefreshRequest::find($this->requestId)?->presentation_id,
        ];
    }
}
