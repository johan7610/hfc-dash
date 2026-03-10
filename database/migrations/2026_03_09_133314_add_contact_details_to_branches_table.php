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
        Schema::table('branches', function (Blueprint $table) {
            $table->string('trading_name')->nullable()->after('agency_id');
            $table->string('tagline')->nullable()->after('trading_name');
            $table->string('address')->nullable()->after('tagline');
            $table->string('phone')->nullable()->after('address');
            $table->string('fax')->nullable()->after('phone');
            $table->string('email')->nullable()->after('fax');
            $table->string('reg_no')->nullable()->after('email');
            $table->string('vat_no')->nullable()->after('reg_no');
            $table->string('ffc_no')->nullable()->after('vat_no');
            $table->string('fic_no')->nullable()->after('ffc_no');
            $table->string('logo_path')->nullable()->after('fic_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'trading_name',
                'tagline',
                'address',
                'phone',
                'fax',
                'email',
                'reg_no',
                'vat_no',
                'ffc_no',
                'fic_no',
                'logo_path',
            ]);
        });
    }
};
