<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_document_library_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('presentation_id');
            $table->unsignedBigInteger('document_library_item_id');
            $table->unsignedBigInteger('attached_by_user_id');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('presentation_id')->references('id')->on('presentations')->onDelete('cascade');
            $table->foreign('document_library_item_id', 'pdli_doc_lib_item_fk')->references('id')->on('document_library_items')->onDelete('cascade');
            $table->foreign('attached_by_user_id', 'pdli_attached_by_fk')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['presentation_id', 'document_library_item_id'], 'pdli_pres_doc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_document_library_items');
    }
};
