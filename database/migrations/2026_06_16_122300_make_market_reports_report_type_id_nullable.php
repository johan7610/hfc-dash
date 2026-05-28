<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parser-dispatch fix Phase 2 dependency — make `market_reports.report_type_id`
 * nullable so re-parse can clear it.
 *
 * Why: re-parse must reset the parser stamp so `ParseMarketReportJob` takes
 * its detect() branch and re-resolves the correct parser. The branch was
 * already designed for null (`$report->report_type_id ? resolveByKey : detect`),
 * but the column was created NOT NULL so the null path was unreachable in
 * practice. With this migration the existing branch becomes live and the
 * "stuck on Other / Unknown" reports can be unstuck by hitting Re-parse.
 *
 * Production impact: nullable column accepts existing rows untouched (no
 * backfill). The job re-writes the type_id immediately after detection, so
 * the null window is bounded to a single parse-job run.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_reports', function (Blueprint $table) {
            $table->unsignedSmallInteger('report_type_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Backfill any nulls to the 'other' type before flipping NOT NULL
        // back on — without this the change() would fail mid-flight if any
        // unresolved re-parses left a row with a null type_id.
        $otherTypeId = \Illuminate\Support\Facades\DB::table('market_report_types')
            ->where('key', 'other')
            ->value('id');
        if ($otherTypeId) {
            \Illuminate\Support\Facades\DB::table('market_reports')
                ->whereNull('report_type_id')
                ->update(['report_type_id' => $otherTypeId]);
        }

        Schema::table('market_reports', function (Blueprint $table) {
            $table->unsignedSmallInteger('report_type_id')->nullable(false)->change();
        });
    }
};
