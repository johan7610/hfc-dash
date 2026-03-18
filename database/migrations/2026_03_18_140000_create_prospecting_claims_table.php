<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospecting_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id');
            $table->unsignedBigInteger('prospecting_listing_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status', 30)->default('claimed');
            $table->text('notes')->nullable();
            $table->timestamp('claimed_at');
            $table->timestamp('feedback_at')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['prospecting_listing_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index(['agency_id', 'status']);
            $table->foreign('prospecting_listing_id')
                ->references('id')->on('prospecting_listings');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospecting_claims');
    }
};
