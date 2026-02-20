<?php

namespace App\Services\SaleProbability\DTOs;

class SaleProbabilityResult
{
    // All output fields start null; computed in later phases.
    public ?float  $p30          = null;
    public ?float  $p60          = null;
    public ?float  $p90          = null;
    public ?int    $expectedDays = null;
    public ?string $skipReason   = null;

    private array $breakdown = [];

    /** Price-sensitivity steps — computed at runtime, NOT persisted to DB. */
    public array $sensitivity = [];

    private function __construct(
        public readonly string $modelVersion,
        public readonly string $inputsHash,
    ) {}

    public static function empty(string $modelVersion, string $inputsHash): self
    {
        return new self($modelVersion, $inputsHash);
    }

    public function setBreakdown(array $breakdown): void
    {
        $this->breakdown = $breakdown;
    }

    /**
     * Flat key→value map of top-level outputs for storage in outputs_json.
     */
    public function toValuesArray(): array
    {
        return [
            'p30'           => $this->p30,
            'p60'           => $this->p60,
            'p90'           => $this->p90,
            'expected_days' => $this->expectedDays,
            'skip_reason'   => $this->skipReason,
        ];
    }

    /**
     * Full signal breakdown for storage in breakdown_json.
     */
    public function toBreakdownArray(): array
    {
        return $this->breakdown;
    }
}
