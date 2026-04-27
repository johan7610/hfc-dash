<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_event_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('pillar', 32);
            $table->string('group_label')->nullable();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('default_enabled')->default(true);
            $table->string('threshold_unit', 16)->default('none'); // hours|days|none
            $table->unsignedInteger('default_threshold')->nullable();
            $table->unsignedInteger('threshold_min')->nullable();
            $table->unsignedInteger('threshold_max')->nullable();
            $table->boolean('supports_in_app')->default(true);
            $table->boolean('supports_email')->default(true);
            $table->boolean('supports_push')->default(true);
            $table->boolean('is_adapter')->default(false); // maps to legacy UserDashboardSetting column
            $table->string('adapter_column')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pillar', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_types');
    }
};
