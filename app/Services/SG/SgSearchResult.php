<?php

declare(strict_types=1);

namespace App\Services\SG;

/**
 * Phase 3j — return type from SgSearchService::search.
 *
 * Discriminate on $errorMessage / $parseError to render friendly UI states.
 * documents is a list of associative arrays with keys:
 *   { sg_document_number, sg_page_number, sg_doc_type, sg_source_url, real_name }
 */
final class SgSearchResult
{
    /**
     * @param array<int, array<string, mixed>> $documents
     */
    public function __construct(
        public readonly array $documents,
        public readonly bool $fromCache,
        public readonly ?\DateTimeImmutable $fetchedAt,
        public readonly ?string $errorMessage = null,
        public readonly bool $parseError = false,
        public readonly array $resolvedQuery = [],
    ) {}

    public function ok(): bool
    {
        return $this->errorMessage === null && !$this->parseError;
    }

    public function isEmpty(): bool
    {
        return $this->ok() && empty($this->documents);
    }
}
