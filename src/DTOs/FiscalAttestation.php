<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class FiscalAttestation
{
    public function __construct(
        public int $year,
        public string $tenantName,
        public string $softwareVersion,
        public array $compliance,
        public ?string $generatedAt = null,
        public ?string $certificateHash = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            year: (int) $data['year'],
            tenantName: $data['tenant_name'] ?? $data['company_name'] ?? '',
            softwareVersion: $data['software_version'] ?? '',
            compliance: $data['compliance'] ?? [],
            generatedAt: $data['generated_at'] ?? null,
            certificateHash: $data['certificate_hash'] ?? null,
        );
    }
}
