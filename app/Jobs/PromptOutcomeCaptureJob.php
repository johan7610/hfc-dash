<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\Presentation\PresentationOutcomePrompted;
use App\Models\Agency;
use App\Models\Presentation;
use App\Models\PresentationOutcomePrompt;
use App\Models\User;
use App\Notifications\Presentations\OutcomeCapturePromptNotification;
use App\Services\Presentations\PresentationOutcomeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8 — daily nudge dispatcher.
 *
 * For each agency, finds presentations older than $daysOld days with no
 * outcome row, dispatches OutcomeCapturePromptNotification to the listing
 * agent, logs to presentation_outcome_prompts (for cooldown + audit), and
 * emits the PresentationOutcomePrompted domain event.
 *
 * Cooldown: only one prompt per presentation per 30 days. Even if the
 * outcome still hasn't been recorded after 60+ days, the agent gets
 * pinged at most once per 30-day window.
 */
final class PromptOutcomeCaptureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $daysOld = 30,
        public readonly int $cooldownDays = 30,
    ) {}

    public function handle(PresentationOutcomeService $svc): void
    {
        $agencies = Agency::query()->pluck('id');

        foreach ($agencies as $agencyId) {
            $open = $svc->findOpenOutcomes((int) $agencyId, $this->daysOld);

            foreach ($open as $presentation) {
                $this->processOne($presentation);
            }
        }
    }

    private function processOne(Presentation $presentation): void
    {
        $agentId = (int) $presentation->created_by_user_id;
        if (!$agentId) {
            return;
        }

        // Cooldown check — has this presentation been prompted in the last
        // $cooldownDays days for any user?
        $recent = PresentationOutcomePrompt::where('presentation_id', $presentation->id)
            ->where('prompted_at', '>=', now()->subDays($this->cooldownDays))
            ->exists();
        if ($recent) {
            return;
        }

        $agent = User::find($agentId);
        if (!$agent) {
            return;
        }

        $days = (int) round((float) $presentation->created_at?->diffInDays(now()));

        try {
            $agent->notify(new OutcomeCapturePromptNotification(
                presentationId:    (int) $presentation->id,
                daysSinceCreation: $days,
            ));
        } catch (\Throwable $e) {
            Log::warning('outcome.prompt.notify_failed', [
                'presentation_id' => $presentation->id,
                'agent_id'        => $agent->id,
                'error'           => $e->getMessage(),
            ]);
            return;
        }

        PresentationOutcomePrompt::create([
            'presentation_id'  => $presentation->id,
            'agency_id'        => $presentation->agency_id,
            'prompted_user_id' => $agent->id,
            'prompted_at'      => now(),
            'channel'          => 'mail',
        ]);

        try {
            event(new PresentationOutcomePrompted(
                presentationId:    (int) $presentation->id,
                promptedUserId:    (int) $agent->id,
                agencyIdValue:     (int) $presentation->agency_id,
                daysSinceCreation: $days,
            ));
        } catch (\Throwable $e) {
            Log::warning('outcome.prompt.event_failed', [
                'presentation_id' => $presentation->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
