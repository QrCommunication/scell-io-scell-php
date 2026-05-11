<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Reponse de `SubTenantResource::superpdpAuthorize()` et payload des reponses
 * 422 `MISSING_ACCESS_TOKEN` retournees par `refreshSuperPDPStatus()` (depuis
 * v2.9.0).
 *
 * Le champ `state` est un nonce a renvoyer tel quel par SuperPDP au callback
 * OAuth (anti-CSRF). Le `authorizeUrl` est prefilled avec `login_hint` +
 * `superpdp_company_number` quand disponibles cote sub-tenant.
 */
readonly class SuperPDPAuthorizeUrl
{
    public function __construct(
        public string $authorizeUrl,
        public string $state,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            authorizeUrl: (string) $payload['authorize_url'],
            state: (string) $payload['state'],
        );
    }
}
