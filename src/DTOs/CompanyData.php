<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Donnees societe normalisees retournees par le lookup Sirene
 * (depuis v2.0.0).
 *
 * Shape JSON cote API :
 * ```json
 * {
 *   "name": "RL CONSEIL",
 *   "legal_name": "RL CONSEIL",
 *   "siret": "10178342100015",
 *   "siren": "101783421",
 *   "vat_number": "FR95101783421",
 *   "legal_form": "5710",
 *   "legal_form_code": null,
 *   "naf_code": "62.02A",
 *   "naf_label": null,
 *   "address_line1": "200 RUE DE LA CROIX NIVERT",
 *   "address_line2": null,
 *   "postal_code": "75015",
 *   "city": "PARIS",
 *   "country": "FR",
 *   "is_active": true,
 *   "creation_date": "2026-01-19",
 *   "employee_range": "NN"
 * }
 * ```
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
        public ?string $legalName = null,
        public ?string $addressLine2 = null,
        public ?string $legalForm = null,
        public ?string $legalFormCode = null,
        public ?string $nafCode = null,
        public ?string $nafLabel = null,
        public ?string $vatNumber = null,
        public ?string $creationDate = null,
        public ?string $employeeRange = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // L'API peut envoyer l'adresse soit a plat (Etalab/INSEE normalise),
        // soit nested (legacy ou clients custom). On accepte les deux.
        $nested = is_array($data['address'] ?? null) ? $data['address'] : [];

        return new self(
            siret: (string) ($data['siret'] ?? ''),
            siren: (string) ($data['siren'] ?? ''),
            name: (string) ($data['name'] ?? $data['legal_name'] ?? ''),
            addressLine1: (string) ($data['address_line1'] ?? $nested['line1'] ?? ''),
            postalCode: (string) ($data['postal_code'] ?? $nested['postal_code'] ?? ''),
            city: (string) ($data['city'] ?? $nested['city'] ?? ''),
            country: (string) ($data['country'] ?? $nested['country'] ?? 'FR'),
            isActive: (bool) ($data['is_active'] ?? false),
            legalName: $data['legal_name'] ?? null,
            addressLine2: $data['address_line2'] ?? $nested['line2'] ?? null,
            legalForm: $data['legal_form'] ?? null,
            legalFormCode: $data['legal_form_code'] ?? null,
            nafCode: $data['naf_code'] ?? null,
            nafLabel: $data['naf_label'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            creationDate: $data['creation_date'] ?? $data['created_at'] ?? null,
            employeeRange: $data['employee_range'] ?? null,
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
            'legal_name' => $this->legalName,
            'legal_form' => $this->legalForm,
            'legal_form_code' => $this->legalFormCode,
            'naf_code' => $this->nafCode,
            'naf_label' => $this->nafLabel,
            'vat_number' => $this->vatNumber,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
            'is_active' => $this->isActive,
            'creation_date' => $this->creationDate,
            'employee_range' => $this->employeeRange,
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
