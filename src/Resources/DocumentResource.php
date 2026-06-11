<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les apercus de documents (factures, avoirs, devis).
 *
 * Permet de generer un apercu HTML NON persiste d'un document en cours de
 * saisie, rendu avec le vrai template + branding + mentions de la Company
 * emettrice. Ideal pour une previsualisation live dans un formulaire de
 * creation (injecter le HTML dans un `<iframe srcdoc="...">`).
 *
 * @example
 * ```php
 * $html = $client->documents()->preview([
 *     'type' => 'invoice',
 *     'document_number' => 'FA-2026-0042',
 *     'buyer' => [
 *         'name' => 'Client SA',
 *         'siret' => '98765432109876',
 *         'address' => ['line1' => '1 rue de la Paix', 'postal_code' => '75002', 'city' => 'Paris'],
 *     ],
 *     'lines' => [
 *         ['description' => 'Prestation', 'quantity' => 1, 'unit_price' => 1000.00, 'tax_rate' => 20.0],
 *     ],
 *     'issue_date' => '2026-06-11',
 *     'due_date' => '2026-07-11',
 * ]);
 * ```
 *
 * @since 3.4.0
 */
class DocumentResource
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Genere un apercu HTML non persiste d'un document en cours de saisie.
     *
     * Tous les champs sont optionnels sauf `type`. Le rendu utilise le vrai
     * template + branding + mentions de la Company emettrice du contexte
     * d'authentification courant. Aucune donnee n'est persistee.
     *
     * Erreur API possible : 422 si le payload est invalide.
     *
     * @param array{
     *     type: 'invoice'|'credit_note'|'quote',
     *     document_number?: string,
     *     buyer?: array{
     *         name?: string,
     *         siret?: string,
     *         vat_number?: string,
     *         email?: string,
     *         phone?: string,
     *         is_individual?: bool,
     *         address?: array{line1?: string, line2?: string, postal_code?: string, city?: string, country?: string}
     *     },
     *     lines?: array<int, array{description?: string, quantity?: float|int, unit_price?: float|int, tax_rate?: float|int}>,
     *     issue_date?: string,
     *     due_date?: string,
     *     currency?: string,
     *     notes?: string,
     *     payment_terms?: string
     * } $payload Document en cours de saisie (`type` requis)
     * @return string Contenu HTML brut de l'apercu (`text/html`)
     */
    public function preview(array $payload): string
    {
        return $this->http->postRaw('documents/preview', $payload);
    }
}
