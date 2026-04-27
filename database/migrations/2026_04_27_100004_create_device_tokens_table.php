<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16); // ios|android|web
            $table->string('token', 512);
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'token'], 'device_tokens_user_token_unique');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
