<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PresentationAiVariant;
use Illuminate\Database\Seeder;

/**
 * Phase 3 Part C — initial 3 AI variants.
 *
 *   direct_v1     — straight-to-the-point analysis for analytical sellers
 *   warm_v1       — partner-with-you tone for first-timers / emotional sellers
 *   confident_v1  — authoritative advisor voice for trust-led pitches
 *
 * Idempotent: re-running the seeder updates existing rows (matched by key)
 * with the latest copy. Existing presentations that reference the variant
 * are unaffected.
 */
final class PresentationAiVariantsSeeder extends Seeder
{
    public function run(): void
    {
        $variants = [
            [
                'key'             => 'direct_v1',
                'display_name'    => 'Direct',
                'description'     => 'Straight-to-the-point analysis. Best for analytical sellers who want the facts without fluff.',
                'max_tokens'      => 800,
                'temperature'     => 0.40,
                'sort_order'      => 10,
                'prompt_template' => <<<'TXT'
TONE: Direct, evidence-based, professional. Lead with the most important fact. Short sentences. No filler.

STRUCTURE:
1. One-sentence opening that states the pricing position vs market
2. Key supporting data (suburb median, comp count, active competition)
3. Time-to-sell estimate with the reasoning shown
4. Inflow risk if applicable
5. One-sentence close inviting a pricing discussion

FACTS:
{facts_block}

Write the summary now.
TXT,
            ],
            [
                'key'             => 'warm_v1',
                'display_name'    => 'Warm & Conversational',
                'description'     => 'Approachable, partner-with-you tone. Best for first-time sellers or emotionally-attached homeowners.',
                'max_tokens'      => 800,
                'temperature'     => 0.55,
                'sort_order'      => 20,
                'prompt_template' => <<<'TXT'
TONE: Warm, partnership-oriented, encouraging while honest. Acknowledge the seller's stake. Conversational sentence rhythm.

STRUCTURE:
1. Brief opening acknowledging this is their decision
2. The market context (suburb activity, comp signals)
3. Their property's position with empathy for any gap to market
4. What that means in months-to-sell + competition
5. Close suggesting a conversation about strategy

FACTS:
{facts_block}

Write the summary now.
TXT,
            ],
            [
                'key'             => 'confident_v1',
                'display_name'    => 'Confident Advisor',
                'description'     => 'Authoritative agent voice. Best when the agent has strong market position and wants to lead with expertise.',
                'max_tokens'      => 800,
                'temperature'     => 0.45,
                'sort_order'      => 30,
                'prompt_template' => <<<'TXT'
TONE: Confident, advisory, expert-positioned. Statements not questions. The agent owns the analysis. Inspires trust through clarity.

STRUCTURE:
1. Position statement — what the data tells us
2. Three or four key data points presented as a clear case
3. The recommendation framing (without giving a specific price — the data is the recommendation)
4. Time + competition outlook
5. Close that positions the agent as the right person to execute

FACTS:
{facts_block}

Write the summary now.
TXT,
            ],
        ];

        foreach ($variants as $v) {
            PresentationAiVariant::updateOrCreate(['key' => $v['key']], $v);
        }
    }
}
