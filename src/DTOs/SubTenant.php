<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;
use Scell\Sdk\Enums\OnboardingStatus;

/**
 * Represente un sub-tenant (client final d'un tenant partenaire).
 *
 * v2.0.0 : `kycStatus` / `kycVerifiedAt` / `kycDelegated` ont ete supprimes.
 * Remplaces par `onboardingStatus` (6 valeurs) et les statuts SuperPDP
 * explicites (`superpdpCompanyVerificationStatus`,
 * `superpdpUserIdentityVerificationStatus`).
 *
 * @example
 * ```php
 * $subTenant = SubTenant::fromArray($apiResponse['data']);
 * echo $subTenant->name;
 * if ($subTenant->isOnboarded()) { ... }
 * ```
 */
readonly class SubTenant
{
    public function __construct(
        public string $id,
        public string $name,
        public OnboardingStatus $onboardingStatus,
        public ?string $siret = null,
        public ?string $externalId = null,
        public ?string $siren = null,
        public ?string $vatNumber = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $contactFirstName = null,
        public ?string $contactLastName = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public string $country = 'FR',
        public ?string $superpdpCompanyVerificationStatus = null,
        public ?string $superpdpUserIdentityVerificationStatus = null,
        public ?DateTimeImmutable $lastPolledAt = null,
        public ?string $resumeUrl = null,
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
            onboardingStatus: OnboardingStatus::from($data['onboarding_status']),
            siret: $data['siret'] ?? null,
            externalId: $data['external_id'] ?? null,
            siren: $data['siren'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            contactFirstName: $data['contact_first_name'] ?? null,
            contactLastName: $data['contact_last_name'] ?? null,
            addressLine1: $data['address_line1'] ?? null,
            addressLine2: $data['address_line2'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            city: $data['city'] ?? null,
            country: $data['country'] ?? 'FR',
            superpdpCompanyVerificationStatus: $data['superpdp_company_verification_status'] ?? null,
            superpdpUserIdentityVerificationStatus: $data['superpdp_user_identity_verification_status'] ?? null,
            lastPolledAt: isset($data['last_polled_at']) ? new DateTimeImmutable($data['last_polled_at']) : null,
            resumeUrl: $data['resume_url'] ?? null,
            metadata: $data['metadata'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }

    /**
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
            'contact_first_name' => $this->contactFirstName,
            'contact_last_name' => $this->contactLastName,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
            'onboarding_status' => $this->onboardingStatus->value,
            'superpdp_company_verification_status' => $this->superpdpCompanyVerificationStatus,
            'superpdp_user_identity_verification_status' => $this->superpdpUserIdentityVerificationStatus,
            'last_polled_at' => $this->lastPolledAt?->format(DATE_ATOM),
            'resume_url' => $this->resumeUrl,
            'metadata' => $this->metadata,
        ], fn($value) => $value !== null);
    }

    /**
     * Onboarding fully completed (status = active).
     */
    public function isOnboarded(): bool
    {
        return $this->onboardingStatus === OnboardingStatus::Active;
    }

    /**
     * Onboarding still in progress (any non-terminal status).
     */
    public function isPending(): bool
    {
        return $this->onboardingStatus->isInProgress();
    }

    /**
     * Onboarding failed and requires intervention.
     */
    public function hasFailed(): bool
    {
        return $this->onboardingStatus === OnboardingStatus::SuperPDPFailed;
    }

    public function hasCompleteAddress(): bool
    {
        return $this->addressLine1 !== null
            && $this->postalCode !== null
            && $this->city !== null;
    }

    public function formattedAddress(): string
    {
        if (! $this->hasCompleteAddress()) {
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

    public function oneLineAddress(): string
    {
        if (! $this->hasCompleteAddress()) {
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
