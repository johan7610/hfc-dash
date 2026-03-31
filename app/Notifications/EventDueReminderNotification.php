<?php

namespace App\Notifications;

use App\Models\CommandCenter\CalendarEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventDueReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected CalendarEvent $event
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $settings = \App\Models\CommandCenter\UserDashboardSetting::getEffective($notifiable);
        if ($settings->notify_email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dateLabel = $this->event->event_date->format('d M Y H:i');

        return (new MailMessage)
            ->subject("Event Reminder: {$this->event->title}")
            ->greeting("Hi {$notifiable->name},")
            ->line("You have an upcoming event on **{$dateLabel}**:")
            ->line("**{$this->event->title}**")
            ->when($this->event->property, function ($msg) {
                return $msg->line("Property: {$this->event->property->buildDisplayAddress()}");
            })
            ->action('View Calendar', url('/corex/command-center/calendar'))
            ->line('Please prepare for this event.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'event_due_reminder',
            'title'       => "Upcoming: {$this->event->title}",
            'body'        => $this->event->event_date->diffForHumans(),
            'action_url'  => '/corex/command-center/calendar',
            'icon'        => 'calendar',
            'event_id'    => $this->event->id,
            'property_id' => $this->event->property_id,
        ];
    }
}
