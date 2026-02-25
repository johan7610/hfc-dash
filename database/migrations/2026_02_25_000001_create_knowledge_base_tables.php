<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('knowledge_categories')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type', 20);
            $table->unsignedInteger('file_size')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedInteger('page_count')->nullable();
            $table->enum('status', ['processing', 'ready', 'error'])->default('processing');
            $table->text('error_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_ellie_enabled')->default(true);
            $table->string('version', 50)->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            $table->index(['category_id', 'is_active']);
            $table->index(['status']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('knowledge_documents')->onDelete('cascade');
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->string('section_title')->nullable();
            $table->unsignedInteger('page_number')->nullable();
            $table->unsignedInteger('char_count')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['document_id', 'chunk_index']);
        });

        // PostgreSQL full-text search
        if (config('database.default') === 'pgsql') {
            DB::statement("ALTER TABLE knowledge_chunks ADD COLUMN search_vector tsvector");
            DB::statement("CREATE INDEX knowledge_chunks_search_idx ON knowledge_chunks USING GIN(search_vector)");
            DB::statement("
                CREATE OR REPLACE FUNCTION knowledge_chunks_search_update() RETURNS trigger AS \$\$
                BEGIN
                    NEW.search_vector := to_tsvector('english', COALESCE(NEW.section_title, '') || ' ' || NEW.content);
                    RETURN NEW;
                END
                \$\$ LANGUAGE plpgsql;
            ");
            DB::statement("
                CREATE TRIGGER knowledge_chunks_search_trigger
                BEFORE INSERT OR UPDATE OF content, section_title
                ON knowledge_chunks
                FOR EACH ROW EXECUTE FUNCTION knowledge_chunks_search_update();
            ");
        }

        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('ai_messages')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('rating', ['up', 'down']);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('ai_daily_briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('briefing_date');
            $table->text('content');
            $table->json('data_snapshot')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'briefing_date']);
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_daily_briefings');
        Schema::dropIfExists('ai_feedback');
        if (config('database.default') === 'pgsql') {
            DB::statement("DROP TRIGGER IF EXISTS knowledge_chunks_search_trigger ON knowledge_chunks");
            DB::statement("DROP FUNCTION IF EXISTS knowledge_chunks_search_update()");
        }
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
        Schema::dropIfExists('knowledge_categories');
    }
};
