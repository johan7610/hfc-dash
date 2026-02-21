<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_library_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('doc_type')->default('other');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->json('tags_json')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('uploaded_by_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('doc_type');
            $table->index('uploaded_by_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_library_items');
    }
};
