<?php

namespace App\Services\CommandCenter;

use App\Models\Agency;
use App\Models\CommandCenter\NotificationEventType;
use App\Models\CommandCenter\UserDashboardSetting;
use App\Models\CommandCenter\UserNotificationPreference;
use App\Models\User;

class NotificationPreferenceService
{
    /**
     * Effective preference for a user + event-type key.
     * Returns an array with: enabled, threshold, channel_in_app, channel_email, channel_push.
     */
    public function effective(User $user, string $key): ?array
    {
        $type = NotificationEventType::where('key', $key)->first();
        if (! $type) {
            return null;
        }

        // Notification preferences are ALWAYS per-user, regardless of agency
        // dashboard_settings_mode. The agency mode only applies to user-level
        // dashboard settings, not to notification preferences.
        $dashboard = UserDashboardSetting::firstOrCreate(
            ['user_id' => $user->id],
            UserDashboardSetting::defaults()
        );

        // Master switches gate everything.
        $masterInApp = (bool) $dashboard->notify_in_app;
        $masterEmail = (bool) $dashboard->notify_email;

        // Adapter rows: read from existing UserDashboardSetting columns.
        if ($type->is_adapter && $type->adapter_column) {
            return $this->resolveAdapter($type, $dashboard, $masterInApp, $masterEmail);
        }

        $pref = UserNotificationPreference::where('user_id', $user->id)
            ->where('notification_event_type_id', $type->id)
            ->first();

        $enabled       = $pref?->enabled       ?? $type->default_enabled;
        $threshold     = $pref?->threshold     ?? $type->default_threshold;
        $channelInApp  = $pref?->channel_in_app  ?? true;
        $channelEmail  = $pref?->channel_email   ?? false;
        $channelPush   = $pref?->channel_push    ?? true;

        return [
            'enabled'        => (bool) $enabled,
            'threshold'      => $threshold,
            'channel_in_app' => $masterInApp && $channelInApp,
            'channel_email'  => $masterEmail && $channelEmail,
            'channel_push'   => $masterInApp && $channelPush, // push gated by in-app master
            'event_type'     => $type,
        ];
    }

    public function shouldNotify(User $user, string $key): bool
    {
        $eff = $this->effective($user, $key);
        if (! $eff) return false;
        if (! $eff['enabled']) return false;
        return $eff['channel_in_app'] || $eff['channel_email'] || $eff['channel_push'];
    }

    public function isAgencyControlled(User $user): bool
    {
        // Notification preferences are always user-editable.
        // Agency dashboard_settings_mode does not apply here — that flag only
        // governs user-level dashboard settings (idle, digest, working hours,
        // etc.), not the notification matrix.
        return false;
    }

    /**
     * Snapshot for the settings UI / API: returns every event type with the
     * user's effective preference resolved.
     */
    public function snapshot(User $user): array
    {
        $types = NotificationEventType::orderBy('pillar')->orderBy('sort_order')->get();
        $userPrefs = UserNotificationPreference::where('user_id', $user->id)->get()
            ->keyBy('notification_event_type_id');
        $dashboard = UserDashboardSetting::firstOrCreate(
            ['user_id' => $user->id],
            UserDashboardSetting::defaults()
        );

        $groups = [];
        foreach ($types as $type) {
            $eff = $this->effective($user, $type->key);
            $groups[$type->pillar] ??= [
                'pillar' => $type->pillar,
                'label'  => ucfirst($type->pillar),
                'items'  => [],
            ];

            $groups[$type->pillar]['items'][] = [
                'key'             => $type->key,
                'label'           => $type->label,
                'description'     => $type->description,
                'group'           => $type->group_label,
                'threshold_unit'  => $type->threshold_unit,
                'threshold'       => $eff['threshold'],
                'threshold_min'   => $type->threshold_min,
                'threshold_max'   => $type->threshold_max,
                'enabled'         => $eff['enabled'],
                'channel_in_app'  => $eff['channel_in_app'],
                'channel_email'   => $eff['channel_email'],
                'channel_push'    => $eff['channel_push'],
                'is_adapter'      => (bool) $type->is_adapter,
            ];
        }

        return [
            'master' => [
                'in_app' => (bool) $dashboard->notify_in_app,
                'email'  => (bool) $dashboard->notify_email,
                'push'   => (bool) $dashboard->notify_in_app, // push follows in_app master
            ],
            'agency_controlled' => $this->isAgencyControlled($user),
            'groups' => array_values($groups),
        ];
    }

