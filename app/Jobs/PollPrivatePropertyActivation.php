<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\PrivateProperty\PrivatePropertySyndicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollPrivatePropertyActivation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    // Backoff schedule in seconds, applied per attempt index.
    // Total window ≈ 60 minutes — covers PP's typical activation lag.
    private const SCHEDULE = [30, 90, 300, 900, 1800];

    public function __construct(
        public int $propertyId,
        public int $attempt = 0,
    ) {}

    public function handle(PrivatePropertySyndicationService $service): void
    {
        $property = Property::find($this->propertyId);
        if (!$property) {
            return;
        }

        // Already activated, deactivated, errored, or sync no longer enabled — stop polling.
        if (!$property->pp_syndication_enabled
            || $property->pp_syndication_status !== 'submitted'
            || !empty($property->pp_ref)) {
            return;
        }

        try {
            $service->syncActivationStatus($property);
        } catch (\Throwable $e) {
            Log::channel('private_property')->warning(
                "Auto-poll: property #{$this->propertyId} sync failed (attempt {$this->attempt}) — {$e->getMessage()}"
            );
        }

        $property->refresh();

        // Activated — done.
        if ($property->pp_syndication_status === 'active' || !empty($property->pp_ref)) {
            return;
        }

        // Schedule next attempt if any remain.
        $next = $this->attempt + 1;
        if ($next < count(self::SCHEDULE)) {
            self::dispatch($this->propertyId, $next)
                ->delay(now()->addSeconds(self::SCHEDULE[$next]));
        }
    }

    /**
     * Kick off the first poll after a successful submission.
     */
    public static function start(int $propertyId): void
    {
        self::dispatch($propertyId, 0)
            ->delay(now()->addSeconds(self::SCHEDULE[0]));
    }
}
