<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3a — `scheme_owners`: extracted owner roll from CMA Info
 * "Sectional Title Scheme Owners List" reports.
 *
 * Spec: Phase 3a build prompt §1.3.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('scheme_owners', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agency_id')->constrained('agencies');
            $table->foreignId('market_report_id')->constrained('market_reports')->cascadeOnDelete();

            $table->string('scheme_name');
            $table->string('scheme_ss_number', 32)->nullable();
            $table->string('section_number', 32)->nullable();
            $table->string('flat_number', 32)->nullable();

            $table->string('owner_name');

            $table->unsignedInteger('extent_m2')->nullable();
            $table->string('property_type', 64)->nullable();

            $table->decimal('latitude', 10, 7)->nullable()
                  ->comment('Populated later via cross-link to scheme GPS.');
            $table->decimal('longitude', 10, 7)->nullable();

            $table->foreignId('contact_id')->nullable()
                  ->comment('Set when the owner is matched to a CoreX Contact (Phase later).');
            $table->timestamp('matched_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Per-agency dedupe — same scheme/section/owner is one row.
            $table->unique(
                ['agency_id', 'scheme_name', 'section_number', 'owner_name'],
                'uq_scheme_owners_agency_scheme_section_owner'
            );

            $table->index('scheme_name', 'idx_scheme_owners_scheme');
            $table->index('owner_name', 'idx_scheme_owners_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_owners');
    }
};
