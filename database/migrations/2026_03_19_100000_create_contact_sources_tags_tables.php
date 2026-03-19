<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contact Sources (e.g. Property24, Walk-in, Referral)
        Schema::create('contact_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('color', 7)->default('#6366f1');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Contact Tags (e.g. VIP, Hot Lead, Investor)
        Schema::create('contact_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('color', 7)->default('#6366f1');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Pivot: contact ↔ tag (many-to-many)
        Schema::create('contact_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_id', 'contact_tag_id']);
        });

        // Add source FK to contacts
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('contact_source_id')->nullable()->after('contact_type_id')
                  ->constrained('contact_sources')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['contact_source_id']);
            $table->dropColumn('contact_source_id');
        });

        Schema::dropIfExists('contact_tag');
        Schema::dropIfExists('contact_tags');
        Schema::dropIfExists('contact_sources');
    }
};
