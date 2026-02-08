<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

readonly class FiscalRule
{
    public function __construct(
        public string $id,
        public string $ruleKey,
        public string $name,
        public string $category,
        public array $ruleDefinition,
        public int $version,
        public string $effectiveFrom,
        public ?string $effectiveUntil = null,
        public ?string $legalReference = null,
        public ?string $tenantId = null,
        public ?string $description = null,
        public bool $isActive = true,
        public ?string $createdAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            ruleKey: $data['rule_key'],
            name: $data['name'],
            category: $data['category'],
            ruleDefinition: $data['rule_definition'] ?? [],
            version: (int) ($data['version'] ?? 1),
            effectiveFrom: $data['effective_from'],
            effectiveUntil: $data['effective_until'] ?? null,
            legalReference: $data['legal_reference'] ?? null,
            tenantId: $data['tenant_id'] ?? null,
            description: $data['description'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            createdAt: $data['created_at'] ?? null,
        );
    }

    public function isGlobal(): bool
    {
        return $this->tenantId === null;
    }
}
