<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docuperfect_field_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('context', 500)->index();
            $table->string('claude_suggested_key');
            $table->string('claude_suggested_label');
            $table->string('user_corrected_key');
            $table->string('user_corrected_label');
            $table->string('document_type')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docuperfect_field_corrections');
    }
};
