<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class BillingInvoice
{
    public function __construct(
        public string $id,
        public string $invoiceNumber,
        public string $period,
        public float $totalHt,
        public float $totalTax,
        public float $totalTtc,
        public string $status,
        public string $currency,
        public ?string $issuedAt = null,
        public ?string $dueDate = null,
        public ?string $paidAt = null,
        public ?array $lines = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            invoiceNumber: $data['invoice_number'] ?? '',
            period: $data['period'] ?? '',
            totalHt: (float) ($data['total_ht'] ?? 0),
            totalTax: (float) ($data['total_tax'] ?? 0),
            totalTtc: (float) ($data['total_ttc'] ?? 0),
            status: $data['status'] ?? 'pending',
            currency: $data['currency'] ?? 'EUR',
            issuedAt: $data['issued_at'] ?? null,
            dueDate: $data['due_date'] ?? null,
            paidAt: $data['paid_at'] ?? null,
            lines: $data['lines'] ?? null,
        );
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
