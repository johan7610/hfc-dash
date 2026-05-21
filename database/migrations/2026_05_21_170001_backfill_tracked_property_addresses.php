<?php

declare(strict_types=1);

use App\Models\Prospecting\TrackedProperty;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MIC Phase C1 — backfill `tracked_property_addresses` from existing TPs.
 *
 * One-shot data migration that materialises one is_primary=true row per
 * tracked_property, mirroring the TP's current primary address-field cache
 * (street_number / street_name / suburb / town / province / postal_code /
 * lat / lon / unit_number / complex_name). source_type is derived from the
 * first entry of source_chain; confidence defaults by source with a downgrade
 * to 'low' when street_name is empty (suburb-only TPs).
 *
 * Bypasses Eloquent entirely (DB::table()->insert()) so:
 *   - TrackedPropertyAddressObserver does NOT fire (the parent's cache is
 *     already correct — re-syncing 4,912 times is wasted work)
 *   - No TrackedPropertyAddressAdded domain events are dispatched (would
 *     write ~10k extra rows across domain_event_log + agent_activity_events)
 *
 * Idempotent: re-running counts rows-with-existing-primary as skipped.
 *
 * Spec: .ai/specs/mic-complete-spec.md §3.2.1 + §7 (Address management).
 */
return new class extends Migration {
    private const BACKFILL_NOTE = 'Backfilled from tracked_properties on 2026-05-21';

    public function up(): void
    {
        $count = 0;
        $errors = 0;
        $skipped = 0;
        $started = microtime(true);

        TrackedProperty::withTrashed()
            ->orderBy('id')
            ->chunk(200, function ($chunk) use (&$count, &$errors, &$skipped) {
                foreach ($chunk as $tp) {
                    try {
                        $hasPrimary = DB::table('tracked_property_addresses')
                            ->where('tracked_property_id', $tp->id)
                            ->where('is_primary', true)
                            ->whereNull('deleted_at')
                            ->exists();
                        if ($hasPrimary) {
                            $skipped++;
                            continue;
                        }

                        $sourceType = $this->deriveSourceType($tp);
                        $sourceRef  = $this->deriveSourceRef($tp);
                        $firstSeen  = $tp->first_seen_at ?? $tp->created_at ?? now();
                        $lastSeen   = $tp->last_enriched_at ?? $tp->updated_at ?? now();

                        DB::table('tracked_property_addresses')->insert([
                            'agency_id'           => $tp->agency_id,
                            'tracked_property_id' => $tp->id,
                            'street_number'       => $tp->street_number,
                            'street_name'         => $tp->street_name,
                            'unit_number'         => $tp->unit_number,
                            'complex_name'        => $tp->complex_name,
                            'suburb'              => $tp->suburb,
                            'suburb_normalised'   => $tp->suburb_normalised,
                            'town'                => $tp->town,
                            'province'            => $tp->province,
                            'postal_code'         => $tp->postal_code,
                            'latitude'            => $tp->latitude,
                            'longitude'           => $tp->longitude,
                            'source_type'         => $sourceType,
                            'source_ref'          => $sourceRef,
                            'confidence'          => $this->deriveConfidence($sourceType, $tp),
                            'is_primary'          => true,
                            'verified_by_user_id' => null,
                            'verified_at'         => null,
                            'notes'               => self::BACKFILL_NOTE,
                            'first_seen_at'       => $firstSeen,
                            'last_seen_at'        => $lastSeen,
                            'created_at'          => $firstSeen,
                            'updated_at'          => $lastSeen,
                        ]);
                        $count++;
                    } catch (\Throwable $e) {
                        $errors++;
                        Log::warning("Phase C1 backfill failed for TP {$tp->id}: " . $e->getMessage());
                    }
                }
            });

        $elapsed = round(microtime(true) - $started, 2);

        if (PHP_SAPI === 'cli') {
            fwrite(STDOUT, sprintf(
                "    → backfilled=%d skipped=%d errors=%d elapsed=%.2fs" . PHP_EOL,
                $count, $skipped, $errors, $elapsed
            ));
        }
    }

    public function down(): void
    {
        // Hard-delete only rows tagged by this backfill — they are migration
        // artefacts, not user data. The "no hard deletes" rule does not apply
        // to a roll-back of the very migration that wrote the rows.
        $deleted = DB::table('tracked_property_addresses')
            ->where('notes', self::BACKFILL_NOTE)
            ->delete();

        if (PHP_SAPI === 'cli') {
            fwrite(STDOUT, "    → rolled back {$deleted} backfilled rows" . PHP_EOL);
        }
    }

    /**
     * source_type from the FIRST entry of source_chain (the original creator).
     * Falls back to 'unknown' when source_chain is null/empty or malformed.
     * Recognised values per spec §3.2.1:
     *   p24 | pp | chrome_capture | cmainfo | manual_agent | manual_admin | deeds_office
     */
    private function deriveSourceType(TrackedProperty $tp): string
    {
        $chain = $tp->source_chain;
        if (!is_array($chain) || empty($chain)) {
            return 'unknown';
        }
        $first = $chain[0] ?? null;
        if (!is_array($first)) {
            return 'unknown';
        }
        // Tolerate both keys — older rows may use 'source' instead of 'type'.
        $type = $first['type'] ?? $first['source'] ?? null;
        return is_string($type) && $type !== '' ? $type : 'unknown';
    }

    /**
     * source_ref from the FIRST source_chain entry (e.g. P24-117025017, PP-T5391969,
     * pres_5). Null when no chain entry or no ref key.
     */
    private function deriveSourceRef(TrackedProperty $tp): ?string
    {
        $chain = $tp->source_chain;
        if (!is_array($chain) || empty($chain)) {
            return null;
        }
        $first = $chain[0] ?? null;
        if (!is_array($first)) {
            return null;
        }
        $ref = $first['ref'] ?? $first['source_ref'] ?? null;
        return is_string($ref) && $ref !== '' ? $ref : null;
    }

    /**
     * Default confidence per source_type, with a downgrade to 'low' when the
     * TP has no street_name (suburb-only address — the silent-killer case).
     */
    private function deriveConfidence(string $sourceType, TrackedProperty $tp): string
    {
        if (empty($tp->street_name)) {
            return 'low';
        }
        return match ($sourceType) {
            'manual_agent', 'manual_admin' => 'verified',
            'deeds_office', 'cmainfo'      => 'high',
            'chrome_capture', 'pp'         => 'medium',
            'p24'                          => 'low',
            default                        => 'low',
        };
    }
};
