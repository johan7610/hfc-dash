<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->boolean('price_on_application')->default(false)->after('price');
            $table->boolean('has_deposit')->default(false)->after('price_on_application');
            $table->string('lease_period', 100)->nullable()->after('has_deposit'); // e.g. "12 Months"
            $table->decimal('price_per_day', 12, 2)->nullable()->after('lease_period');
            $table->decimal('price_per_week', 12, 2)->nullable()->after('price_per_day');
            $table->decimal('price_per_year', 12, 2)->nullable()->after('price_per_week');
            $table->string('lease_type', 100)->nullable()->after('price_per_year'); // e.g. "N Triple Net", "Gross"
            $table->decimal('gross_price', 12, 2)->nullable()->after('lease_type');
            $table->decimal('net_price', 12, 2)->nullable()->after('gross_price');
            $table->decimal('yard_price', 12, 2)->nullable()->after('net_price');
            $table->string('primary_price_display', 50)->default('monthly')->after('yard_price'); // monthly|daily|weekly|yearly
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'price_on_application', 'has_deposit', 'lease_period',
                'price_per_day', 'price_per_week', 'price_per_year',
                'lease_type', 'gross_price', 'net_price', 'yard_price',
                'primary_price_display',
            ]);
        });
    }
};
