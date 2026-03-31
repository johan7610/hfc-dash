<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_document_expectations', function (Blueprint $table) {
            $table->id();
            $table->string('property_type', 50)->comment('sale, rental, commercial, vacant_land');
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('required')->default(true);
            $table->unsignedInteger('due_offset_hours')->default(72);
            $table->string('label', 255);
            $table->integer('sort_order')->default(0);
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_document_expectations');
    }
};
