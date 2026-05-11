<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PillarEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $eventKey,
        public string $pillar,
        public string $title,
        public string $body,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?string $subjectLabel = null,
        public ?string $actionUrl = null,
        public string $severity = 'info', // info | warning | overdue
        public array $payload = [],
        public array $channels = ['database'], // database, mail, fcm
    ) {}

    public function via(object $notifiable): array
    {
        // Channels are decided by the dispatcher based on user preference.
        return $this->channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_key'    => $this->eventKey,
            'pillar'       => $this->pillar,
            'title'        => $this->title,
            'body'         => $this->body,
            'subject_type' => $this->subjectType,
            'subject_id'   => $this->subjectId,
            'subject_label'=> $this->subjectLabel,
            'action_url'   => $this->actionUrl,
            'severity'     => $this->severity,
            'payload'      => $this->payload,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = (new MailMessage)
            ->subject($this->title)
            ->line($this->body);
        if ($this->actionUrl) {
            $msg->action('Open in CoreX', url($this->actionUrl));
        }
        return $msg;
    }

    /**
     * FCM payload — used when the FCM channel is enabled and a channel package is installed.
     * Keeping it here means the dispatcher can read this shape without hard-depending on a package.
     */
    public function toFcmPayload(): array
    {
        return [
            'notification' => [
                'title' => $this->title,
                'body'  => $this->body,
            ],
            'data' => [
                'event_key'       => $this->eventKey,
                'pillar'          => $this->pillar,
                'subject_type'    => (string) $this->subjectType,
                'subject_id'      => (string) $this->subjectId,
                'action_url'      => (string) $this->actionUrl,
                'severity'        => $this->severity,
                'notification_id' => (string) $this->id,
            ],
        ];
    }
}
