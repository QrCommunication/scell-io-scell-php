<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\ResumeUrlResult;
use Scell\Sdk\DTOs\SubTenant;
use Scell\Sdk\DTOs\SubTenantSummary;
use Scell\Sdk\DTOs\SuperPDPAuthorizeUrl;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les sub-tenants (clients finaux).
 *
 * v2.0.0 : ajoute les endpoints `getSuperPDPStatus`,
 * `refreshSuperPDPStatus` et `getResumeUrl` qui exposent le cycle
 * d'onboarding SuperPDP enrichi.
 *
 * v2.9.0 : ajoute `superpdpAuthorize()` (POST `/superpdp-authorize`) qui
 * genere une URL OAuth authorize fraiche pour relancer le tunnel SuperPDP
 * d'un sub-tenant. La methode `delete()` accepte desormais une option
 * `cascade` pour supprimer en cascade les Companies du sub-tenant.
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

    /**
     * Supprime un sub-tenant.
     *
     * Depuis v2.9.0, l'option `cascade` permet de supprimer en cascade les
     * Companies du sub-tenant (utile quand le backend retourne 422 avec
     * `code = SUB_TENANT_HAS_COMPANIES`). La suppression est systematiquement
     * refusee si des factures ou avoirs existent (compliance ISCA — code
     * `SUB_TENANT_HAS_FISCAL_ENTRIES`, pas de force flag possible).
     *
     * @param array{cascade?: bool} $options
     * @return array{message?: string, companies_deleted?: int}
     *     Retourne le payload de la reponse 200 (`companies_deleted` indique
     *     le nombre de Companies supprimees quand `cascade=true`).
     *
     * @example
     * ```php
     * // Suppression simple :
     * $api->subTenants()->delete($id);
     *
     * // En cascade quand le backend retourne SUB_TENANT_HAS_COMPANIES :
     * $result = $api->subTenants()->delete($id, ['cascade' => true]);
     * echo $result['companies_deleted']; // ex: 1
     * ```
     */
    public function delete(string $id, array $options = []): array
    {
        $query = [];
        if (!empty($options['cascade'])) {
            $query['cascade'] = 'true';
        }

        return $this->http->delete("tenant/sub-tenants/{$id}", $query);
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
     *
     * Reponses 422 possibles (`Scell\Sdk\Exceptions\ScellException` avec
     * payload `$exception->getResponseBody()`) :
     *   - `RATE_LIMITED` : poll trop frequent (1 / minute).
     *   - `REFRESH_FAILED` : SuperPDP a refuse l'appel.
     *   - `MISSING_ACCESS_TOKEN` (depuis backend 2026-05-11) : aucun
     *     `access_token` SuperPDP n'est encore associe au sub-tenant. Le
     *     payload retourne contient alors `authorize_url` + `state` que
     *     le consommateur doit ouvrir pour lancer le tunnel OAuth (cf.
     *     {@see self::superpdpAuthorize()} qui regenere ces memes valeurs).
     *     ```json
     *     {
     *       "error": "Aucun access_token SuperPDP disponible pour ce sub-tenant.",
     *       "code": "MISSING_ACCESS_TOKEN",
     *       "authorize_url": "https://oauth.superpdp.tech/authorize?...",
     *       "state": "...",
     *       "message": "Aucune connexion SuperPDP active..."
     *     }
     *     ```
     */
    public function refreshSuperPDPStatus(string $id): SubTenantSummary
    {
        $response = $this->http->post("tenant/sub-tenants/{$id}/superpdp-status/refresh");

        return SubTenantSummary::fromArray($response);
    }

    /**
     * Genere une URL OAuth authorize fraiche pour (re)lancer le tunnel
     * SuperPDP d'un sub-tenant (depuis v2.9.0).
     *
     * Utilisez cette methode quand :
     *   - le sub-tenant n'a jamais authorise SuperPDP (status
     *     `pending_superpdp` ou `superpdp_failed`),
     *   - une reponse 422 `MISSING_ACCESS_TOKEN` est retournee par
     *     {@see self::refreshSuperPDPStatus()}.
     *
     * L'URL est prefilled cote backend avec `login_hint` (email) et
     * `superpdp_company_number` quand disponibles, et le `state` retourne
     * sert d'anti-CSRF pour le callback OAuth.
     *
     * @example
     * ```php
     * $authorize = $api->subTenants()->superpdpAuthorize($subTenantId);
     * // Rediriger l'utilisateur ou ouvrir une popup :
     * header('Location: ' . $authorize->authorizeUrl);
     * ```
     */
    public function superpdpAuthorize(string $id): SuperPDPAuthorizeUrl
    {
        $response = $this->http->post("tenant/sub-tenants/{$id}/superpdp-authorize");

        return SuperPDPAuthorizeUrl::fromArray($response);
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

    /**
     * Deconnecte SuperPDP d'un sub-tenant : revoque les tokens chez SuperPDP et
     * reinitialise `onboarding_status` a `pending_superpdp` (depuis v3.1.0).
     *
     * Les factures deja emises restent immuables (ISCA) ; les futures factures
     * B2B retombent en transmission papier tant que SuperPDP n'est pas reconnecte.
     * Pour relancer la connexion en un seul appel, voir {@see self::superpdpReconnect()}.
     *
     * POST /v1/tenant/sub-tenants/{id}/superpdp-disconnect
     */
    public function superpdpDisconnect(string $id): SubTenantSummary
    {
        $response = $this->http->post("tenant/sub-tenants/{$id}/superpdp-disconnect");

        return SubTenantSummary::fromArray($response);
    }

    /**
     * Force une reconnexion SuperPDP : deconnecte (revoque + reinitialise) puis
     * genere une nouvelle URL OAuth authorize prefilled (depuis v3.1.0).
     *
     * Utile apres un KYB echoue (`superpdp_failed`) ou un changement de societe.
     *
     * POST /v1/tenant/sub-tenants/{id}/superpdp-reconnect
     *
     * @example
     * ```php
     * $authorize = $api->subTenants()->superpdpReconnect($subTenantId);
     * header('Location: ' . $authorize->authorizeUrl);
     * ```
     */
    public function superpdpReconnect(string $id): SuperPDPAuthorizeUrl
    {
        $response = $this->http->post("tenant/sub-tenants/{$id}/superpdp-reconnect");

        return SuperPDPAuthorizeUrl::fromArray($response);
    }

    /**
     * Genere un jeton signe (URL signee, scopee a UN sub-tenant, TTL 24h) a
     * passer au web component `<scell-onboarding mode="superpdp" resume-token="...">`
     * (depuis v3.1.0). Le widget ouvre alors UNIQUEMENT l'etape SuperPDP sans
     * exposer l'id du sub-tenant (anti-IDOR : la signature HMAC fait foi).
     *
     * @param bool $reset Si true, deconnecte (revoque + reset) le sub-tenant
     *                    AVANT d'emettre le jeton — voie "forcer une reconnexion"
     *                    via le widget.
     *
     * POST /v1/tenant/sub-tenants/{id}/superpdp-widget-token
     *
     * @return array{resume_token: string, expires_at: string}
     */
    public function superpdpWidgetToken(string $id, bool $reset = false): array
    {
        return $this->http->post(
            "tenant/sub-tenants/{$id}/superpdp-widget-token",
            $reset ? ['reset' => true] : [],
        );
    }

    /**
     * Jauges de seuils micro-entrepreneur (FR) d'un sous-tenant.
     *
     * Retourne le CA HT cumule par categorie face aux seuils applicables
     * (franchise TVA base/majoree + plafond du regime micro), le palier
     * d'alerte atteint et une date projetee de franchissement. Les seuils
     * sont des regles datees (loi 2025-1044) resolues cote serveur.
     *
     * Information non contractuelle (champ `disclaimer`), pas un conseil fiscal.
     *
     * GET /v1/tenant/sub-tenants/{id}/thresholds
     *
     * @return array{
     *     data: array{
     *         sub_tenant_id: string,
     *         tenant_id: string,
     *         fiscal_year: int,
     *         generated_at: string,
     *         gauges: array<int, array{category: string, kind: string, revenue: float, threshold: float, percent: float, level: ?string, actionable: bool, projected_crossing_date: ?string}>,
     *         new_alerts: array<int, array<string, mixed>>
     *     },
     *     disclaimer: string
     * }
     */
    public function getThresholds(string $id): array
    {
        return $this->http->get("tenant/sub-tenants/{$id}/thresholds");
    }

    /**
     * Met a jour le profil fiscal declare d'un sous-tenant (regime, statut TVA,
     * type d'activite, date de debut d'activite, numero de TVA).
     *
     * Passer `vat_status = 'liable'` bascule la facturation Scell vers la TVA
     * (les factures suivantes portent la TVA et n'affichent plus la mention de
     * franchise art. 293 B). Un numero de TVA devient alors requis (dans le
     * payload ou deja present sur le sous-tenant), sinon l'API repond 422.
     *
     * PATCH /v1/tenant/sub-tenants/{id}/fiscal-status
     *
     * @param array{fiscal_regime?: string, vat_status?: string, activity_type?: string, activity_start_date?: ?string, vat_number?: ?string} $data
     */
    public function updateFiscalStatus(string $id, array $data): SubTenant
    {
        $response = $this->http->patch("tenant/sub-tenants/{$id}/fiscal-status", $data);

        return SubTenant::fromArray($response['data']);
    }

    /**
     * Simulateur pré-émission : projette les jauges de seuils SI une facture
     * hypothétique de `amount` (HT) était émise dans `category`. Le
     * `level`/`actionable` des jauges reflète l'état POST-facture, permettant de
     * vérifier un franchissement AVANT d'émettre. Lecture seule (ne persiste rien).
     *
     * POST /v1/tenant/sub-tenants/{id}/thresholds/simulate
     *
     * @param array{amount: float|int, category: string} $data  category : goods|service|accommodation
     * @return array{data: array<string, mixed>, simulated: array{amount: float, category: string}, disclaimer: string}
     */
    public function simulateThresholds(string $id, array $data): array
    {
        return $this->http->post("tenant/sub-tenants/{$id}/thresholds/simulate", $data);
    }
}
