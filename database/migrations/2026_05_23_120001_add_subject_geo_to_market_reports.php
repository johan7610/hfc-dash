<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3a — extend market_reports with the subject-property metadata that
 * the CMA Info parsers extract from page 1 (scheme name + section + GPS).
 *
 * Spec: Phase 3a build prompt + Presentations V2 §5 (data model).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('market_reports', 'subject_address')) {
                $table->string('subject_address')->nullable()->after('source_town');
            }
            if (!Schema::hasColumn('market_reports', 'subject_scheme_name')) {
                $table->string('subject_scheme_name')->nullable()->after('subject_address');
            }
            if (!Schema::hasColumn('market_reports', 'subject_section_number')) {
                $table->string('subject_section_number', 32)->nullable()->after('subject_scheme_name');
            }
            if (!Schema::hasColumn('market_reports', 'subject_latitude')) {
                $table->decimal('subject_latitude', 10, 7)->nullable()->after('subject_section_number');
            }
            if (!Schema::hasColumn('market_reports', 'subject_longitude')) {
                $table->decimal('subject_longitude', 10, 7)->nullable()->after('subject_latitude');
            }
            if (!Schema::hasColumn('market_reports', 'subject_extent_m2')) {
                $table->unsignedInteger('subject_extent_m2')->nullable()->after('subject_longitude');
            }
            if (!Schema::hasColumn('market_reports', 'radius_metres')) {
                $table->unsignedInteger('radius_metres')->nullable()->after('subject_extent_m2');
            }

            $table->index(['subject_latitude', 'subject_longitude'], 'idx_market_reports_geo');
        });
    }

    public function down(): void
    {
        Schema::table('market_reports', function (Blueprint $table) {
            $table->dropIndex('idx_market_reports_geo');
            foreach ([
                'radius_metres', 'subject_extent_m2',
                'subject_longitude', 'subject_latitude',
                'subject_section_number', 'subject_scheme_name', 'subject_address',
            ] as $col) {
                if (Schema::hasColumn('market_reports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
