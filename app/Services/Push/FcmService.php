<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

/**
 * FCM v1 push transport.
 *
 * Soft-discovered by App\Services\CommandCenter\NotificationDispatcher::sendPush()
 * via class_exists. Channel gating (channel_push + master.push) is already
 * applied upstream by NotificationPreferenceService::effective().
 */
class FcmService
{
    public function __construct(private Messaging $messaging) {}

    /**
     * @param  string[]                                               $tokens
     * @param  array{notification: array{title: string, body: string}, data: array<string, string>}  $payload
     */
    public function send(array $tokens, array $payload): void
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if (empty($tokens)) return;

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create(
                $payload['notification']['title'] ?? '',
                $payload['notification']['body']  ?? '',
            ))
            ->withData(array_map('strval', $payload['data'] ?? []));

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);
        } catch (\Throwable $e) {
            Log::warning('FCM sendMulticast failed', ['error' => $e->getMessage()]);
            return;
        }

        // Prune tokens the Firebase service rejected as unregistered/invalid.
        $stale = array_merge($report->unknownTokens(), $report->invalidTokens());
        if (! empty($stale)) {
            DeviceToken::whereIn('token', $stale)->delete();
        }
    }
}
