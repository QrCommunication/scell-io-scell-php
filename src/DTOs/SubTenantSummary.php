<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Reponse enrichie retournee par les endpoints
 * `getSuperPDPStatus` / `refreshSuperPDPStatus` (depuis v2.0.0).
 *
 * Combine le SubTenant et l'action recommandee i18n a afficher dans
 * l'UI partenaire.
 */
readonly class SubTenantSummary
{
    public function __construct(
        public SubTenant $subTenant,
        public ?RecommendedAction $recommendedAction = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $payload = $data['data'] ?? $data;
        $action = $data['recommended_action'] ?? null;

        return new self(
            subTenant: SubTenant::fromArray($payload),
            recommendedAction: is_array($action) ? RecommendedAction::fromArray($action) : null,
        );
    }
}
