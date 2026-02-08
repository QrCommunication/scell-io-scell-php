<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class FiscalAnchor
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $anchorType,
        public string $sourceHash,
        public ?string $anchorReference = null,
        public ?string $anchorProvider = null,
        public ?string $anchoredAt = null,
        public ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            tenantId: $data['tenant_id'],
            anchorType: $data['anchor_type'] ?? $data['type'] ?? 'closing',
            sourceHash: $data['source_hash'],
            anchorReference: $data['anchor_reference'] ?? null,
            anchorProvider: $data['anchor_provider'] ?? $data['provider'] ?? null,
            anchoredAt: $data['anchored_at'] ?? null,
            createdAt: $data['created_at'] ?? null,
        );
    }
}
