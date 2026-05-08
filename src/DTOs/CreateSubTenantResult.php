<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Reponse de `OnboardingResource::createSubTenant()` (depuis v2.0.0).
 *
 * Combine le SubTenant cree, l'action recommandee i18n et l'URL de
 * reprise signee (valide 7 jours).
 */
readonly class CreateSubTenantResult
{
    public function __construct(
        public SubTenant $subTenant,
        public ?RecommendedAction $recommendedAction = null,
        public ?string $resumeUrl = null,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $sub = $payload['data'] ?? $payload;
        $action = $payload['recommended_action'] ?? null;

        return new self(
            subTenant: SubTenant::fromArray($sub),
            recommendedAction: is_array($action) ? RecommendedAction::fromArray($action) : null,
            resumeUrl: $payload['resume_url'] ?? null,
        );
    }
}
