<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docuperfect_pack_branches', function (Blueprint $table) {
            $table->unsignedBigInteger('pack_id');
            $table->unsignedBigInteger('branch_id');

            $table->foreign('pack_id')->references('id')->on('docuperfect_packs')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->unique(['pack_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docuperfect_pack_branches');
    }
};
