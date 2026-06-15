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

    /**
     * Deduit les couleurs primaire/accent du logo de FACTURE du modele par
     * defaut du tenant et retourne la palette SANS persister.
     *
     * Contrairement a {@see deriveColorsFromEmailLogo()} (qui applique la
     * palette au modele), cet endpoint sert a la mise a jour LIVE d'un
     * formulaire de branding facture : l'utilisateur ajuste puis sauvegarde
     * lui-meme via {@see update()}.
     *
     * Erreurs API possibles :
     * - 404 si le tenant est introuvable ou si aucun logo de facture n'est importe.
     * - 422 si le logo est inaccessible ou si ses couleurs sont trop neutres.
     *
     * @return array{primary_color: string, accent_color: string} Palette derivee (non persistee)
     *
     * @example
     * ```php
     * $palette = $client->invoiceTemplates()->deriveColorsFromInvoiceLogo();
     * echo $palette['primary_color']; // ex: '#0F4C81'
     * echo $palette['accent_color'];  // ex: '#E8B647'
     * ```
     *
     * @since 3.5.0
     */
    public function deriveColorsFromInvoiceLogo(): array
    {
        $response = $this->http->post('invoice-templates/derive-colors-from-invoice-logo');

        /** @var array{primary_color: string, accent_color: string} $palette */
        $palette = $response['data'];

        return $palette;
    }

    /**
     * Apercu d'une facture-echantillon rendue avec un branding donne (HTML ou PDF).
     *
     * Tout champ de branding absent retombe sur le modele par defaut resolu du
     * tenant (cascade explicit > sub_tenant > tenant > system). Permet une
     * previsualisation live des modifications non encore sauvegardees.
     *
     * Retourne le contenu BRUT : HTML (`text/html`, defaut) ou PDF binaire
     * (`application/pdf`) selon `format`. Pour le HTML, injecter le resultat
     * dans un `<iframe srcdoc="...">`.
     *
     * Erreur API possible : 422 si une couleur hex ou l'URL du logo est invalide.
     *
     * @param array{
     *     primary_color?: string,
     *     accent_color?: string,
     *     text_color?: string,
     *     background_color?: string,
     *     logo_url?: string,
     *     header_text?: string,
     *     footer_text?: string,
     *     format?: 'html'|'pdf'
     * } $params Overrides de branding (non persistes) + format de sortie
     * @return string Contenu brut : HTML (defaut) ou PDF binaire
     *
     * @example
     * ```php
     * // Apercu HTML pour un iframe
     * $html = $client->invoiceTemplates()->preview([
     *     'primary_color' => '#0F4C81',
     *     'logo_url' => 'https://cdn.client.com/logo.svg',
     * ]);
     *
     * // Apercu PDF binaire
     * $pdf = $client->invoiceTemplates()->preview(['format' => 'pdf']);
     * file_put_contents('apercu.pdf', $pdf);
     * ```
     *
     * @since 3.5.0
     */
    public function preview(array $params = []): string
    {
        return $this->http->getRaw('invoice-templates/preview', $params);
    }
}
