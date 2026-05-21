<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Sign V3 Phase 1B.6 (FIX 4).
 *
 * Optional informational link between a DocumentCondition and an existing
 * numbered clause in the source document. Distinct from
 * `overrides_clause_ref` (which marks a strikethrough replacement) —
 * `relates_to_clause_ref` is non-overriding context the recipient adds
 * when filing a brand-new condition that references an existing clause.
 *
 * Spec: .ai/specs/esign-v3-complete-spec.md §7.5.4 (Phase 1B.6 revision).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_conditions', function (Blueprint $table) {
            $table->string('relates_to_clause_ref')->nullable()->after('overrides_clause_ref');
            $table->index(['signature_template_id', 'relates_to_clause_ref'], 'doc_cond_relates_to_idx');
        });
    }

    public function down(): void
    {
        Schema::table('document_conditions', function (Blueprint $table) {
            $table->dropIndex('doc_cond_relates_to_idx');
            $table->dropColumn('relates_to_clause_ref');
        });
    }
};
