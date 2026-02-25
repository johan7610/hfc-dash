<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_filing_register', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('agent_id');
            $table->enum('document_type', ['OA', 'EA', 'Other'])->default('OA');
            $table->string('file_reference');
            $table->string('sequence_number');
            $table->string('property_address');
            $table->string('seller_name')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('captured_by');
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('captured_by')->references('id')->on('users')->onDelete('cascade');

            $table->index('branch_id');
            $table->index('agent_id');
            $table->index('property_address');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_filing_register');
    }
};
