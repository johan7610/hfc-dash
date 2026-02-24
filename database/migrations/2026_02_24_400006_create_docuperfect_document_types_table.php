<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docuperfect_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default types
        $types = ['Mandates', 'OTPs', 'Addendums', 'Condition Reports', 'FICA', 'Rental Agreements', 'Other'];
        foreach ($types as $i => $name) {
            DB::table('docuperfect_document_types')->insert([
                'name' => $name,
                'sort_order' => $i * 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('docuperfect_document_types');
    }
};
