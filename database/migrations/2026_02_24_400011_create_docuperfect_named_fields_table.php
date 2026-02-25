<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docuperfect_named_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('field_type')->default('text');
            $table->json('default_options')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docuperfect_named_fields');
    }
};
