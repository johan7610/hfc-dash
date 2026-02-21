<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_listings', function (Blueprint $table) {
            $table->id();
            $table->string('source_site', 100);           // e.g. www.property24.com
            $table->string('portal_listing_id', 50);      // e.g. 116815341
            $table->text('canonical_url')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedBigInteger('last_capture_id')->nullable();
            $table->json('current_fields_json')->nullable();
            $table->timestamps();

            $table->unique(['source_site', 'portal_listing_id'], 'portal_listings_site_id_unique');
            $table->index('last_capture_id');

            $table->foreign('last_capture_id')
                  ->references('id')->on('portal_captures')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_listings');
    }
};
