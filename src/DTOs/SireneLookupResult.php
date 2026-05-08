<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Reponse de `OnboardingResource::lookupSirene()` (depuis v2.0.0).
 *
 * - $data = null && $sireneLookupSucceeded = true : Sirene a repondu sans match
 * - $data = null && $sireneLookupSucceeded = false : Sirene indisponible / rate-limit
 */
readonly class SireneLookupResult
{
    public function __construct(
        public ?CompanyData $data,
        public bool $sireneLookupSucceeded,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = $payload['data'] ?? null;

        return new self(
            data: is_array($data) ? CompanyData::fromArray($data) : null,
            sireneLookupSucceeded: (bool) ($payload['sirene_lookup_succeeded'] ?? false),
        );
    }
}
