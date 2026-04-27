<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_event_type_id')->constrained('notification_event_types')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('threshold')->nullable();
            $table->boolean('channel_in_app')->default(true);
            $table->boolean('channel_email')->default(false);
            $table->boolean('channel_push')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'notification_event_type_id'], 'unp_user_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
