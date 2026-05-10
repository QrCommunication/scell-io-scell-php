<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\Signature;
use Scell\Sdk\Enums\SignatureStatus;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les signatures scope tenant (URL-nested).
 *
 * Disponible depuis le SDK v2.7.0. Cible les nouveaux endpoints expose par
 * le backend en authentification `tenant.key` (header `X-API-Key: sk_live_*`
 * / `sk_test_*`) sans contrainte `company_id`. Permet a un master tenant
 * de lister TOUTES les signatures (parent + tous sub-tenants) sans devoir
 * passer par une cle API attachee a une `company_id`.
 *
 * Endpoints couverts :
 * - GET /api/v1/tenant/signatures
 * - GET /api/v1/tenant/signatures/{id}
 * - GET /api/v1/tenant/sub-tenants/{subTenantId}/signatures
 * - GET /api/v1/tenant/sub-tenants/{subTenantId}/signatures/{id}
 *
 * Pour les operations write (create/remind/cancel/download/audit-trail),
 * continuer d'utiliser `SignatureResource` (endpoints sous `/v1/signatures`).
 *
 * @example
 * ```php
 * // Toutes les signatures du tenant (parent + sub-tenants)
 * $signatures = $tenant->signatures()->list([
 *     'status'      => 'completed',
 *     'environment' => 'production',
 *     'per_page'    => 50,
 * ]);
 *
 * // Detail d'une signature scope tenant (anti-IDOR cote backend)
 * $signature = $tenant->signatures()->get('signature-uuid');
 *
 * // Signatures d'un sub-tenant precis
 * $subSigs = $tenant->signatures()->listForSubTenant('sub-tenant-uuid', [
 *     'status' => 'pending',
 * ]);
 *
 * // Detail signature scope sub-tenant
 * $sig = $tenant->signatures()->getForSubTenant('sub-tenant-uuid', 'signature-uuid');
 * ```
 */
class TenantSignatureResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste toutes les signatures du tenant courant (parent + tous sub-tenants).
     *
     * Endpoint : `GET /api/v1/tenant/signatures`. Auth : `X-API-Key sk_*`
     * (middleware `tenant.key`, sans contrainte `company_id`).
     *
     * @param array{
     *     status?: SignatureStatus|string,
     *     environment?: string,
     *     per_page?: int,
     *     page?: int
     * } $filters Filtres optionnels.
     *
     * Filtres supportes :
     *  - `status` : `pending` | `completed` | `refused` | `expired`.
     *  - `environment` : `sandbox` | `production`.
     *  - `per_page` : 1 a 100 (defaut backend = 25).
     *  - `page` : numero de page Laravel (1-indexe).
     *
     * @return PaginatedResult<Signature>
     */
    public function list(array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get('tenant/signatures', $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Signature::fromArray($data));
    }

    /**
     * Recupere une signature scope tenant.
     *
     * Endpoint : `GET /api/v1/tenant/signatures/{id}`. Le backend verifie
     * que la signature appartient bien au tenant courant (404 sinon).
     *
     * @param string $signatureId UUID de la signature.
     */
    public function get(string $signatureId): Signature
    {
        $response = $this->http->get("tenant/signatures/{$signatureId}");

        return Signature::fromArray($response['data']);
    }

    /**
     * Liste les signatures d'un sub-tenant precis.
     *
     * Endpoint : `GET /api/v1/tenant/sub-tenants/{subTenantId}/signatures`.
     * Le middleware `sub-tenant` valide que le sub-tenant appartient au
     * tenant courant (anti-IDOR, 403 sinon).
     *
     * @param string $subTenantId UUID du sub-tenant.
     * @param array{
     *     status?: SignatureStatus|string,
     *     environment?: string,
     *     per_page?: int,
     *     page?: int
     * } $filters
     *
     * @return PaginatedResult<Signature>
     */
    public function listForSubTenant(string $subTenantId, array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get("tenant/sub-tenants/{$subTenantId}/signatures", $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Signature::fromArray($data));
    }

    /**
     * Recupere une signature scope sub-tenant.
     *
     * Endpoint : `GET /api/v1/tenant/sub-tenants/{subTenantId}/signatures/{id}`.
     * Anti-IDOR via middleware `sub-tenant` + scope tenant.
     *
     * @param string $subTenantId UUID du sub-tenant.
     * @param string $signatureId UUID de la signature.
     */
    public function getForSubTenant(string $subTenantId, string $signatureId): Signature
    {
        $response = $this->http->get(
            "tenant/sub-tenants/{$subTenantId}/signatures/{$signatureId}"
        );

        return Signature::fromArray($response['data']);
    }

    /**
     * Normalise les filtres de liste (enums, valeurs null filtrees).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $query = [];

        foreach ($filters as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($value instanceof SignatureStatus) {
                $query[$key] = $value->value;
            } else {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}
