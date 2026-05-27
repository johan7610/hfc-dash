<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3j B1 — SG search context on properties.
 *
 * properties.erf_number already exists (Phase 1). We add the smaller
 * supporting fields the SG search needs and a cache-control timestamp:
 *
 *   erf_portion          — usually '0', but sectional/subdivided land carries a real portion
 *   sg_province          — friendly name override; ordinarily derived from suburb via the gazetteer
 *   sg_rural_urban       — 'urban' default; mostly relevant for vacant agricultural
 *   sg_farm_name         — rural-only override (the SG farm-name search field)
 *   sg_last_searched_at  — last time we hit the SG service for this property
 *
 * sg_province deliberately stores the FRIENDLY name ("Kwa-Zulu Natal") not
 * the numeric ID. The query builder maps it to the numeric office=N id at
 * call time. Storing the friendly name keeps the DB self-describing.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'erf_portion')) {
                $table->string('erf_portion', 20)->nullable()->default('0')->after('erf_number');
            }
            if (!Schema::hasColumn('properties', 'sg_province')) {
                $table->string('sg_province', 30)->nullable()->after('erf_portion');
            }
            if (!Schema::hasColumn('properties', 'sg_rural_urban')) {
                $table->enum('sg_rural_urban', ['rural', 'urban'])->default('urban')->after('sg_province');
            }
            if (!Schema::hasColumn('properties', 'sg_farm_name')) {
                $table->string('sg_farm_name', 200)->nullable()->after('sg_rural_urban');
            }
            if (!Schema::hasColumn('properties', 'sg_last_searched_at')) {
                $table->timestamp('sg_last_searched_at')->nullable()->after('sg_farm_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            foreach (['sg_last_searched_at', 'sg_farm_name', 'sg_rural_urban', 'sg_province', 'erf_portion'] as $col) {
                if (Schema::hasColumn('properties', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
