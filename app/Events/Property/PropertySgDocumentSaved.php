<?php

declare(strict_types=1);

namespace App\Events\Property;

use App\Events\AbstractDomainEvent;

/**
 * Phase 3j — fires when an SG document TIF is successfully downloaded and
 * saved to the property's drive.
 *
 * Slug: property_sg_document.saved
 * Subject: the Property (the SG document is metadata on the property).
 *
 * Renders in the property timeline + agent activity feed.
 */
final class PropertySgDocumentSaved extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $propertyId,
        public readonly int $sgDocumentId,
        public readonly string $sgDocumentNumber,
        public readonly int $sgPageNumber,
        public readonly string $sgDocType,
        public readonly int $fileSizeBytes,
        public readonly ?int $agencyIdValue,
        public readonly ?int $actorUserIdValue,
        ?string $traceId = null,
    ) {
        parent::__construct($traceId);
    }

    public function agencyId(): ?int
    {
        return $this->agencyIdValue;
    }

    public function actorUserId(): ?int
    {
        return $this->actorUserIdValue;
    }

    public function subject(): ?array
    {
        return ['App\\Models\\Property', $this->propertyId];
    }

    public function context(): array
    {
        return [
            'sg_document_id'     => $this->sgDocumentId,
            'sg_document_number' => $this->sgDocumentNumber,
            'sg_page_number'     => $this->sgPageNumber,
            'sg_doc_type'        => $this->sgDocType,
            'file_size_bytes'    => $this->fileSizeBytes,
        ];
    }
}
