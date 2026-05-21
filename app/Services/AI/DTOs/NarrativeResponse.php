<?php

declare(strict_types=1);

namespace App\Services\AI\DTOs;

use Carbon\CarbonInterface;

/**
 * Output DTO for `App\Services\AI\AnthropicGateway::generate()`.
 *
 * Always returns a populated response — `fromFallback=true` + `errorMessage`
 * when the API failed but a fallback was provided; throws only when API
 * fails AND no fallback was provided in the request.
 *
 * Spec: .ai/specs/mic-complete-spec.md §4.8.
 */
final class NarrativeResponse
{
    /**
     * @param string  $outputText
     * @param array<string, mixed>|null $outputJson  Populated by
     *                                  `generateStructured()` when the model
     *                                  returned valid JSON.
     * @param string  $model            The actual model that responded
     *                                  ('claude-haiku-4-5', etc). For fallback
     *                                  responses, the model that WOULD have
     *                                  been called.
     * @param int     $inputTokens
     * @param int     $outputTokens
     * @param float   $costZar
     * @param bool    $fromCache        True if served from ai_narrative_cache
     *                                  without hitting the API.
     * @param bool    $fromFallback     True if the API failed (or was disabled)
     *                                  and the request's fallbackData was used.
     * @param string|null $errorMessage  If degraded — the API error summary.
     * @param CarbonInterface $generatedAt  When the underlying text was produced
     *                                  (either by the API call or by the cache
     *                                  row's original API call).
     */
    public function __construct(
        public readonly string $outputText,
        public readonly ?array $outputJson,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly float $costZar,
        public readonly bool $fromCache,
        public readonly bool $fromFallback,
        public readonly ?string $errorMessage,
        public readonly CarbonInterface $generatedAt,
    ) {}

    public function isDegraded(): bool
    {
        return $this->fromFallback || $this->errorMessage !== null;
    }
}
