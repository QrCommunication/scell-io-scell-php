<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\InvoiceTemplate;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Http\HttpClient;

/**
 * CRUD des templates de personnalisation factures / avoirs.
 *
 * @example
 * ```php
 * $tpl = $client->invoiceTemplates()->create([
 *     'scope' => 'tenant',
 *     'name' => 'Brand Q4 2026',
 *     'logo_url' => 'https://cdn.client.com/logo.svg',
 *     'primary_color' => '#0F172A',
 *     'is_default' => true,
 *     'is_available_to_subtenants' => true,
 * ]);
 * ```
 */
class InvoiceTemplateResource
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Liste les templates accessibles au contexte courant.
     *
     * @return PaginatedResult<InvoiceTemplate>
     */
    public function list(array $filters = []): PaginatedResult
    {
        $response = $this->http->get('invoice-templates', $filters);
        return PaginatedResult::fromArray($response, fn (array $row) => InvoiceTemplate::fromArray($row));
    }

    public function get(string $id): InvoiceTemplate
    {
        $response = $this->http->get("invoice-templates/{$id}");
        return InvoiceTemplate::fromArray($response['data']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): InvoiceTemplate
    {
        $response = $this->http->post('invoice-templates', $data);
        return InvoiceTemplate::fromArray($response['data']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): InvoiceTemplate
    {
        $response = $this->http->patch("invoice-templates/{$id}", $data);
        return InvoiceTemplate::fromArray($response['data']);
    }

    public function delete(string $id): void
    {
        $this->http->delete("invoice-templates/{$id}");
    }

    /**
     * Marque un template comme default pour son scope.
     */
    public function markDefault(string $id): InvoiceTemplate
    {
        $response = $this->http->put("invoice-templates/{$id}/default");
        return InvoiceTemplate::fromArray($response['data']);
    }

    /**
     * Upload un logo pour le template (multipart S3).
     *
     * Formats acceptes : jpeg, png, webp, svg/svgz. Max 2MB.
     * Le logo est stocke sur S3 avec ACL public, scope par tenant.
     *
     * @param string $id Template UUID
     * @param resource|string $logo Resource (fopen) ou path (string) du fichier
     * @param string|null $filename Filename optionnel (default: extrait du path)
     * @return InvoiceTemplate Le template avec le nouveau logo_url
     *
     * @example
     * ```php
     * // Depuis un path
     * $tpl = $client->invoiceTemplates()->uploadLogo($id, '/path/to/logo.png');
     *
     * // Depuis une resource
     * $fp = fopen('/path/to/logo.svg', 'rb');
     * $tpl = $client->invoiceTemplates()->uploadLogo($id, $fp, 'logo.svg');
     * ```
     *
     * @since 1.18.0
     */
    public function uploadLogo(string $id, $logo, ?string $filename = null): InvoiceTemplate
    {
        // Si on recoit un path string, ouvrir la resource
        if (is_string($logo)) {
            $filename ??= basename($logo);
            $logo = fopen($logo, 'rb');
            if ($logo === false) {
                throw new \RuntimeException("Impossible d'ouvrir le fichier logo");
            }
        }

        $response = $this->http->postMultipart("invoice-templates/{$id}/logo", [[
            'name' => 'logo',
            'contents' => $logo,
            'filename' => $filename ?? 'logo',
        ]]);

        return InvoiceTemplate::fromArray($response['data']);
    }

    /**
     * Deduit les couleurs primaire/accent du logo e-mail du tenant et les
     * applique au template de facture par defaut (cree a la volee si absent).
     *
     * Erreurs API possibles :
     * - 404 si aucun logo e-mail n'est configure sur le tenant.
     * - 422 si le logo est inaccessible ou si ses couleurs sont trop neutres.
     *
     * @return InvoiceTemplate Le template par defaut avec les couleurs derivees
     *
     * @example
     * ```php
     * $tpl = $client->invoiceTemplates()->deriveColorsFromEmailLogo();
     * echo $tpl->primaryColor; // ex: '#0F4C81'
     * ```
     *
     * @since 3.4.0
     */
    public function deriveColorsFromEmailLogo(): InvoiceTemplate
    {
        $response = $this->http->post('invoice-templates/derive-colors-from-email-logo');

        return InvoiceTemplate::fromArray($response['data']);
    }
}
