<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0)->comment('0-100');
            $table->string('grade', 20)->default('attention')->comment('excellent, good, attention, critical');
            $table->json('factors')->nullable()->comment('Breakdown of each factor contribution');
            $table->dateTime('last_calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_health_scores');
    }
};
