<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class StatsMonthly
{
    public function __construct(
        public string $month,
        public int $invoicesCount,
        public int $creditNotesCount,
        public float $revenue,
        public float $expenses,
        public ?array $dailyBreakdown = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            month: $data['month'] ?? '',
            invoicesCount: (int) ($data['invoices_count'] ?? 0),
            creditNotesCount: (int) ($data['credit_notes_count'] ?? 0),
            revenue: (float) ($data['revenue'] ?? 0),
            expenses: (float) ($data['expenses'] ?? 0),
            dailyBreakdown: $data['daily_breakdown'] ?? null,
        );
    }
}
