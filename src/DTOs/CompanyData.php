<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Donnees societe normalisees retournees par le lookup Sirene
 * (depuis v2.0.0).
 */
readonly class CompanyData
{
    public function __construct(
        public string $siret,
        public string $siren,
        public string $name,
        public string $addressLine1,
        public string $postalCode,
        public string $city,
        public string $country,
        public bool $isActive,
        public ?string $addressLine2 = null,
        public ?string $legalForm = null,
        public ?string $legalFormCode = null,
        public ?string $nafCode = null,
        public ?string $nafLabel = null,
        public ?string $vatNumber = null,
        public ?string $createdAt = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $address = $data['address'] ?? [];

        return new self(
            siret: (string) $data['siret'],
            siren: (string) $data['siren'],
            name: (string) $data['name'],
            addressLine1: (string) ($address['line1'] ?? ''),
            postalCode: (string) ($address['postal_code'] ?? ''),
            city: (string) ($address['city'] ?? ''),
            country: (string) ($address['country'] ?? 'FR'),
            isActive: (bool) ($data['is_active'] ?? false),
            addressLine2: $address['line2'] ?? null,
            legalForm: $data['legal_form'] ?? null,
            legalFormCode: $data['legal_form_code'] ?? null,
            nafCode: $data['naf_code'] ?? null,
            nafLabel: $data['naf_label'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            createdAt: $data['created_at'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'siret' => $this->siret,
            'siren' => $this->siren,
            'name' => $this->name,
            'legal_form' => $this->legalForm,
            'legal_form_code' => $this->legalFormCode,
            'naf_code' => $this->nafCode,
            'naf_label' => $this->nafLabel,
            'vat_number' => $this->vatNumber,
            'address' => array_filter([
                'line1' => $this->addressLine1,
                'line2' => $this->addressLine2,
                'postal_code' => $this->postalCode,
                'city' => $this->city,
                'country' => $this->country,
            ], fn($v) => $v !== null && $v !== ''),
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
        ], fn($v) => $v !== null);
    }
}
