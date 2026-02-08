<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class BillingTransaction
{
    public function __construct(
        public string $id,
        public string $type,
        public float $amount,
        public string $currency,
        public ?string $description = null,
        public ?string $reference = null,
        public ?string $status = null,
        public ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            amount: (float) $data['amount'],
            currency: $data['currency'] ?? 'EUR',
            description: $data['description'] ?? null,
            reference: $data['reference'] ?? null,
            status: $data['status'] ?? null,
            createdAt: $data['created_at'] ?? null,
        );
    }
}
