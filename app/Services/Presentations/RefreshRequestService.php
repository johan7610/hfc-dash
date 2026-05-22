<?php

declare(strict_types=1);

namespace App\Services\Presentations;

use App\Models\Presentation;
use App\Models\PresentationRefreshRequest;
use App\Models\PresentationSnapshotLink;
use App\Models\User;
use App\Notifications\Presentations\RefreshDeclinedNotification;
use App\Notifications\Presentations\RefreshRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 7 — refresh request lifecycle.
 *
 * Single entry point for all four state changes the spec calls out:
 *   submitRequest()     — seller asks (public route)
 *   acknowledge()       — agent saw it (lightweight tap on the inbox)
 *   resolveWithRefresh()— agent issues a new link; this one auto-supersedes
 *   decline()           — agent says "no", with reason
 *
 * The service is the only place that writes to presentation_refresh_requests
 * and the only place that toggles the supersede columns on
 * presentation_snapshot_links — everything else reads.
 *
 * Notifications are dispatched OUTSIDE the DB transaction. A failed mail send
 * must never abort the state change.
 */
final class RefreshRequestService
{
    /** Per-link rate limit window in seconds. */
    public const PER_LINK_WINDOW_SECONDS = 600;

    /** Max requests allowed from a single fingerprint in the window. */
    public const PER_FINGERPRINT_MAX = 2;

    public function __construct(
        private readonly SnapshotLinkService $links,
    ) {}

    /**
     * Seller-side: record a refresh ask.
     *
     * @param array{
     *   requester_name: string,
     *   requester_email?: string|null,
     *   requester_phone?: string|null,
     *   message?: string|null,
     *   fingerprint_hash?: string|null,
     *   ip_masked?: string|null,
     *   user_agent?: string|null,
     * } $input
     *
     * @throws RefreshNotAllowedException When the link is in a non-requestable state.
     * @throws RefreshRateLimitException When this fingerprint or link has hit the limit.
     */
    public function submitRequest(PresentationSnapshotLink $link, array $input): PresentationRefreshRequest
    {
        if ($link->isRevoked()) {
            throw new RefreshNotAllowedException('This link has been revoked.');
        }
        if ($link->isSuperseded()) {
            throw new RefreshNotAllowedException('This link has already been replaced by a newer version.');
        }

        $fingerprint = $input['fingerprint_hash'] ?? null;
        $this->guardRateLimit($link, $fingerprint);

        $request = DB::transaction(function () use ($link, $input, $fingerprint) {
            $row = PresentationRefreshRequest::create([
                'agency_id'            => $link->agency_id,
                'presentation_id'      => $link->presentation_id,
                'snapshot_link_id'     => $link->id,
                'recipient_contact_id' => $link->recipient_contact_id,
                'requester_name'       => trim((string) $input['requester_name']),
                'requester_email'      => $input['requester_email'] ?? null,
                'requester_phone'      => $input['requester_phone'] ?? null,
                'message'              => $input['message'] ?? null,
                'fingerprint_hash'     => $fingerprint,
                'ip_masked'            => $input['ip_masked'] ?? null,
                'user_agent'           => $input['user_agent'] ?? null,
                'status'               => PresentationRefreshRequest::STATUS_PENDING,
            ]);

            // Mirror the latest ask onto the link itself so the agent's
            // "Share Links" table can show a banner without joining.
            $link->forceFill([
                'refresh_requested_at'      => $row->created_at,
                'refresh_requested_by_name' => $row->requester_name,
                'refresh_requested_message' => $row->message,
                'refresh_request_count'     => (int) $link->refresh_request_count + 1,
            ])->save();

            return $row;
        });

        $this->dispatchRequestedNotification($request, $link);

        return $request;
    }

    /**
     * Agent-side: mark "I've seen it" without issuing a new link.
     * Idempotent — calling on an already-acknowledged request is a no-op.
     */
    public function acknowledge(PresentationRefreshRequest $request, User $by): PresentationRefreshRequest
    {
        if ($request->status !== PresentationRefreshRequest::STATUS_PENDING) {
            return $request;
        }

        $request->forceFill([
            'status'                  => PresentationRefreshRequest::STATUS_ACKNOWLEDGED,
            'acknowledged_at'         => now(),
            'acknowledged_by_user_id' => $by->id,
        ])->save();

        // Also stamp the link so the agent UI can show "seen X ago".
        $link = $request->link;
        if ($link && $link->refresh_acknowledged_at === null) {
            $link->forceFill([
                'refresh_acknowledged_at'         => $request->acknowledged_at,
                'refresh_acknowledged_by_user_id' => $by->id,
            ])->save();
        }

        return $request->refresh();
    }

