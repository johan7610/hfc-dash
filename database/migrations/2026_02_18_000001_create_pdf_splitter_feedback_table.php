<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_splitter_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('base_name', 160);            // sanitised job name
            $table->unsignedSmallInteger('page_number');
            $table->string('auto_label', 40);            // OCR-assigned label
            $table->string('final_label', 40);           // user-confirmed label
            $table->string('snippet', 200)->default(''); // first ~120 chars of OCR text
            $table->json('scores')->nullable();           // raw keyword scores at classify time
            $table->timestamps();

            $table->index(['final_label', 'auto_label']);
            $table->index('base_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_splitter_feedback');
    }
};
