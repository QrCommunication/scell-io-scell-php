<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class FiscalCompliance
{
    public function __construct(
        public float $closingCoveragePercent,
        public float $chainIntegrityPercent,
        public array $openIncidents,
        public int $openIncidentsCount,
        public string $overallStatus,
        public ?string $lastIntegrityCheckAt = null,
        public ?string $lastClosingAt = null,
        public ?string $lastClosingDate = null,
        public int $totalFiscalEntries = 0,
        public int $daysWithActivity = 0,
        public int $daysClosed = 0,
        public ?array $attestationStatus = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            closingCoveragePercent: (float) $data['closing_coverage_percent'],
            chainIntegrityPercent: (float) $data['chain_integrity_percent'],
            openIncidents: $data['open_incidents'] ?? [],
            openIncidentsCount: (int) ($data['open_incidents_count'] ?? 0),
            overallStatus: $data['overall_status'],
            lastIntegrityCheckAt: $data['last_integrity_check_at'] ?? null,
            lastClosingAt: $data['last_closing_at'] ?? null,
            lastClosingDate: $data['last_closing_date'] ?? null,
            totalFiscalEntries: (int) ($data['total_fiscal_entries'] ?? 0),
            daysWithActivity: (int) ($data['days_with_activity'] ?? 0),
            daysClosed: (int) ($data['days_closed'] ?? 0),
            attestationStatus: $data['attestation_status'] ?? null,
        );
    }

    public function isCompliant(): bool
    {
        return $this->overallStatus === 'CONFORME';
    }

    public function hasAlerts(): bool
    {
        return $this->overallStatus === 'ALERTE';
    }

    public function isNonCompliant(): bool
    {
        return $this->overallStatus === 'NON_CONFORME';
    }
}