    /**
     * Agent-side: issue a new snapshot link as the refresh.
     *
     * The new link inherits the source link's recipient + mode + (optionally)
     * version_id. The source link is marked superseded so its /p/{token} URL
     * auto-redirects (or 410s, depending on viewer policy) to the new one.
     *
     * @param array{
     *   version_id?: int|null,
     *   keep_old_link_active?: bool,
     *   resolution_note?: string|null,
     *   notify_recipient?: bool,
     * } $options
     */
    public function resolveWithRefresh(
        PresentationRefreshRequest $request,
        User $by,
        array $options = [],
    ): PresentationSnapshotLink {
        if ($request->isResolved()) {
            // Already done — return the link we issued.
            return $request->resultingLink ?? throw new RefreshNotAllowedException('Request is resolved but resulting link is missing.');
        }
        if ($request->isDeclined() || $request->status === PresentationRefreshRequest::STATUS_CANCELLED) {
            throw new RefreshNotAllowedException('Cannot resolve a declined or cancelled request.');
        }

        $source = $request->link;
        if (!$source) {
            throw new RefreshNotAllowedException('Source link no longer exists.');
        }

        $presentation = Presentation::find($request->presentation_id);
        if (!$presentation) {
            throw new RefreshNotAllowedException('Source presentation no longer exists.');
        }

        $keepOldActive = (bool) ($options['keep_old_link_active'] ?? false);

        return DB::transaction(function () use ($request, $source, $presentation, $by, $options, $keepOldActive) {
            $newLink = $this->links->createLink($presentation, [
                'version_id'           => $options['version_id'] ?? $source->presentation_version_id,
                'mode'                 => $source->mode,
                'recipient_contact_id' => $source->recipient_contact_id,
                'recipient_label'      => $source->recipient_label,
                'created_by_user_id'   => $by->id,
            ]);

            $sourceUpdate = [
                'refresh_resulted_in_link_id' => $newLink->id,
            ];
            if (!$keepOldActive) {
                $sourceUpdate['superseded_by_link_id'] = $newLink->id;
                $sourceUpdate['superseded_at']         = now();
            }
            $source->forceFill($sourceUpdate)->save();

            $request->forceFill([
                'status'              => PresentationRefreshRequest::STATUS_RESOLVED,
                'resolved_at'         => now(),
                'resolved_by_user_id' => $by->id,
                'resulting_link_id'   => $newLink->id,
                'resolution_note'     => $options['resolution_note'] ?? null,
            ])->save();

            return $newLink;
        });
    }

    /**
     * Agent-side: explicitly decline. Optionally emails the requester (if we
     * have their email and they consented — the spec calls for sending unless
     * the agent opts out).
     */
    public function decline(
        PresentationRefreshRequest $request,
        User $by,
        string $reason,
        bool $notifyRequester = true,
    ): PresentationRefreshRequest {
        if ($request->isDeclined()) return $request;
        if ($request->isResolved()) {
            throw new RefreshNotAllowedException('Cannot decline a resolved request.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Decline reason is required.');
        }

        $request->forceFill([
            'status'              => PresentationRefreshRequest::STATUS_DECLINED,
            'declined_at'         => now(),
            'declined_by_user_id' => $by->id,
            'decline_reason'      => $reason,
        ])->save();

        if ($notifyRequester && $request->requester_email) {
            $this->dispatchDeclinedNotification($request);
        }

        return $request->refresh();
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private function guardRateLimit(PresentationSnapshotLink $link, ?string $fingerprint): void
    {
        $windowAgo = now()->subSeconds(self::PER_LINK_WINDOW_SECONDS);

        $linkRecent = PresentationRefreshRequest::where('snapshot_link_id', $link->id)
            ->where('created_at', '>=', $windowAgo)
            ->count();
        if ($linkRecent >= 3) {
            throw new RefreshRateLimitException(
                'Too many refresh requests for this link in a short window.',
                self::PER_LINK_WINDOW_SECONDS,
            );
        }

        if ($fingerprint !== null) {
            $fpRecent = PresentationRefreshRequest::where('snapshot_link_id', $link->id)
                ->where('fingerprint_hash', $fingerprint)
                ->where('created_at', '>=', $windowAgo)
                ->count();
            if ($fpRecent >= self::PER_FINGERPRINT_MAX) {
                throw new RefreshRateLimitException(
                    'You have already requested a refresh recently. Please give the agent time to respond.',
                    self::PER_LINK_WINDOW_SECONDS,
                );
            }
        }
    }

    private function dispatchRequestedNotification(PresentationRefreshRequest $request, PresentationSnapshotLink $link): void
    {
        try {
            $agent = $link->creator;
            if ($agent) {
                $agent->notify(new RefreshRequestedNotification($request->id));
            }
        } catch (\Throwable $e) {
            Log::warning('refresh.request.notify_failed', [
                'request_id' => $request->id,
                'link_id'    => $link->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function dispatchDeclinedNotification(PresentationRefreshRequest $request): void
    {
        try {
            \Illuminate\Support\Facades\Notification::route('mail', $request->requester_email)
                ->notify(new RefreshDeclinedNotification($request->id));
        } catch (\Throwable $e) {
            Log::warning('refresh.decline.notify_failed', [
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
