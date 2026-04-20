<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('p24_agency_id', 32)->nullable()->after('fic_no');
            $table->string('p24_agency_label', 100)->nullable()->after('p24_agency_id');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('p24_agency_id', 32)->nullable()->after('fic_no');
        });

        // Seed the existing HFC Coastal agency with the value that was previously
        // the single hardcoded P24_EXDEV_AGENCY_ID — so Phase 1 behaviour continues
        // to work after the mapper switches to per-agency resolution.
        DB::table('agencies')
            ->where('slug', 'hfc-coastal')
            ->update([
                'p24_agency_id'    => '31357',
                'p24_agency_label' => 'Home Finders Coastal — HFC1 (test)',
                'updated_at'       => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('p24_agency_id');
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['p24_agency_id', 'p24_agency_label']);
        });
    }
};
