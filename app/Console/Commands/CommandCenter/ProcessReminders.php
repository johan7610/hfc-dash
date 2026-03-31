<?php

namespace App\Console\Commands\CommandCenter;

use App\Models\CommandCenter\CalendarEvent;
use App\Models\CommandCenter\CommandTask;
use App\Models\CommandCenter\UserDashboardSetting;
use App\Models\User;
use App\Notifications\EventDueReminderNotification;
use App\Notifications\TaskDueReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessReminders extends Command
{
    protected $signature = 'command-center:reminders';
    protected $description = 'Process calendar event and task reminders — sends notifications before due dates';

    public function handle(): int
    {
        $tasksSent  = 0;
        $eventsSent = 0;
        $overdue    = 0;

        // ── 1. Mark overdue events ──
        $overdueCount = CalendarEvent::where('status', 'pending')
            ->where('event_date', '<', now())
            ->update(['status' => 'overdue']);
        $overdue += $overdueCount;

        // ── 2. Send task due reminders ──
        // Get all users with task reminder enabled
        User::where('is_active', 1)->chunk(50, function ($users) use (&$tasksSent) {
            foreach ($users as $user) {
                try {
                    $settings = UserDashboardSetting::getEffective($user);

                    if (!$settings->task_due_reminders || !$settings->notify_in_app) {
                        continue;
                    }

                    $hoursBefore = $settings->task_reminder_hours_before ?? 4;
                    $windowStart = now();
                    $windowEnd   = now()->addHours($hoursBefore);

                    // Find tasks due within the reminder window that haven't been reminded yet
                    $tasks = CommandTask::forUser($user->id)
                        ->whereNotIn('status', ['done', 'dismissed'])
                        ->where('send_reminder', true)
                        ->whereNotNull('due_date')
                        ->whereBetween('due_date', [$windowStart, $windowEnd])
                        ->whereNull('metadata->reminder_sent')
                        ->get();

                    foreach ($tasks as $task) {
                        $user->notify(new TaskDueReminderNotification($task));

                        // Mark as reminded so we don't send again
                        $meta = $task->metadata ?? [];
                        $meta['reminder_sent'] = now()->toIso8601String();
                        $task->update(['metadata' => $meta]);

                        $tasksSent++;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Task reminder failed for user #{$user->id}: {$e->getMessage()}");
                }
            }
        });

        // ── 3. Send event due reminders ──
        User::where('is_active', 1)->chunk(50, function ($users) use (&$eventsSent) {
            foreach ($users as $user) {
                try {
                    $settings = UserDashboardSetting::getEffective($user);

                    if (!$settings->notify_in_app) {
                        continue;
                    }

                    $hoursBefore = $settings->event_reminder_hours_before ?? 24;
                    $windowStart = now();
                    $windowEnd   = now()->addHours($hoursBefore);

                    $events = CalendarEvent::forUser($user->id)
                        ->where('status', 'pending')
                        ->where('send_reminder', true)
                        ->whereBetween('event_date', [$windowStart, $windowEnd])
                        ->whereNull('metadata->reminder_sent')
                        ->get();

                    foreach ($events as $event) {
                        $user->notify(new EventDueReminderNotification($event));

                        $meta = $event->metadata ?? [];
                        $meta['reminder_sent'] = now()->toIso8601String();
                        $event->update(['metadata' => $meta]);

                        $eventsSent++;
                    }
                } catch (\Throwable $e) {
                    Log::warning("Event reminder failed for user #{$user->id}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Done. Tasks: {$tasksSent} reminded. Events: {$eventsSent} reminded. Overdue: {$overdue} marked.");

        return self::SUCCESS;
    }
}
