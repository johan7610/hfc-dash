<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 3 — AI variant catalogue row.
 *
 * Variants are seeded once via PresentationAiVariantsSeeder. Each variant
 * is a tone + structure choice — direct / warm / confident — that the
 * agent picks at presentation-summary time. The prompt_template is what
 * goes to AI; AiSummaryService prepends a common system prefix and
 * substitutes {facts_block} + {agency_country}.
 */
final class PresentationAiVariant extends Model
{
    protected $table = 'presentation_ai_variants';

    protected $fillable = [
        'key',
        'display_name',
        'description',
        'prompt_template',
        'max_tokens',
        'temperature',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'temperature' => 'decimal:2',
        'max_tokens'  => 'integer',
        'sort_order'  => 'integer',
    ];
}
