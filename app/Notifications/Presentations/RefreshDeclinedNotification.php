<?php

declare(strict_types=1);

namespace App\Notifications\Presentations;

use App\Models\PresentationRefreshRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Phase 7 — sent to the requester when the agent declines.
 *
 * Mail-only (the requester is not necessarily a CoreX user, so no database
 * channel). Dispatched via Notification::route('mail', $email).
 */
final class RefreshDeclinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $requestId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = PresentationRefreshRequest::with(['presentation.property', 'decliner'])->find($this->requestId);
        if (!$request) {
            return (new MailMessage())->subject('Update on your refresh request');
        }

        $address = $request->presentation?->property_address
            ?? ($request->presentation?->property?->address ?? 'the property');
        $agentName = $request->decliner?->name ?? 'Your agent';

        $mail = (new MailMessage())
            ->subject('Update on your refresh request')
            ->greeting('Hi ' . $request->requester_name . ',')
            ->line('Thanks for asking for an updated presentation for ' . $address . '.')
            ->line($agentName . ' has reviewed your request and provided the following response:')
            ->line('"' . trim((string) $request->decline_reason) . '"');

        return $mail->line('If you have further questions please reply directly to this email.');
    }
}
