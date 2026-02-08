<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class BillingUsage
{
    public function __construct(
        public string $period,
        public int $invoicesCount,
        public int $creditNotesCount,
        public int $signaturesCount,
        public float $totalCost,
        public string $currency,
        public ?array $breakdown = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            period: $data['period'] ?? '',
            invoicesCount: (int) ($data['invoices_count'] ?? 0),
            creditNotesCount: (int) ($data['credit_notes_count'] ?? 0),
            signaturesCount: (int) ($data['signatures_count'] ?? 0),
            totalCost: (float) ($data['total_cost'] ?? 0),
            currency: $data['currency'] ?? 'EUR',
            breakdown: $data['breakdown'] ?? null,
        );
    }
}
