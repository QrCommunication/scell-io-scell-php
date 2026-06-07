<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente une categorie de produit (registre Scell.io scope tenant + sub_tenant).
 *
 * Les categories permettent de regrouper les produits/services du catalogue
 * ({@see Product}). Le registre est scope strictement par (tenant, sub_tenant)
 * — vous ne verrez que les categories de votre propre scope.
 */
readonly class ProductCategory
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public ?string $subTenantId,
        public string $name,
        public int $position = 0,
        public ?string $color = null,
        public ?string $description = null,
        /** Nombre de produits ranges dans cette categorie (si fourni par l'API). */
        public ?int $productsCount = null,
        public ?array $metadata = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            tenantId: $data['tenant_id'],
            subTenantId: $data['sub_tenant_id'] ?? null,
            name: $data['name'],
            position: (int) ($data['position'] ?? 0),
            color: $data['color'] ?? null,
            description: $data['description'] ?? null,
            productsCount: isset($data['products_count']) ? (int) $data['products_count'] : null,
            metadata: $data['metadata'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }
}
