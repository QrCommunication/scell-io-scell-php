<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente un sub-tenant (client final d'un tenant partenaire).
 *
 * @example
 * ```php
 * // Creer depuis une reponse API
 * $subTenant = SubTenant::fromArray($apiResponse['data']);
 *
 * // Acceder aux proprietes
 * echo $subTenant->name;
 * echo $subTenant->siret;
 * echo $subTenant->formattedAddress();
 * ```
 */
readonly class SubTenant
{
    public function __construct(
        public string $id,
        public string $name,
        public string $siret,
        public ?string $externalId = null,
        public ?string $siren = null,
        public ?string $vatNumber = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public string $country = 'FR',
        public string $status = 'active',
        public ?array $metadata = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            siret: $data['siret'],
            externalId: $data['external_id'] ?? null,
            siren: $data['siren'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            addressLine1: $data['address_line1'] ?? null,
            addressLine2: $data['address_line2'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            city: $data['city'] ?? null,
            country: $data['country'] ?? 'FR',
            status: $data['status'] ?? 'active',
            metadata: $data['metadata'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }

    /**
     * Convertit en tableau pour l'API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'external_id' => $this->externalId,
            'name' => $this->name,
            'siret' => $this->siret,
            'siren' => $this->siren,
            'vat_number' => $this->vatNumber,
            'email' => $this->email,
            'phone' => $this->phone,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ], fn($value) => $value !== null);
    }

    /**
     * Verifie si le sub-tenant est actif.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifie si le sub-tenant a une adresse complete.
     */
    public function hasCompleteAddress(): bool
    {
        return $this->addressLine1 !== null
            && $this->postalCode !== null
            && $this->city !== null;
    }

    /**
     * Retourne l'adresse formatee sur plusieurs lignes.
     */
    public function formattedAddress(): string
    {
        if (!$this->hasCompleteAddress()) {
            return '';
        }

        $lines = [$this->addressLine1];
        if ($this->addressLine2) {
            $lines[] = $this->addressLine2;
        }
        $lines[] = "{$this->postalCode} {$this->city}";
        if ($this->country !== 'FR') {
            $lines[] = $this->country;
        }

        return implode("\n", $lines);
    }

    /**
     * Retourne l'adresse en une seule ligne.
     */
    public function oneLineAddress(): string
    {
        if (!$this->hasCompleteAddress()) {
            return '';
        }

        $parts = [$this->addressLine1];
        if ($this->addressLine2) {
            $parts[] = $this->addressLine2;
        }
        $parts[] = "{$this->postalCode} {$this->city}";
        if ($this->country !== 'FR') {
            $parts[] = $this->country;
        }

        return implode(', ', $parts);
    }
}
