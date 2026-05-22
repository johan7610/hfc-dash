<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3j B2 — references to SG documents discovered for a property.
 *
 * One row per (property, sg_document_number, page). Storage path is
 * populated only when the TIF has actually been downloaded; rows can sit
 * with storage_path=null until the agent clicks Save to Drive.
 *
 * sha256 enables dedup — if two queries return the same TIF URL we don't
 * download it twice.
 *
 * UNIQUE(property_id, sg_document_number, sg_page_number) keeps the table
 * idempotent across repeat searches: re-searching SG returns the same
 * rows, not duplicates.
 *
 * Explicit short FK names (psgd_*_fk) — Laravel's auto-generated names
 * overflow MySQL's 64-char cap on `property_sg_documents`.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('property_sg_documents')) {
            return;
        }

        Schema::create('property_sg_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties', 'id', 'psgd_property_fk')
                ->cascadeOnDelete();
            $table->foreignId('agency_id')
                ->constrained('agencies', 'id', 'psgd_agency_fk')
                ->cascadeOnDelete();

            $table->string('sg_document_number', 50);
            $table->unsignedSmallInteger('sg_page_number')->default(1);
            $table->enum('sg_doc_type', [
                'DIAGRAM', 'GENERAL_PLAN', 'SERVITUDE', 'TITLE_DEED', 'OTHER',
            ])->default('OTHER');

            $table->string('sg_source_url', 500);
            $table->string('storage_path', 500)->nullable();
            $table->unsignedInteger('file_size_bytes')->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->string('sha256', 64)->nullable();

            $table->boolean('is_saved')->default(false);
            $table->timestamp('saved_at')->nullable();
            $table->foreignId('saved_by_user_id')->nullable()
                ->constrained('users', 'id', 'psgd_saver_fk')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'sg_document_number', 'sg_page_number'], 'psgd_prop_doc_page_uq');
            $table->index(['property_id', 'sg_doc_type'], 'psgd_prop_type_idx');
            $table->index('sha256', 'psgd_sha_idx');
            $table->index(['agency_id', 'is_saved'], 'psgd_agency_saved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_sg_documents');
    }
};
