<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('oversight_nudges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('category', 64);
            $table->text('message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['agency_id', 'to_user_id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oversight_nudges');
    }
};