    /**
     * Apply an inbound preferences payload (idempotent upsert).
     */
    public function applyUpdates(User $user, array $payload): int
    {
        $saved = 0;
        $master = $payload['master'] ?? null;
        if (is_array($master)) {
            $dashboard = UserDashboardSetting::firstOrCreate(
                ['user_id' => $user->id],
                UserDashboardSetting::defaults()
            );
            $dashboard->fill([
                'notify_in_app' => (bool) ($master['in_app'] ?? $dashboard->notify_in_app),
                'notify_email'  => (bool) ($master['email']  ?? $dashboard->notify_email),
            ])->save();
        }

        foreach ($payload['preferences'] ?? [] as $row) {
            if (empty($row['key'])) continue;
            $type = NotificationEventType::where('key', $row['key'])->first();
            if (! $type) continue;

            // Adapter rows write back to UserDashboardSetting, not to user_notification_preferences.
            if ($type->is_adapter && $type->adapter_column) {
                $this->writeAdapter($user, $type, $row);
                $saved++;
                continue;
            }

            UserNotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'notification_event_type_id' => $type->id],
                [
                    'enabled'        => (bool) ($row['enabled'] ?? true),
                    'threshold'      => $row['threshold'] ?? $type->default_threshold,
                    'channel_in_app' => (bool) ($row['channel_in_app'] ?? true),
                    'channel_email'  => (bool) ($row['channel_email']  ?? false),
                    'channel_push'   => (bool) ($row['channel_push']   ?? true),
                ]
            );
            $saved++;
        }

        return $saved;
    }

    private function resolveAdapter(NotificationEventType $type, $dashboard, bool $masterInApp, bool $masterEmail): array
    {
        // Map adapter_column → enable boolean + threshold int.
        // Some adapter columns are themselves the toggle; others are the threshold.
        $col = $type->adapter_column;
        $enabled = true;
        $threshold = $type->default_threshold;

        // Toggle columns we know about
        $toggleMap = [
            'task_reminder_hours_before' => 'task_due_reminders',
            'event_reminder_hours_before' => null, // always on if master is on
            'lease_reminder_days_before' => 'lease_expiry_reminders',
            'idle_threshold_days' => 'idle_alerts_enabled',
            'overdue_daily_digest' => 'overdue_daily_digest',
            'ffc_reminders' => 'ffc_reminders',
        ];

        $toggleCol = $toggleMap[$col] ?? null;
        if ($toggleCol) {
            $enabled = (bool) $dashboard->{$toggleCol};
        } elseif ($col === 'overdue_daily_digest' || $col === 'ffc_reminders') {
            $enabled = (bool) $dashboard->{$col};
        }

        if ($type->threshold_unit !== 'none' && in_array($col, [
            'task_reminder_hours_before','event_reminder_hours_before',
            'lease_reminder_days_before','idle_threshold_days',
        ], true)) {
            $threshold = (int) $dashboard->{$col};
        }

        return [
            'enabled'        => $enabled,
            'threshold'      => $threshold,
            'channel_in_app' => $masterInApp,
            'channel_email'  => $masterEmail,
            'channel_push'   => $masterInApp,
            'event_type'     => $type,
        ];
    }

    private function writeAdapter(User $user, NotificationEventType $type, array $row): void
    {
        $dashboard = UserDashboardSetting::firstOrCreate(
            ['user_id' => $user->id],
            UserDashboardSetting::defaults()
        );

        $col = $type->adapter_column;
        $toggleMap = [
            'task_reminder_hours_before' => 'task_due_reminders',
            'lease_reminder_days_before' => 'lease_expiry_reminders',
            'idle_threshold_days' => 'idle_alerts_enabled',
        ];
        if ($tCol = ($toggleMap[$col] ?? null)) {
            $dashboard->{$tCol} = (bool) ($row['enabled'] ?? true);
        }

        if (in_array($col, ['overdue_daily_digest', 'ffc_reminders'], true)) {
            $dashboard->{$col} = (bool) ($row['enabled'] ?? true);
        }

        if ($type->threshold_unit !== 'none' && isset($row['threshold']) && in_array($col, [
            'task_reminder_hours_before','event_reminder_hours_before',
            'lease_reminder_days_before','idle_threshold_days',
        ], true)) {
            $dashboard->{$col} = (int) $row['threshold'];
        }

        $dashboard->save();
    }
}
