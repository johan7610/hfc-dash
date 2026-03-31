<?php

namespace App\Notifications;

use App\Models\CommandCenter\CommandTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskDueReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected CommandTask $task
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
        $dueLabel = $this->task->due_date?->format('d M Y H:i') ?? 'soon';

        return (new MailMessage)
            ->subject("Task Due Reminder: {$this->task->title}")
            ->greeting("Hi {$notifiable->name},")
            ->line("You have a task due **{$dueLabel}**:")
            ->line("**{$this->task->title}**")
            ->when($this->task->property, function ($msg) {
                return $msg->line("Property: {$this->task->property->buildDisplayAddress()}");
            })
            ->action('View Dashboard', url('/corex'))
            ->line('Please complete this task before the deadline.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'task_due_reminder',
            'title'       => "Task due soon: {$this->task->title}",
            'body'        => $this->task->due_date
                ? "Due {$this->task->due_date->diffForHumans()}"
                : 'Due soon',
            'action_url'  => '/corex/command-center/tasks',
            'icon'        => 'clock',
            'task_id'     => $this->task->id,
            'property_id' => $this->task->property_id,
        ];
    }
}
