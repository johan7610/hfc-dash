<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docuperfect_pack_instance_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pack_instance_id');
            $table->unsignedBigInteger('named_field_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->foreign('named_field_id')->references('id')->on('docuperfect_named_fields')->onDelete('cascade');
            $table->unique(['pack_instance_id', 'named_field_id'], 'piv_instance_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docuperfect_pack_instance_values');
    }
};
