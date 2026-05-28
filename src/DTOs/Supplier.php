<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente un fournisseur (registre Scell.io scope tenant + sub_tenant).
 *
 * Miroir de {@see Buyer}, cote emetteur : le registre des fournisseurs
 * permet de reutiliser l'identite + adresse de facturation d'un partenaire
 * sans la resaisir. Le registre est scope strictement par
 * (tenant, sub_tenant) — vous ne verrez que les fournisseurs de votre
 * propre scope.
 *
 * Contrairement aux acheteurs, un fournisseur ne porte ni adresse de
 * livraison (concept acheteur), ni email de facturation distinct.
 */
readonly class Supplier
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public ?string $subTenantId,
        public string $name,
        public bool $isIndividual,
        public Address $billingAddress,
        public string $country,
        public ?string $siret = null,
        public ?string $vatNumber = null,
        public ?string $legalId = null,
        public ?string $legalIdScheme = null,
        public ?string $email = null,
        public ?string $phone = null,
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
            isIndividual: (bool) ($data['is_individual'] ?? false),
            billingAddress: Address::fromArray($data['billing_address'] ?? []),
            country: $data['country'] ?? 'FR',
            siret: $data['siret'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            legalId: $data['legal_id'] ?? null,
            legalIdScheme: $data['legal_id_scheme'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            metadata: $data['metadata'] ?? null,
            notes: $data['notes'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }
}
