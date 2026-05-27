<?php

declare(strict_types=1);

use App\Models\Docuperfect\DocumentCondition;
use App\Models\Docuperfect\SignatureTemplate;
use App\Services\Docuperfect\LegacyOtherConditionsBridge;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * E-Sign V3 Phase 1B.5 — one-shot backfill.
 *
 * For every existing signature_templates row that has a non-empty
 * other_conditions_text but ZERO document_conditions rows, run the bridge
 * once so the legacy textarea content becomes visible to recipient signing
 * surfaces.
 *
 * Idempotent: re-running this migration is safe because the bridge
 * itself skips rows that already carry the bridge signature.
 *
 * Spec: .ai/specs/esign-v3-complete-spec.md §22 Phase 1B.5
 */
return new class extends Migration
{
    public function up(): void
    {
        $bridge = new LegacyOtherConditionsBridge();
        $bridged = 0;
        $skipped = 0;

        $docs = SignatureTemplate::query()
            ->whereNotNull('other_conditions_text')
            ->where('other_conditions_text', '!=', '')
            ->get(['id', 'document_id', 'other_conditions_text', 'created_by', 'deleted_at']);

        foreach ($docs as $doc) {
            // Skip docs that already have any structured condition (means an
            // earlier process already populated them — never overwrite).
            $hasStructured = DocumentCondition::query()
                ->where('signature_template_id', $doc->id)
                ->exists();
            if ($hasStructured) {
                $skipped++;
                continue;
            }

            try {
                $written = $bridge->syncToStructuredRows($doc);
                if ($written > 0) {
                    $bridged++;
                }
            } catch (\Throwable $e) {
                Log::warning('Phase 1B.5 backfill: bridge failed for signature_template ' . $doc->id, [
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        Log::info('Phase 1B.5 backfill complete', [
            'bridged' => $bridged,
            'skipped' => $skipped,
            'scanned' => $docs->count(),
        ]);
    }

    public function down(): void
    {
        // We can't reliably distinguish bridge-written rows from rows the
        // bridge may write again in the future, so a true reversal would
        // potentially delete legitimate data. Migration is intentionally
        // one-way; manual cleanup is required if the backfill needs reverting.
    }
};
