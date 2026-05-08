<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Reponse de `SubTenantResource::getResumeUrl()` (depuis v2.0.0).
 */
readonly class ResumeUrlResult
{
    public function __construct(
        public string $resumeUrl,
        public DateTimeImmutable $expiresAt,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            resumeUrl: (string) $payload['resume_url'],
            expiresAt: new DateTimeImmutable((string) $payload['expires_at']),
        );
    }
}
