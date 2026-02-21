<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_listing_observations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portal_listing_id');
            $table->unsignedBigInteger('capture_id');
            $table->timestamp('observed_at')->nullable();
            $table->json('observed_fields_json')->nullable();
            $table->json('changed_fields_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['portal_listing_id', 'observed_at'], 'portal_obs_listing_observed_idx');
            $table->index('capture_id');

            $table->foreign('portal_listing_id')
                  ->references('id')->on('portal_listings')
                  ->onDelete('cascade');

            $table->foreign('capture_id')
                  ->references('id')->on('portal_captures')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_listing_observations');
    }
};
