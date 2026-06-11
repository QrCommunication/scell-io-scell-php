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
     * Le tableau retourne contient :
     * - `url`         : URL S3 pre-signee a laquelle PUT le binaire du logo.
     * - `public_url`  : URL publique finale du logo (a persister via updateTenant).
     * - `expires_at`  : expiration de l'URL pre-signee (ISO 8601).
     * - `headers`     : en-tetes a renvoyer tels quels dans la requete PUT
     *                   (ex: `Content-Type`). Optionnel selon la config S3.
     *
     * @param string $mimeType Type MIME de l'image (ex: 'image/png', 'image/jpeg', 'image/svg+xml')
     * @return array{url: string, public_url: string, expires_at: string, headers?: array<string, string>}
     */
    public function logoUploadUrlTenant(string $mimeType = 'image/png'): array
    {
        return $this->http->post('branding/tenant/logo-upload-url', ['mime_type' => $mimeType]);
    }

    /**
     * Genere une URL presignee pour uploader le logo d'un sub-tenant.
     *
     * Memes champs de reponse que {@see logoUploadUrlTenant()}
     * (`url`, `public_url`, `expires_at`, `headers`).
     *
     * @param string $mimeType Type MIME de l'image (ex: 'image/png', 'image/jpeg', 'image/svg+xml')
     * @return array{url: string, public_url: string, expires_at: string, headers?: array<string, string>}
     */
    public function logoUploadUrlSubTenant(string $subTenantId, string $mimeType = 'image/png'): array
    {
        return $this->http->post(
            "branding/sub-tenants/{$subTenantId}/logo-upload-url",
            ['mime_type' => $mimeType],
        );
    }

    /**
     * Upload DIRECT du logo e-mail du tenant master (multipart).
     *
     * Alternative en un seul appel au flux presigned
     * {@see logoUploadUrlTenant()} : le binaire est envoye directement a
     * l'API qui le stocke et persiste le `logo_url` resultant.
     *
     * Formats acceptes : jpeg, png, webp, svg/svgz. Max 2 Mo.
     *
     * @param resource|string $logo Resource (fopen) ou path (string) du fichier
     * @param string|null $filename Filename optionnel (default: extrait du path)
     * @return Branding La configuration de marque avec le nouveau logo_url
     *
     * @example
     * ```php
     * // Depuis un path
     * $branding = $api->branding()->uploadLogoTenant('/path/to/logo.png');
     *
     * // Depuis une resource
     * $fp = fopen('/path/to/logo.svg', 'rb');
     * $branding = $api->branding()->uploadLogoTenant($fp, 'logo.svg');
     * ```
     *
     * @since 3.4.0
     */
    public function uploadLogoTenant($logo, ?string $filename = null): Branding
    {
        // Si on recoit un path string, ouvrir la resource
        if (is_string($logo)) {
            $filename ??= basename($logo);
            $logo = fopen($logo, 'rb');
            if ($logo === false) {
                throw new \RuntimeException("Impossible d'ouvrir le fichier logo");
            }
        }

        $response = $this->http->postMultipart('branding/tenant/logo', [[
            'name' => 'logo',
            'contents' => $logo,
            'filename' => $filename ?? 'logo',
        ]]);

        return Branding::fromArray($response['data'] ?? $response);
    }

    /**
     * Upload DIRECT du logo e-mail d'un sub-tenant (multipart).
     *
     * Le sub-tenant doit appartenir au tenant courant (404 anti-IDOR cote
     * serveur). Memes formats et limites que {@see uploadLogoTenant()}.
     *
     * @param resource|string $logo Resource (fopen) ou path (string) du fichier
     * @param string|null $filename Filename optionnel (default: extrait du path)
     * @return Branding La configuration de marque avec le nouveau logo_url
     *
     * @since 3.4.0
     */
    public function uploadLogoSubTenant(string $subTenantId, $logo, ?string $filename = null): Branding
    {
        // Si on recoit un path string, ouvrir la resource
        if (is_string($logo)) {
            $filename ??= basename($logo);
            $logo = fopen($logo, 'rb');
            if ($logo === false) {
                throw new \RuntimeException("Impossible d'ouvrir le fichier logo");
            }
        }

        $response = $this->http->postMultipart("branding/sub-tenants/{$subTenantId}/logo", [[
            'name' => 'logo',
            'contents' => $logo,
            'filename' => $filename ?? 'logo',
        ]]);

        return Branding::fromArray($response['data'] ?? $response);
    }

    /**
     * Retourne l'apercu de l'email brande du tenant courant, tel qu'il
     * apparaitra au destinataire — AVANT tout envoi.
     *
     * Par defaut le rendu est en HTML (`text/html`), ideal a injecter dans
     * un `<iframe srcdoc="...">` pour une previsualisation live. Envoyer un
     * header `Accept: application/pdf` pour obtenir le rendu PDF.
     *
     * Depuis la v3.4.0, des overrides NON persistes peuvent etre passes en
     * query string pour previsualiser un branding avant de l'enregistrer :
     * `brand_primary_color` (#RRGGBB), `brand_email_footer` (<= 2000),
     * `brand_email_signature` (<= 1000), `brand_logo_url` (<= 2048).
     *
     * @param array{
     *     brand_primary_color?: string,
     *     brand_email_footer?: string,
     *     brand_email_signature?: string,
     *     brand_logo_url?: string
     * } $overrides Overrides de previsualisation (non persistes)
     * @return string Contenu brut (HTML par defaut, ou PDF selon le header Accept)
     */
    public function previewTenant(array $overrides = []): string
    {
        return $this->http->getRaw('branding/tenant/preview', $overrides);
    }

    /**
     * Retourne l'apercu de l'email brande d'un sub-tenant, tel qu'il
     * apparaitra au destinataire — AVANT tout envoi. Le sub-tenant doit
     * appartenir au tenant courant (anti-IDOR cote serveur).
     *
     * Memes regles de format que {@see previewTenant()} (HTML par defaut,
     * PDF via `Accept: application/pdf`) et memes overrides de
     * previsualisation NON persistes (depuis v3.4.0).
     *
     * @param array{
     *     brand_primary_color?: string,
     *     brand_email_footer?: string,
     *     brand_email_signature?: string,
     *     brand_logo_url?: string
     * } $overrides Overrides de previsualisation (non persistes)
     * @return string Contenu brut (HTML par defaut, ou PDF selon le header Accept)
     */
    public function previewSubTenant(string $subTenantId, array $overrides = []): string
    {
        return $this->http->getRaw("branding/sub-tenants/{$subTenantId}/preview", $overrides);
    }
}
