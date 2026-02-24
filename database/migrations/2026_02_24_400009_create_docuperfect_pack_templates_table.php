<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docuperfect_pack_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pack_id');
            $table->unsignedBigInteger('template_id');
            $table->integer('sort_order')->default(0);

            $table->foreign('pack_id')->references('id')->on('docuperfect_packs')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('docuperfect_templates')->onDelete('cascade');
            $table->unique(['pack_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docuperfect_pack_templates');
    }
};
