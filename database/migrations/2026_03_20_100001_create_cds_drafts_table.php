<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cds_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->string('template_name');
            $table->json('cds_json');
            $table->json('tags')->nullable();
            $table->json('mappings')->nullable();
            $table->longText('tagged_html')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('source_template_id')->nullable();
            $table->string('status')->default('draft'); // draft, saved
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cds_drafts');
    }
};
