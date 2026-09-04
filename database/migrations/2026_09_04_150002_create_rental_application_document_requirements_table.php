<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_application_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->enum('employment_type', [
                'permanently_employed', 'business_owner_personal_account', 'business_owner_business_account',
            ]);
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // AT-392 spec — "no agency row present ⇒ V8 defaults apply in memory" only
            // holds if a saved row can never silently duplicate itself.
            $table->unique(['agency_id', 'employment_type', 'document_type_id'], 'rental_app_doc_req_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_application_document_requirements');
    }
};
