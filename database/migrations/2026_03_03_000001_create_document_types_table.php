<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed existing hardcoded types
        DB::table('document_types')->insert([
            ['key' => 'suburb_stats',   'label' => 'Suburb Stats',   'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'suburb_sales',   'label' => 'Suburb Sales',   'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vicinity_sales', 'label' => 'Vicinity Sales', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'cma',            'label' => 'CMA',            'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'market_article', 'label' => 'Market Article', 'sort_order' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'market_report',  'label' => 'Market Report',  'sort_order' => 6, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'other',          'label' => 'Other',          'sort_order' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
