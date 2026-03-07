<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flows', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedInteger('current_step')->default(1);
            $table->json('step_data')->nullable();
            $table->enum('status', ['active', 'completed', 'abandoned', 'draft'])->default('active');
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->foreign('template_id')->references('id')->on('docuperfect_templates')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flows');
    }
};
