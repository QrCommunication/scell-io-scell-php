<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente un acheteur (registre Scell.io scope tenant + sub_tenant).
 *
 * L'acheteur peut etre reutilise pour emettre plusieurs factures sans
 * resaisir l'identite + adresse. Le registre porte l'etat *courant* du
 * client. Quand une facture est emise, ses champs sont snapshotes sur
 * la facture (immutabilite ISCA) — modifier l'acheteur plus tard
 * n'impacte pas les factures deja emises.
 */
readonly class Buyer
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public ?string $subTenantId,
        public string $name,
        public bool $isIndividual,
        public Address $billingAddress,
        public string $country,
        public ?Address $shippingAddress = null,
        public ?string $siret = null,
        public ?string $vatNumber = null,
        public ?string $legalId = null,
        public ?string $legalIdScheme = null,
        public ?string $email = null,
        /** Adresse email de facturation (si differente de l'email de contact). */
        public ?string $billingEmail = null,
        public ?string $phone = null,
        public bool $hasDistinctShippingAddress = false,
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
            shippingAddress: isset($data['shipping_address']) && is_array($data['shipping_address'])
                ? Address::fromArray($data['shipping_address'])
                : null,
            siret: $data['siret'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            legalId: $data['legal_id'] ?? null,
            legalIdScheme: $data['legal_id_scheme'] ?? null,
            email: $data['email'] ?? null,
            billingEmail: $data['billing_email'] ?? null,
            phone: $data['phone'] ?? null,
            hasDistinctShippingAddress: (bool) ($data['has_distinct_shipping_address'] ?? false),
            metadata: $data['metadata'] ?? null,
            notes: $data['notes'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }
}
