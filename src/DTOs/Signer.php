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
    /**
     * @param string|null $message Message custom envoye au signataire (max 500 chars).
     *                             Supporte le placeholder `{OTP}` qui sera remplace par le code OTP.
     */
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
        public ?string $message = null,
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
            message: $data['message'] ?? null,
        );
    }

    /**
     * Cree un nouveau signataire pour une demande de signature.
     *
     * @param string|null $message Message custom (max 500 chars). Placeholder `{OTP}` supporte.
     */
    public static function create(
        string $firstName,
        string $lastName,
        AuthMethod $authMethod,
        ?string $email = null,
        ?string $phone = null,
        ?string $message = null,
    ): self {
        return new self(
            id: null,
            firstName: $firstName,
            lastName: $lastName,
            authMethod: $authMethod,
            email: $email,
            phone: $phone,
            message: $message,
        );
    }

    /**
     * Retourne une copie du signataire avec un message custom.
     *
     * @param string $message Message max 500 chars, placeholder `{OTP}` supporte.
     */
    public function withMessage(string $message): self
    {
        return new self(
            id: $this->id,
            firstName: $this->firstName,
            lastName: $this->lastName,
            authMethod: $this->authMethod,
            email: $this->email,
            phone: $this->phone,
            status: $this->status,
            signingUrl: $this->signingUrl,
            signedAt: $this->signedAt,
            refusedAt: $this->refusedAt,
            message: $message,
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
            'message' => $this->message,
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
