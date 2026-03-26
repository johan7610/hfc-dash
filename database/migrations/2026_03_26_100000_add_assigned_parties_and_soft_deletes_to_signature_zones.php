<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_zones', function (Blueprint $table) {
            if (!Schema::hasColumn('signature_zones', 'assigned_parties')) {
                $table->json('assigned_parties')->nullable()->after('party_role');
            }
            if (!Schema::hasColumn('signature_zones', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('signature_zones', function (Blueprint $table) {
            if (Schema::hasColumn('signature_zones', 'assigned_parties')) {
                $table->dropColumn('assigned_parties');
            }
            if (Schema::hasColumn('signature_zones', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
