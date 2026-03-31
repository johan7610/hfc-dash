<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('period_type', 20)->comment('daily, weekly, monthly');
            $table->date('period_start');
            $table->date('period_end');

            $table->unsignedInteger('tasks_completed')->default(0);
            $table->unsignedInteger('tasks_overdue')->default(0);
            $table->unsignedInteger('tasks_total')->default(0);
            $table->unsignedInteger('properties_attended')->default(0);
            $table->unsignedInteger('properties_total')->default(0);
            $table->unsignedInteger('documents_uploaded')->default(0);
            $table->unsignedInteger('fica_complete')->default(0);
            $table->unsignedInteger('fica_total')->default(0);
            $table->decimal('avg_response_hours', 8, 2)->default(0);
            $table->unsignedInteger('deals_progressed')->default(0);
            $table->unsignedInteger('events_completed')->default(0);
            $table->unsignedInteger('events_total')->default(0);
            $table->unsignedInteger('activity_points')->default(0);
            $table->unsignedTinyInteger('overall_score')->default(0)->comment('0-100');

            $table->dateTime('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_scorecards');
    }
};
