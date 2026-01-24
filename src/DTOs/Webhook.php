<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;
use Scell\Sdk\Enums\Environment;
use Scell\Sdk\Enums\WebhookEvent;

/**
 * Represente une configuration de webhook.
 */
readonly class Webhook
{
    /**
     * @param string[] $events
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $id,
        public string $url,
        public array $events,
        public bool $isActive,
        public Environment $environment,
        public int $retryCount,
        public int $timeoutSeconds,
        public int $failureCount,
        public ?string $companyId = null,
        public ?string $secret = null,
        public array $headers = [],
        public ?DateTimeImmutable $lastTriggeredAt = null,
        public ?DateTimeImmutable $lastSuccessAt = null,
        public ?DateTimeImmutable $lastFailureAt = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            url: $data['url'],
            events: $data['events'],
            isActive: $data['is_active'] ?? true,
            environment: Environment::from($data['environment']),
            retryCount: (int) ($data['retry_count'] ?? 3),
            timeoutSeconds: (int) ($data['timeout_seconds'] ?? 30),
            failureCount: (int) ($data['failure_count'] ?? 0),
            companyId: $data['company_id'] ?? null,
            secret: $data['secret'] ?? null,
            headers: $data['headers'] ?? [],
            lastTriggeredAt: isset($data['last_triggered_at']) ? new DateTimeImmutable($data['last_triggered_at']) : null,
            lastSuccessAt: isset($data['last_success_at']) ? new DateTimeImmutable($data['last_success_at']) : null,
            lastFailureAt: isset($data['last_failure_at']) ? new DateTimeImmutable($data['last_failure_at']) : null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
        );
    }

    /**
     * Verifie si le webhook est en mode sandbox.
     */
    public function isSandbox(): bool
    {
        return $this->environment->isSandbox();
    }

    /**
     * Verifie si le webhook ecoute un evenement specifique.
     */
    public function listensTo(WebhookEvent|string $event): bool
    {
        $eventValue = $event instanceof WebhookEvent ? $event->value : $event;
        return in_array($eventValue, $this->events);
    }

    /**
     * Verifie si le webhook a des echecs recents.
     */
    public function hasRecentFailures(): bool
    {
        return $this->failureCount > 0;
    }

    /**
     * Verifie si le webhook est en bonne sante.
     */
    public function isHealthy(): bool
    {
        return $this->isActive && $this->failureCount < 3;
    }
}
