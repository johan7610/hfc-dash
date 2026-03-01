<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('worksheets', function (Blueprint $table) {
            $table->decimal('commission_percent_admin', 5, 2)->nullable()->after('commission_percent');
            $table->boolean('commission_percent_locked')->default(false)->after('commission_percent_admin');
        });
    }

    public function down(): void
    {
        Schema::table('worksheets', function (Blueprint $table) {
            $table->dropColumn(['commission_percent_admin', 'commission_percent_locked']);
        });
    }
};
