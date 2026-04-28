<?php

namespace App\Console\Commands\CommandCenter;

use App\Models\Property;
use App\Models\User;
use App\Services\CommandCenter\NotificationDispatcher;
use App\Services\CommandCenter\NotificationPreferenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ScanPropertyNotifications extends Command
{
    protected $signature = 'notifications:scan-properties';
    protected $description = 'Scan properties and emit pillar notifications based on user preferences.';

    public function handle(NotificationPreferenceService $prefs, NotificationDispatcher $dispatcher): int
    {
        $hasDocs = Schema::hasTable('property_documents');

        Property::query()
            ->whereNotNull('agent_id')
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['sold','withdrawn','expired']);
            })
            ->chunkById(200, function ($props) use ($prefs, $dispatcher, $hasDocs) {
                foreach ($props as $property) {
                    $agent = User::find($property->agent_id);
                    if (! $agent) continue;

                    // property.documents_missing
                    $eff = $prefs->effective($agent, 'property.documents_missing');
                    if ($eff && $eff['enabled'] && $eff['threshold']) {
                        $ageHours = optional($property->created_at)->diffInHours(now()) ?? 0;
                        if ($ageHours >= (int) $eff['threshold']) {
                            $hasAny = $hasDocs
                                ? \DB::table('property_documents')->where('property_id', $property->id)->exists()
                                : false;
                            if (! $hasAny) {
                                $dispatcher->fire($agent, 'property.documents_missing', $property, [
                                    'title' => trim(($property->address ?? 'Property') . ' — documents missing'),
                                    'body'  => "Listed {$ageHours}h ago, no documents on file.",
                                    'subject_label' => $property->address ?? "Property #{$property->id}",
                                    'action_url' => "/properties/{$property->id}",
                                    'severity' => 'warning',
                                    'threshold_hit_at' => now()->startOfHour(),
                                ]);
                            }
                        }
                    }

                    // property.mandate_expiring
                    if (($property->mandate_expires_at ?? null)) {
                        $eff2 = $prefs->effective($agent, 'property.mandate_expiring');
                        if ($eff2 && $eff2['enabled'] && $eff2['threshold']) {
                            $daysOut = now()->diffInDays($property->mandate_expires_at, false);
                            if ($daysOut >= 0 && $daysOut <= (int) $eff2['threshold']) {
                                $dispatcher->fire($agent, 'property.mandate_expiring', $property, [
                                    'title' => ($property->address ?? 'Property') . " — mandate expires in {$daysOut} days",
                                    'body'  => "Mandate expiring on " . $property->mandate_expires_at->format('Y-m-d'),
                                    'subject_label' => $property->address ?? "Property #{$property->id}",
                                    'action_url' => "/properties/{$property->id}",
                                    'severity' => $daysOut <= 3 ? 'overdue' : 'warning',
                                    'threshold_hit_at' => $property->mandate_expires_at->copy()->startOfDay(),
                                ]);
                            }
                        }
                    }
                }
            });

        return self::SUCCESS;
    }
}
