<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospecting_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id')->index();
            $table->unsignedBigInteger('captured_by_user_id')->index();
            $table->enum('portal_source', ['p24', 'pp']);
            $table->string('portal_ref', 50);
            $table->string('portal_url', 500);
            $table->string('address', 255);
            $table->string('suburb', 100)->index();
            $table->string('district', 100)->nullable();
            $table->integer('price')->index();
            $table->smallInteger('bedrooms')->nullable();
            $table->smallInteger('bathrooms')->nullable();
            $table->smallInteger('garages')->nullable();
            $table->decimal('property_size_m2', 10, 2)->nullable();
            $table->decimal('erf_size_m2', 10, 2)->nullable();
            $table->string('property_type', 50)->nullable()->index();
            $table->string('agent_name', 100)->nullable();
            $table->string('agency_name', 100)->nullable();
            $table->string('thumbnail_path', 255)->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->dateTime('price_changed_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['agency_id', 'portal_source', 'portal_ref']);

            $table->foreign('agency_id')->references('id')->on('agencies')->onDelete('cascade');
            $table->foreign('captured_by_user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('prospecting_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prospecting_listing_id')->index();
            $table->integer('old_price');
            $table->integer('new_price');
            $table->dateTime('changed_at');
            $table->timestamps();

            $table->foreign('prospecting_listing_id')
                ->references('id')
                ->on('prospecting_listings')
                ->onDelete('cascade');
        });

        Schema::create('prospecting_searches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('portal_source', ['p24', 'pp']);
            $table->text('search_url');
            $table->string('search_description', 255);
            $table->integer('total_results');
            $table->integer('pages_captured');
            $table->integer('listing_count');
            $table->dateTime('captured_at');
            $table->timestamps();

            $table->foreign('agency_id')->references('id')->on('agencies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospecting_searches');
        Schema::dropIfExists('prospecting_price_history');
        Schema::dropIfExists('prospecting_listings');
    }
};
