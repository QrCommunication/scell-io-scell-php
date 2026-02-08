<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class FiscalEntry
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public int $sequenceNumber,
        public string $entryType,
        public string $fiscalDate,
        public ?string $entityType = null,
        public ?string $entityId = null,
        public ?array $dataSnapshot = null,
        public ?string $dataHash = null,
        public ?string $previousHash = null,
        public ?string $chainHash = null,
        public ?string $environment = null,
        public ?string $legalStatus = null,
        public ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            tenantId: $data['tenant_id'],
            sequenceNumber: (int) $data['sequence_number'],
            entryType: $data['entry_type'],
            fiscalDate: $data['fiscal_date'],
            entityType: $data['entity_type'] ?? null,
            entityId: $data['entity_id'] ?? null,
            dataSnapshot: $data['data_snapshot'] ?? null,
            dataHash: $data['data_hash'] ?? null,
            previousHash: $data['previous_hash'] ?? null,
            chainHash: $data['chain_hash'] ?? null,
            environment: $data['environment'] ?? null,
            legalStatus: $data['legal_status'] ?? null,
            createdAt: $data['created_at'] ?? null,
        );
    }
}
