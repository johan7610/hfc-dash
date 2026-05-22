<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3i B1+B2 — close the deals ↔ property/presentation gap.
 *
 * Added columns:
 *   - property_id (FK properties, nullable) — the canonical link from a
 *     deal back to the physical property the transaction concerned.
 *   - presentation_id (FK presentations, nullable) — the presentation
 *     that led to this deal (when the deal originated from a presentation
 *     flow). Forward link complements existing presentations.deal_id.
 *   - sale_price (bigint unsigned) — Rands, no cents. New canonical
 *     numeric field. property_value (decimal 12,2) stays for legacy
 *     reads; sale_price is what new code writes.
 *   - sale_date (date) — alias of registration_date for analytics queries
 *     that should be agnostic to lifecycle naming. registration_date
 *     stays as the agency-tracker authoritative field.
 *   - link_source / link_confidence / link_reviewed_at / link_reviewed_by_user_id
 *     — provenance for the FK match. See DealPropertyLinkService.
 *
 * Explicit short FK names (deals_*_fk) — Laravel's auto-generated names
 * overflow MySQL's 64-char identifier cap on long table names.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (!Schema::hasColumn('deals', 'property_id')) {
                $table->foreignId('property_id')->nullable()
                    ->after('agency_id')
                    ->constrained('properties', 'id', 'deals_property_fk')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('deals', 'presentation_id')) {
                $table->foreignId('presentation_id')->nullable()
                    ->after('property_id')
                    ->constrained('presentations', 'id', 'deals_presentation_fk')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('deals', 'sale_price')) {
                $table->unsignedBigInteger('sale_price')->nullable()
                    ->after('property_value')
                    ->comment('Phase 3i canonical sale price in Rands (no cents). Mirrors property_value.');
            }
            if (!Schema::hasColumn('deals', 'sale_date')) {
                $table->date('sale_date')->nullable()
                    ->after('registration_date')
                    ->comment('Phase 3i analytics alias of registration_date.');
            }
            if (!Schema::hasColumn('deals', 'link_source')) {
                $table->enum('link_source', [
                    'manual',
                    'auto_address_match',
                    'auto_address_date_match',
                    'presentation_link',
                    'admin_review',
                ])->nullable()->after('sale_date');
            }
            if (!Schema::hasColumn('deals', 'link_confidence')) {
                $table->enum('link_confidence', ['exact', 'high', 'medium', 'low'])
                    ->nullable()->after('link_source');
            }
            if (!Schema::hasColumn('deals', 'link_reviewed_at')) {
                $table->timestamp('link_reviewed_at')->nullable()->after('link_confidence');
            }
            if (!Schema::hasColumn('deals', 'link_reviewed_by_user_id')) {
                $table->foreignId('link_reviewed_by_user_id')->nullable()
                    ->after('link_reviewed_at')
                    ->constrained('users', 'id', 'deals_link_reviewer_fk')
                    ->nullOnDelete();
            }
        });

        Schema::table('deals', function (Blueprint $table) {
            // Composite index for "HFC sales history on this property over time"
            // queries (property show page panel + map pins).
            $idx = collect(Schema::getIndexes('deals'))->pluck('name')->all();
            if (!in_array('deals_property_sale_date_idx', $idx, true)) {
                $table->index(['property_id', 'sale_date'], 'deals_property_sale_date_idx');
            }
            if (!in_array('deals_presentation_idx', $idx, true)) {
                $table->index('presentation_id', 'deals_presentation_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $idx = collect(Schema::getIndexes('deals'))->pluck('name')->all();
            if (in_array('deals_presentation_idx', $idx, true)) {
                $table->dropIndex('deals_presentation_idx');
            }
            if (in_array('deals_property_sale_date_idx', $idx, true)) {
                $table->dropIndex('deals_property_sale_date_idx');
            }

            foreach ([
                'deals_link_reviewer_fk',
                'deals_presentation_fk',
                'deals_property_fk',
            ] as $fk) {
                try { $table->dropForeign($fk); } catch (\Throwable $e) { /* tolerant */ }
            }

            foreach ([
                'link_reviewed_by_user_id', 'link_reviewed_at', 'link_confidence',
                'link_source', 'sale_date', 'sale_price',
                'presentation_id', 'property_id',
            ] as $col) {
                if (Schema::hasColumn('deals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
