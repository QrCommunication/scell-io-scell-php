<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\OnboardingSession;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour l'onboarding via SuperPDP OAuth2 Authorization Code.
 *
 * Flux en 3 etapes :
 * 1. `createSession()` — Initialise la session d'onboarding
 * 2. `getSuperPDPAuthorizeUrl()` — Obtient l'URL de redirection OAuth2 SuperPDP
 * 3. `superpdpCallback()` — Echange le code OAuth2 et finalise l'onboarding
 *
 * @example
 * ```php
 * $api = ScellApiClient::withApiKey('pk_live_...');
 *
 * // Etape 1 : creer la session
 * $session = $api->onboarding()->createSession([
 *     'mode' => 'redirect',
 *     'callback_url' => 'https://myapp.com/onboarding/callback',
 *     'external_id' => 'user_123',
 * ]);
 *
 * // Etape 2 : obtenir l'URL d'autorisation SuperPDP
 * $result = $api->onboarding()->getSuperPDPAuthorizeUrl($session->id);
 * header('Location: ' . $result['authorize_url']);
 *
 * // Etape 3 : apres redirection OAuth2, finaliser
 * $result = $api->onboarding()->superpdpCallback(
 *     sessionId: $session->id,
 *     code: $_GET['code'],
 *     state: $_GET['state'],
 * );
 * ```
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

    /**
     * Recupere une session d'onboarding par son ID.
     */
    public function getSession(string $sessionId): OnboardingSession
    {
        $response = $this->http->get("onboarding/sessions/{$sessionId}");
        return OnboardingSession::fromArray($response['data']);
    }

    /**
     * Obtient l'URL d'autorisation OAuth2 SuperPDP pour la session.
     *
     * Redirigez l'utilisateur vers `authorize_url` pour demarrer le flux OAuth2.
     *
     * @param string $sessionId UUID de la session d'onboarding
     * @return array{authorize_url: string, state: string}
     */
    public function getSuperPDPAuthorizeUrl(string $sessionId): array
    {
        return $this->http->post('onboarding/superpdp/authorize', [
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Finalise l'onboarding apres le retour OAuth2 SuperPDP.
     *
     * A appeler avec les parametres `code` et `state` recus depuis la redirection OAuth2.
     *
     * @param string $sessionId UUID de la session d'onboarding
     * @param string $code Code d'autorisation OAuth2 retourne par SuperPDP
     * @param string $state Parametre state CSRF retourne par SuperPDP
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
}
