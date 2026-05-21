<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\Branding;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour la configuration de marque (branding) tenant et sub-tenant.
 *
 * Le branding est applique aux emails et PDFs emis sous le perimetre
 * concerne. Si tous les champs requis sont renseignes (logo + couleur
 * primaire + footer), ce branding remplace le branding Scell.io par defaut.
 *
 * @example
 * ```php
 * $api = ScellApiClient::withApiKey('sk_live_...');
 *
 * // Configurer le branding du tenant master
 * $branding = $api->branding()->updateTenant([
 *     'primary_color'   => '#1a73e8',
 *     'email_footer'    => 'Ma Societe SAS — SIRET 123 456 789 00010',
 *     'email_signature' => 'L\'equipe Ma Societe',
 * ]);
 *
 * // Obtenir une URL presignee pour uploader le logo
 * $upload = $api->branding()->logoUploadUrlTenant('image/png');
 * // PUT le fichier sur $upload['url'] puis configurer logo_url=$upload['public_url']
 *
 * // Configurer le branding d'un sub-tenant
 * $api->branding()->updateSubTenant($subTenantId, [
 *     'logo_url'      => 'https://cdn.scell.io/logos/sub-xxx.png',
 *     'primary_color' => '#e63946',
 * ]);
 * ```
 */
class BrandingResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Recupere la configuration de marque du tenant master.
     */
    public function getTenant(): Branding
    {
        $response = $this->http->get('branding/tenant');
        return Branding::fromArray($response['data'] ?? $response);
    }

    /**
     * Met a jour la configuration de marque du tenant master.
     *
     * @param array{
     *     logo_url?: string,
     *     primary_color?: string,
     *     email_footer?: string,
     *     email_signature?: string
     * } $data
     */
    public function updateTenant(array $data): Branding
    {
        $response = $this->http->patch('branding/tenant', $data);
        return Branding::fromArray($response['data'] ?? $response);
    }

    /**
     * Recupere la configuration de marque d'un sub-tenant.
     */
    public function getSubTenant(string $subTenantId): Branding
    {
        $response = $this->http->get("branding/sub-tenants/{$subTenantId}");
        return Branding::fromArray($response['data'] ?? $response);
    }

    /**
     * Met a jour la configuration de marque d'un sub-tenant.
     *
     * @param array{
     *     logo_url?: string,
     *     primary_color?: string,
     *     email_footer?: string,
     *     email_signature?: string
     * } $data
     */
    public function updateSubTenant(string $subTenantId, array $data): Branding
    {
        $response = $this->http->patch("branding/sub-tenants/{$subTenantId}", $data);
        return Branding::fromArray($response['data'] ?? $response);
    }

    /**
     * Genere une URL presignee pour uploader le logo du tenant master.
     *
     * Workflow :
     * 1. Appeler cette methode avec le MIME type de l'image.
     * 2. PUT le fichier directement sur `$result['url']` (pas d'auth).
     * 3. Appeler updateTenant(['logo_url' => $result['public_url']]).
     *
     * @param string $mimeType Type MIME de l'image (ex: 'image/png', 'image/jpeg', 'image/svg+xml')
     * @return array{url: string, public_url: string, expires_at: string}
     */
    public function logoUploadUrlTenant(string $mimeType = 'image/png'): array
    {
        return $this->http->post('branding/tenant/logo-upload-url', ['mime_type' => $mimeType]);
    }

    /**
     * Genere une URL presignee pour uploader le logo d'un sub-tenant.
     *
     * @param string $mimeType Type MIME de l'image (ex: 'image/png', 'image/jpeg', 'image/svg+xml')
     * @return array{url: string, public_url: string, expires_at: string}
     */
    public function logoUploadUrlSubTenant(string $subTenantId, string $mimeType = 'image/png'): array
    {
        return $this->http->post(
            "branding/sub-tenants/{$subTenantId}/logo-upload-url",
            ['mime_type' => $mimeType],
        );
    }

    /**
     * Retourne un apercu HTML/PDF du rendu avec le branding tenant courant.
     *
     * @return string Contenu binaire (HTML ou PDF selon Accept header)
     */
    public function previewTenant(): string
    {
        return $this->http->getRaw('branding/tenant/preview');
    }

    /**
     * Retourne un apercu HTML/PDF du rendu avec le branding sub-tenant courant.
     *
     * @return string Contenu binaire (HTML ou PDF selon Accept header)
     */
    public function previewSubTenant(string $subTenantId): string
    {
        return $this->http->getRaw("branding/sub-tenants/{$subTenantId}/preview");
    }
}
