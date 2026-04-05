<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente une session d'onboarding SuperPDP OAuth2.
 */
readonly class OnboardingSession
{
    public function __construct(
        public string $id,
        public string $sessionToken,
        public string $mode,
        public string $status,
        public ?string $externalId = null,
        public ?string $callbackUrl = null,
        public ?string $state = null,
        public ?array $metadata = null,
        public ?string $ipAddress = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?DateTimeImmutable $completedAt = null,
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
            sessionToken: $data['session_token'],
            mode: $data['mode'],
            status: $data['status'],
            externalId: $data['external_id'] ?? null,
            callbackUrl: $data['callback_url'] ?? null,
            state: $data['state'] ?? null,
            metadata: $data['metadata'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            expiresAt: isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null,
            completedAt: isset($data['completed_at']) ? new DateTimeImmutable($data['completed_at']) : null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
        );
    }

    /**
     * Verifie si la session est active (non expiree, non terminee).
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['initiated', 'in_progress'], true);
    }

    /**
     * Verifie si la session est terminee avec succes.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Verifie si la session a expire.
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expiresAt !== null && $this->expiresAt < new DateTimeImmutable());
    }
}
