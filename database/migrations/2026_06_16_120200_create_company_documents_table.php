<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9c-3 — admin-managed company documents (privacy policy, T&Cs,
 * complaints procedure, AML statement, code of conduct, …).
 *
 * Token-based public URL pattern mirrors `presentation_snapshot_links`
 * (Phase 4 of Presentations V2). Public route `/legal/{token}` renders
 * the document on a clean branded page; 404 when not published.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $t->string('document_type', 64);
            $t->string('title', 200);
            $t->longText('content')->nullable();
            $t->string('content_format', 16)->default('markdown'); // markdown | html
            $t->string('public_token', 64)->unique();
            $t->boolean('is_published')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->foreignId('last_updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['agency_id', 'document_type', 'deleted_at'], 'company_documents_agency_type_unique');
            $t->index(['agency_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documents');
    }
};
