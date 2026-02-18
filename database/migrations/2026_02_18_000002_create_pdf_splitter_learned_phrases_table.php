<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_splitter_learned_phrases', function (Blueprint $table) {
            $table->id();
            $table->string('bucket', 40);           // doc-type key (e.g. 'mandate')
            $table->string('phrase', 120);          // learned 2-word phrase (lowercase)
            $table->unsignedSmallInteger('weight')->default(1); // score boost applied when phrase matches
            $table->unsignedInteger('hits')->default(0);        // times seen in user overrides
            $table->boolean('enabled')->default(false);         // only active when hits >= threshold
            $table->timestamps();

            $table->unique(['bucket', 'phrase']);   // one row per bucket+phrase combination
            $table->index(['bucket', 'enabled']);   // fast lookup in classifyPage()
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_splitter_learned_phrases');
    }
};
