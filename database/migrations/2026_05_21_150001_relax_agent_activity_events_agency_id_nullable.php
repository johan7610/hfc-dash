<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MIC Phase B1 follow-up — relax `agent_activity_events.agency_id` to nullable.
 *
 * Phase A1 declared agency_id NOT NULL, but legitimate global / system events
 * have no agency: `AINarrativeFailedFallback` for cross-agency briefs,
 * `AINarrativeGenerated` for shared-pool narratives, future cross-agency
 * audit signals. The LogAgentActivity listener was silently dropping these
 * rows (SQLSTATE 1048).
 *
 * Drop FK → relax column → re-add FK with nullOnDelete so a deleted agency
 * doesn't take the historical event log with it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agent_activity_events', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
        });

        Schema::table('agent_activity_events', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_id')->nullable()->change();
        });

        Schema::table('agent_activity_events', function (Blueprint $table) {
            $table->foreign('agency_id')
                  ->references('id')->on('agencies')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $nullCount = DB::table('agent_activity_events')->whereNull('agency_id')->count();
        if ($nullCount > 0) {
            throw new \RuntimeException(
                "Cannot roll back: {$nullCount} agent_activity_events row(s) have NULL agency_id. "
                . 'Delete or backfill them before re-attempting this rollback.'
            );
        }

        Schema::table('agent_activity_events', function (Blueprint $table) {
            $table->dropForeign(['agency_id']);
        });

        Schema::table('agent_activity_events', function (Blueprint $table) {
            $table->unsignedBigInteger('agency_id')->nullable(false)->change();
        });

        Schema::table('agent_activity_events', function (Blueprint $table) {
            $table->foreign('agency_id')
                  ->references('id')->on('agencies')
                  ->cascadeOnDelete();
        });
    }
};
