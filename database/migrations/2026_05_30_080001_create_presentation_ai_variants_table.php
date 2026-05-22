<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Part B1 — AI summary variant catalogue.
 *
 * Agency-shared today; agency-scoping can be added later by adding a
 * nullable agency_id (NULL = global). Each variant is a stored prompt
 * template + behavioural knobs (max_tokens / temperature) consumed by
 * AiSummaryService when generating a summary.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('presentation_ai_variants', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('key', 50)->unique();
            $table->string('display_name', 100);
            $table->string('description', 300);
            $table->text('prompt_template');
            $table->unsignedSmallInteger('max_tokens')->default(800);
            $table->decimal('temperature', 3, 2)->default(0.50);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_ai_variants');
    }
};
