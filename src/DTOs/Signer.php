<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;
use Scell\Sdk\Enums\AuthMethod;

/**
 * Represente un signataire.
 */
readonly class Signer
{
    public function __construct(
        public ?string $id,
        public string $firstName,
        public string $lastName,
        public AuthMethod $authMethod,
        public ?string $email = null,
        public ?string $phone = null,
        public string $status = 'pending',
        public ?string $signingUrl = null,
        public ?DateTimeImmutable $signedAt = null,
        public ?DateTimeImmutable $refusedAt = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            authMethod: AuthMethod::from($data['auth_method']),
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            status: $data['status'] ?? 'pending',
            signingUrl: $data['signing_url'] ?? null,
            signedAt: isset($data['signed_at']) ? new DateTimeImmutable($data['signed_at']) : null,
            refusedAt: isset($data['refused_at']) ? new DateTimeImmutable($data['refused_at']) : null,
        );
    }

    /**
     * Cree un nouveau signataire pour une demande de signature.
     */
    public static function create(
        string $firstName,
        string $lastName,
        AuthMethod $authMethod,
        ?string $email = null,
        ?string $phone = null,
    ): self {
        return new self(
            id: null,
            firstName: $firstName,
            lastName: $lastName,
            authMethod: $authMethod,
            email: $email,
            phone: $phone,
        );
    }

    /**
     * Convertit en tableau pour l'API.
     */
    public function toArray(): array
    {
        return array_filter([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'auth_method' => $this->authMethod->value,
        ], fn($value) => $value !== null);
    }

    /**
     * Retourne le nom complet.
     */
    public function fullName(): string
    {
        return "{$this->firstName} {$this->lastName}";
    }

    /**
     * Verifie si le signataire a signe.
     */
    public function hasSigned(): bool
    {
        return $this->status === 'signed' && $this->signedAt !== null;
    }

    /**
     * Verifie si le signataire a refuse.
     */
    public function hasRefused(): bool
    {
        return $this->status === 'refused' && $this->refusedAt !== null;
    }

    /**
     * Verifie si le signataire est en attente.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
