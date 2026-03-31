<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('automation_rules');
            $table->string('trigger_model_type', 120);
            $table->unsignedBigInteger('trigger_model_id');
            $table->string('action_type', 50);
            $table->string('action_result_type', 120)->nullable();
            $table->unsignedBigInteger('action_result_id')->nullable();
            $table->dateTime('executed_at');
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_log');
    }
};
