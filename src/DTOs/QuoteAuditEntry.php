<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente une entree du journal d'audit d'un devis.
 */
readonly class QuoteAuditEntry
{
    public function __construct(
        public string $id,
        public string $event,
        public DateTimeImmutable $occurredAt,
        public ?string $actorType = null,
        public ?string $actorId = null,
        public ?string $actorName = null,
        public ?string $actorEmail = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?array $metadata = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            event: $data['event'],
            occurredAt: new DateTimeImmutable($data['occurred_at'] ?? $data['created_at']),
            actorType: $data['actor_type'] ?? null,
            actorId: $data['actor_id'] ?? null,
            actorName: $data['actor_name'] ?? null,
            actorEmail: $data['actor_email'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
