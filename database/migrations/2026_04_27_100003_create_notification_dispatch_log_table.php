<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_dispatch_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_event_type_id')->constrained('notification_event_types')->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->timestamp('threshold_hit_at');
            $table->timestamp('dispatched_at')->nullable();
            $table->string('channel', 16); // in_app | email | push
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'notification_event_type_id', 'subject_type', 'subject_id'], 'ndl_user_event_subject');
            $table->index('threshold_hit_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatch_log');
    }
};
