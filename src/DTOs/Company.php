<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente une entreprise.
 */
readonly class Company
{
    public function __construct(
        public string $id,
        public string $name,
        public string $siret,
        public string $addressLine1,
        public string $postalCode,
        public string $city,
        public string $status,
        public ?string $siren = null,
        public ?string $vatNumber = null,
        public ?string $legalForm = null,
        public ?string $addressLine2 = null,
        public string $country = 'FR',
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $website = null,
        public ?string $logoUrl = null,
        public ?DateTimeImmutable $kycCompletedAt = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            siret: $data['siret'],
            addressLine1: $data['address_line1'],
            postalCode: $data['postal_code'],
            city: $data['city'],
            status: $data['status'],
            siren: $data['siren'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            legalForm: $data['legal_form'] ?? null,
            addressLine2: $data['address_line2'] ?? null,
            country: $data['country'] ?? 'FR',
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            website: $data['website'] ?? null,
            logoUrl: $data['logo_url'] ?? null,
            kycCompletedAt: isset($data['kyc_completed_at']) ? new DateTimeImmutable($data['kyc_completed_at']) : null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }

    /**
     * Verifie si le KYC est complete.
     */
    public function isKycCompleted(): bool
    {
        return $this->status === 'active' && $this->kycCompletedAt !== null;
    }

    /**
     * Verifie si l'entreprise est active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifie si l'entreprise est en attente de KYC.
     */
    public function isPendingKyc(): bool
    {
        return $this->status === 'pending_kyc';
    }

    /**
     * Retourne l'adresse complete formatee.
     */
    public function formattedAddress(): string
    {
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
}
