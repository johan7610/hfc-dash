<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_oversight_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 64);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('threshold_hours')->nullable();
            $table->enum('notify_channel', ['email', 'in_app', 'both'])->default('in_app');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'category']);
            $table->index(['agency_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_oversight_preferences');
    }
};
