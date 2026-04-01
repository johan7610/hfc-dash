<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_type_property_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->foreignId('property_type_id')->constrained('property_setting_items')->cascadeOnDelete();
            $table->unique(['document_type_id', 'property_type_id'], 'dt_pt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_type_property_type');
    }
};
