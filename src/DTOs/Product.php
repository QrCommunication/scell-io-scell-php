<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente un produit ou service du catalogue (registre Scell.io scope
 * tenant + sub_tenant).
 *
 * Le catalogue permet de reutiliser un article (libelle, prix HT, taux de
 * TVA par defaut, unite...) pour pre-remplir une ligne de facture ou de devis
 * sans le resaisir. Le registre est scope strictement par (tenant, sub_tenant)
 * — vous ne verrez que les produits de votre propre scope.
 *
 * Comme pour les acheteurs ({@see Buyer}), le catalogue porte l'etat *courant*
 * de l'article : modifier un produit plus tard n'impacte pas les factures deja
 * emises (snapshot immutable cote facture, ISCA fiscal compliance).
 */
readonly class Product
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public ?string $subTenantId,
        public string $name,
        public float $unitPriceHt,
        public float $defaultTaxRate = 0.0,
        public string $unit = 'C62',
        public string $currency = 'EUR',
        public bool $isActive = true,
        public ?string $productCategoryId = null,
        public ?string $description = null,
        public ?string $sku = null,
        /** Categorie de revenu fiscal : 'goods', 'service', 'accommodation' ou null. */
        public ?string $revenueCategory = null,
        /** Libelle lisible de la categorie de revenu (derive cote serveur). */
        public ?string $revenueCategoryLabel = null,
        public ?float $defaultDiscountRate = null,
        /** Categorie imbriquee (eager-load) si fournie par l'API. */
        public ?ProductCategory $productCategory = null,
        public ?array $metadata = null,
        public ?string $notes = null,
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
            unitPriceHt: (float) ($data['unit_price_ht'] ?? 0.0),
            defaultTaxRate: (float) ($data['default_tax_rate'] ?? 0.0),
            unit: $data['unit'] ?? 'C62',
            currency: $data['currency'] ?? 'EUR',
            isActive: (bool) ($data['is_active'] ?? true),
            productCategoryId: $data['product_category_id'] ?? null,
            description: $data['description'] ?? null,
            sku: $data['sku'] ?? null,
            revenueCategory: $data['revenue_category'] ?? null,
            revenueCategoryLabel: $data['revenue_category_label'] ?? null,
            defaultDiscountRate: isset($data['default_discount_rate'])
                ? (float) $data['default_discount_rate']
                : null,
            productCategory: isset($data['product_category']) && is_array($data['product_category'])
                ? ProductCategory::fromArray($data['product_category'])
                : null,
            metadata: $data['metadata'] ?? null,
            notes: $data['notes'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }
}
