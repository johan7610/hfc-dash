<?php

declare(strict_types=1);

namespace App\Services\MarketReports\DTOs;

/**
 * Result of MarketReportParser::canParse() — a confidence score (0..1) plus
 * a short list of reasons the parser thinks it does or does not match.
 * Used by the upload controller's auto-detect loop to pick the best parser.
 *
 * Spec: .ai/specs/mic-complete-spec.md §8.2.
 */
final class ParserConfidence
{
    /**
     * @param array<int, string> $reasons
     */
    public function __construct(
        public readonly float $score,
        public readonly array $reasons = [],
    ) {}

    public static function none(string $reason = 'no match'): self
    {
        return new self(0.0, [$reason]);
    }

    public static function low(string $reason): self
    {
        return new self(0.2, [$reason]);
    }

    public static function high(float $score, array $reasons): self
    {
        return new self(max(0.0, min(1.0, $score)), $reasons);
    }
}
