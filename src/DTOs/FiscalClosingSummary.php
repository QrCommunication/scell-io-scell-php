<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class FiscalClosingSummary
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $closingDate,
        public string $closingType,
        public string $status,
        public int $entriesCount,
        public float $totalDebit,
        public float $totalCredit,
        public ?string $chainHash = null,
        public ?string $environment = null,
        public ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            tenantId: $data['tenant_id'],
            closingDate: $data['closing_date'],
            closingType: $data['closing_type'] ?? 'daily',
            status: $data['status'],
            entriesCount: (int) ($data['entries_count'] ?? 0),
            totalDebit: (float) ($data['total_debit'] ?? 0),
            totalCredit: (float) ($data['total_credit'] ?? 0),
            chainHash: $data['chain_hash'] ?? null,
            environment: $data['environment'] ?? null,
            createdAt: $data['created_at'] ?? null,
        );
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
