<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presentations V2 Phase 2 — agency-scoped coverage thresholds.
 *
 * Agency settings live as columns on the `agencies` table (existing pattern —
 * see `whatsapp_launch_mode_agent`, `ai_monthly_budget_zar` etc.). Adding
 * threshold + default-window columns so each agency tunes its own coverage
 * gate without code change.
 *
 * Spec: .ai/specs/presentations.md §5.6
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            if (!Schema::hasColumn('agencies', 'presentations_coverage_rich_threshold')) {
                $table->unsignedSmallInteger('presentations_coverage_rich_threshold')
                    ->default(6)
                    ->after('require_external_access_authorization');
            }
            if (!Schema::hasColumn('agencies', 'presentations_coverage_moderate_threshold')) {
                $table->unsignedSmallInteger('presentations_coverage_moderate_threshold')
                    ->default(3)
                    ->after('presentations_coverage_rich_threshold');
            }
            if (!Schema::hasColumn('agencies', 'presentations_coverage_thin_threshold')) {
                $table->unsignedSmallInteger('presentations_coverage_thin_threshold')
                    ->default(1)
                    ->after('presentations_coverage_moderate_threshold');
            }
            if (!Schema::hasColumn('agencies', 'presentations_default_period_months')) {
                $table->unsignedSmallInteger('presentations_default_period_months')
                    ->default(12)
                    ->after('presentations_coverage_thin_threshold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            foreach ([
                'presentations_default_period_months',
                'presentations_coverage_thin_threshold',
                'presentations_coverage_moderate_threshold',
                'presentations_coverage_rich_threshold',
            ] as $col) {
                if (Schema::hasColumn('agencies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
