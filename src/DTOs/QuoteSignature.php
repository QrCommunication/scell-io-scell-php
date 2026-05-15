<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente les informations de signature electronique d'un devis.
 */
readonly class QuoteSignature
{
    public function __construct(
        public string $status,
        public ?string $signedByName = null,
        public ?string $signedByEmail = null,
        public ?DateTimeImmutable $signedAt = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $signatureImageUrl = null,
        public ?string $certificateUrl = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? 'pending',
            signedByName: $data['signed_by_name'] ?? null,
            signedByEmail: $data['signed_by_email'] ?? null,
            signedAt: isset($data['signed_at']) ? new DateTimeImmutable($data['signed_at']) : null,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            signatureImageUrl: $data['signature_image_url'] ?? null,
            certificateUrl: $data['certificate_url'] ?? null,
        );
    }

    /**
     * Indique si le devis a ete signe.
     */
    public function isSigned(): bool
    {
        return $this->status === 'signed' && $this->signedAt !== null;
    }

    /**
     * Indique si la signature est en attente.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
