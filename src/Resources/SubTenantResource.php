<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\ResumeUrlResult;
use Scell\Sdk\DTOs\SubTenant;
use Scell\Sdk\DTOs\SubTenantSummary;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les sub-tenants (clients finaux).
 *
 * v2.0.0 : ajoute les endpoints `getSuperPDPStatus`,
 * `refreshSuperPDPStatus` et `getResumeUrl` qui exposent le cycle
 * d'onboarding SuperPDP enrichi.
 */
class SubTenantResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les sub-tenants avec pagination et filtres.
     *
     * @param array{
     *     search?: string,
     *     onboarding_status?: string,
     *     per_page?: int,
     *     page?: int,
     *     sort?: string,
     *     order?: string
     * } $filters
     * @return PaginatedResult<SubTenant>
     */
    public function list(array $filters = []): PaginatedResult
    {
        $response = $this->http->get('tenant/sub-tenants', $filters);

        return PaginatedResult::fromArray($response, fn(array $data) => SubTenant::fromArray($data));
    }

    /**
     * Cree un nouveau sub-tenant (server-to-server).
     *
     * @param array{
     *     external_id?: string,
     *     name: string,
     *     siret?: string,
     *     siren?: string,
     *     vat_number?: string,
     *     email?: string,
     *     phone?: string,
     *     contact_first_name?: string,
     *     contact_last_name?: string,
     *     address_line1?: string,
     *     address_line2?: string,
     *     postal_code?: string,
     *     city?: string,
     *     country?: string,
     *     metadata?: array
     * } $data
     */
    public function create(array $data): SubTenant
    {
        $response = $this->http->post('tenant/sub-tenants', $data);

        return SubTenant::fromArray($response['data']);
    }

    public function get(string $id): SubTenant
    {
        $response = $this->http->get("tenant/sub-tenants/{$id}");

        return SubTenant::fromArray($response['data']);
    }

    /**
     * Met a jour un sub-tenant.
     *
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): SubTenant
    {
        $response = $this->http->put("tenant/sub-tenants/{$id}", $data);

        return SubTenant::fromArray($response['data']);
    }

    public function delete(string $id): void
    {
        $this->http->delete("tenant/sub-tenants/{$id}");
    }

    public function findByExternalId(string $externalId): SubTenant
    {
        $response = $this->http->get("tenant/sub-tenants/by-external-id/{$externalId}");

        return SubTenant::fromArray($response['data']);
    }

    // ==========================================================================
    // SuperPDP onboarding status (since v2.0.0)
    // ==========================================================================

    /**
     * Recupere le statut SuperPDP en cache pour un sub-tenant, plus
     * l'action recommandee i18n a afficher dans l'UI partenaire.
     *
     * @example
     * ```php
     * $summary = $api->subTenants()->getSuperPDPStatus($id);
     * echo $summary->subTenant->onboardingStatus->value;
     * if ($summary->recommendedAction) {
     *     echo $summary->recommendedAction->title('fr');
     * }
     * ```
     */
    public function getSuperPDPStatus(string $id): SubTenantSummary
    {
        $response = $this->http->get("tenant/sub-tenants/{$id}/superpdp-status");

        return SubTenantSummary::fromArray($response);
    }

    /**
     * Force un poll SuperPDP frais pour un sub-tenant.
     *
     * Rate-limite cote serveur a 1 requete / minute / sub-tenant. Une
     * reponse 429 est exposee comme `Scell\Sdk\Exceptions\RateLimitException`.
     */
    public function refreshSuperPDPStatus(string $id): SubTenantSummary
    {
        $response = $this->http->post("tenant/sub-tenants/{$id}/superpdp-status/refresh");

        return SubTenantSummary::fromArray($response);
    }

    /**
     * Regenere l'URL signee permettant au sub-tenant de reprendre son
     * onboarding (valide 7 jours).
     */
    public function getResumeUrl(string $id): ResumeUrlResult
    {
        $response = $this->http->post("tenant/sub-tenants/{$id}/resume-url");

        return ResumeUrlResult::fromArray($response);
    }
}
