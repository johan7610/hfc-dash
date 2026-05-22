<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 9a hardening — POPIA 90-day retention on presentation_snapshot_views.
 *
 * Background: every public visit to /p/{token} logs a row with fingerprint
 * hash + masked IP + user_agent. The analytical value of these rows decays
 * fast — the agent needs the first-view, flag, and engagement counts; the
 * raw per-view rows older than 90 days don't add anything beyond their
 * aggregated parents (PresentationSnapshotLink.view_count).
 *
 * Rows that are CURRENTLY part of a fingerprint-mismatch flag (link is
 * flagged_at != null AND last_flag_notified_at is within 30 days) are
 * preserved — they may still be relevant to ongoing investigations.
 *
 * Runs daily 03:15 (after the Phase 8 lock job, before queue-stress hours).
 */
final class PurgeOldSnapshotViewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const RETENTION_DAYS = 90;

    public function handle(): void
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        // Subquery: links whose flagged_at is still "fresh" (last flag notice
        // within 30 days) — keep their view rows for forensic review.
        $protectedLinkIds = DB::table('presentation_snapshot_links')
            ->whereNotNull('flagged_at')
            ->where(function ($q) {
                $q->whereNull('last_flag_notified_at')
                  ->orWhere('last_flag_notified_at', '>=', now()->subDays(30));
            })
            ->pluck('id');

        $deleted = DB::table('presentation_snapshot_views')
            ->where('viewed_at', '<', $cutoff)
            ->whereNotIn('snapshot_link_id', $protectedLinkIds)
            ->delete();

        if ($deleted > 0) {
            Log::info('snapshot_views.purged', [
                'rows'    => $deleted,
                'cutoff'  => $cutoff->toIso8601String(),
                'kept'    => $protectedLinkIds->count(),
            ]);
        }
    }
}
