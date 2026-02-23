<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p24_suburbs', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Display name: "Shelly Beach"
            $table->string('slug')->unique();                // URL slug: "shelly-beach"
            $table->unsignedInteger('p24_id')->nullable();   // P24 suburb numeric ID
            $table->string('region')->default('kzn-south-coast');
            $table->json('surrounding_ids')->nullable();     // Array of nearby suburb P24 IDs
            $table->boolean('confirmed')->default(false);    // Whether the P24 ID is verified
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p24_suburbs');
    }
};
