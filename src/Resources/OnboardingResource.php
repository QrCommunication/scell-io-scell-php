<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\CompanyData;
use Scell\Sdk\DTOs\CreateSubTenantResult;
use Scell\Sdk\DTOs\OnboardingSession;
use Scell\Sdk\DTOs\SireneLookupResult;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour l'onboarding via SuperPDP OAuth2 Authorization Code,
 * et les endpoints widget publishable-key (depuis v2.0.0).
 */
class OnboardingResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Cree une nouvelle session d'onboarding.
     *
     * @param array{
     *     mode: string,
     *     external_id?: string,
     *     callback_url?: string,
     *     state?: string,
     *     metadata?: array
     * } $data
     */
    public function createSession(array $data): OnboardingSession
    {
        $response = $this->http->post('onboarding/sessions', $data);
        return OnboardingSession::fromArray($response['data']);
    }

    public function getSession(string $sessionId): OnboardingSession
    {
        $response = $this->http->get("onboarding/sessions/{$sessionId}");
        return OnboardingSession::fromArray($response['data']);
    }

    /**
     * @return array{authorize_url: string, state: string}
     */
    public function getSuperPDPAuthorizeUrl(string $sessionId): array
    {
        return $this->http->post('onboarding/superpdp/authorize', [
            'session_id' => $sessionId,
        ]);
    }

    /**
     * @return array{message: string, data: array}
     */
    public function superpdpCallback(string $sessionId, string $code, string $state): array
    {
        return $this->http->post('onboarding/superpdp/callback', [
            'session_id' => $sessionId,
            'code' => $code,
            'state' => $state,
        ]);
    }

    // ==========================================================================
    // Widget endpoints (publishable-key auth, since v2.0.0)
    // ==========================================================================

    /**
     * Recherche d'une societe francaise par SIRET via Sirene.
     *
     * Authentification publishable-key. A appeler depuis le widget
     * partenaire pour pre-remplir le formulaire d'onboarding.
     *
     * @example
     * ```php
     * $publicClient = ScellApiClient::withApiKey('pk_live_...');
     * $result = $publicClient->onboarding()->lookupSirene('12345678901234');
     * if ($result->data !== null) {
     *     echo $result->data->name;
     * }
     * ```
     */
    public function lookupSirene(string $siret): SireneLookupResult
    {
        $payload = $this->http->post('widget/onboarding/sirene/lookup', [
            'siret' => preg_replace('/\s+/', '', $siret),
        ]);

        return SireneLookupResult::fromArray($payload);
    }

    /**
     * Cree un SubTenant depuis les donnees collectees par le widget
     * (publishable-key auth).
     *
     * @param array{
     *     external_id?: string,
     *     company: array<string, mixed>|CompanyData,
     *     identity: array{
     *         first_name: string,
     *         last_name: string,
     *         email: string,
     *         phone?: string,
     *         birth_date?: string,
     *         job_title?: string,
     *     },
     *     locale?: 'fr'|'en',
     *     metadata?: array<string, mixed>,
     * } $payload
     */
    public function createSubTenant(array $payload): CreateSubTenantResult
    {
        if (isset($payload['company']) && $payload['company'] instanceof CompanyData) {
            $payload['company'] = $payload['company']->toArray();
        }

        $response = $this->http->post('widget/onboarding/sub-tenant', $payload);

        return CreateSubTenantResult::fromArray($response);
    }
}
