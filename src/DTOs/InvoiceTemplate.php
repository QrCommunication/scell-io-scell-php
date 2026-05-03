<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Template de personnalisation des factures et avoirs Scell.io.
 *
 * Resolution cascade : explicit_id > sub_tenant default > tenant default > system default.
 *
 * @see https://scell.io/docs/sdk pour la documentation complete
 */
readonly class InvoiceTemplate
{
    public const SCOPE_SYSTEM = 'system';
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_SUB_TENANT = 'sub_tenant';

    /**
     * @param array<string, mixed>|null $advancedOptions
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $id,
        public string $scope,
        public ?string $tenantId,
        public ?string $subTenantId,
        public string $name,
        public ?string $description,
        public bool $isDefault,
        public bool $isAvailableToSubtenants,
        public ?string $logoUrl,
        public string $logoPosition,
        public ?string $primaryColor,
        public ?string $accentColor,
        public ?string $textColor,
        public ?string $backgroundColor,
        public ?string $headerText,
        public ?string $footerText,
        public ?string $customMentions,
        public ?array $advancedOptions,
        public ?array $metadata,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            scope: $data['scope'],
            tenantId: $data['tenant_id'] ?? null,
            subTenantId: $data['sub_tenant_id'] ?? null,
            name: $data['name'],
            description: $data['description'] ?? null,
            isDefault: (bool) ($data['is_default'] ?? false),
            isAvailableToSubtenants: (bool) ($data['is_available_to_subtenants'] ?? false),
            logoUrl: $data['logo_url'] ?? null,
            logoPosition: $data['logo_position'] ?? 'top-left',
            primaryColor: $data['primary_color'] ?? null,
            accentColor: $data['accent_color'] ?? null,
            textColor: $data['text_color'] ?? null,
            backgroundColor: $data['background_color'] ?? null,
            headerText: $data['header_text'] ?? null,
            footerText: $data['footer_text'] ?? null,
            customMentions: $data['custom_mentions'] ?? null,
            advancedOptions: $data['advanced_options'] ?? null,
            metadata: $data['metadata'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }

    public function isSystem(): bool
    {
        return $this->scope === self::SCOPE_SYSTEM;
    }

    public function isTenantScope(): bool
    {
        return $this->scope === self::SCOPE_TENANT;
    }

    public function isSubTenantScope(): bool
    {
        return $this->scope === self::SCOPE_SUB_TENANT;
    }
}
