<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class StatsOverview
{
    public function __construct(
        public int $totalInvoices,
        public int $totalCreditNotes,
        public float $totalRevenue,
        public float $totalExpenses,
        public int $activeSubTenants,
        public string $currency,
        public ?array $statusBreakdown = null,
        public ?array $periodComparison = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            totalInvoices: (int) ($data['total_invoices'] ?? 0),
            totalCreditNotes: (int) ($data['total_credit_notes'] ?? 0),
            totalRevenue: (float) ($data['total_revenue'] ?? 0),
            totalExpenses: (float) ($data['total_expenses'] ?? 0),
            activeSubTenants: (int) ($data['active_sub_tenants'] ?? 0),
            currency: $data['currency'] ?? 'EUR',
            statusBreakdown: $data['status_breakdown'] ?? null,
            periodComparison: $data['period_comparison'] ?? null,
        );
    }
}
