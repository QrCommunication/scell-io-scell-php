<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\Http\HttpClient;

/**
 * Assistant de paramétrage des mentions légales de facture (scope tenant).
 *
 * Deux endpoints stateless qui n'écrivent rien :
 * - {@see assistant()} : propose les textes de mentions par champ à partir
 *   d'un profil (TVA, clientèle, délai de paiement, pénalités…).
 * - {@see preview()} : aperçu temps réel des mentions automatiques en
 *   surchargeant (sans persister) la Company émettrice par défaut du tenant.
 *
 * La logique légale vit côté serveur (résolveur unique) — aucune duplication
 * ni drift côté client.
 *
 * @example
 * ```php
 * $suggestion = $client->invoiceMentions()->assistant([
 *     'vat_profile' => 'vat_liable',
 *     'clientele' => 'mixed',
 *     'payment_due_days' => 30,
 *     'penalty_mode' => 'legal',
 * ]);
 *
 * $preview = $client->invoiceMentions()->preview([
 *     'payment_due_days' => 30,
 *     'name' => 'Ma Société',
 *     'vat_regime' => 'franchise',
 * ]);
 * echo $preview['invoice_footer'];
 * ```
 *
 * @since 3.5.0
 */
class InvoiceMentionsResource
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Propose les textes de mentions de facture par champ.
     *
     * Chaque valeur peut être `null` (« laisser vide, défaut légal auto »).
     * La réponse inclut aussi des codes warnings/infos traduits côté frontend.
     *
     * Erreur API possible : 422 `NO_ISSUER_COMPANY` si aucune entreprise
     * émettrice n'est configurée pour le tenant.
     *
     * @param array{
     *     vat_profile: 'franchise'|'vat_liable',
     *     clientele: 'b2b'|'b2c'|'mixed',
     *     payment_due_days: int,
     *     penalty_mode: 'legal'|'custom',
     *     penalty_rate?: float|null,
     *     discount_rate?: float|null,
     *     insurance_text?: string|null,
     *     mediator_text?: string|null
     * } $params Profil de mentions à proposer
     * @return array<string, mixed> Données proposées (textes par champ + warnings/infos)
     *
     * @throws \Scell\Sdk\Exceptions\ScellException 422 si validation / pas d'émetteur
     */
    public function assistant(array $params): array
    {
        $response = $this->http->post('invoice-mentions/assistant', $params);

        /** @var array<string, mixed> $data */
        $data = $response['data'] ?? [];

        return $data;
    }

    /**
     * Aperçu temps réel des mentions automatiques pendant la saisie du profil.
     *
     * Les champs fournis surchargent (sans persister) la Company émettrice par
     * défaut du tenant. La composition reste celle du résolveur serveur (footer,
     * conditions de paiement B2B/B2C, mention de franchise en base de TVA).
     *
     * Erreur API possible : 422 `NO_ISSUER_COMPANY` si aucune entreprise
     * émettrice n'est configurée pour le tenant.
     *
     * @param array{
     *     payment_due_days?: int,
     *     name?: string,
     *     siret?: string,
     *     vat_number?: string,
     *     address_line1?: string,
     *     postal_code?: string,
     *     city?: string,
     *     country?: string,
     *     phone?: string,
     *     legal_form?: string,
     *     legal_category?: string,
     *     share_capital?: float|int|string,
     *     rcs_city?: string,
     *     rm_department?: string,
     *     vat_regime?: string,
     *     vat_basis?: string,
     *     early_payment_discount_rate?: float|int
     * } $params Surcharges non persistées de la Company émettrice
     * @return array{
     *     invoice_footer: string|null,
     *     payment_terms_b2b: string|null,
     *     payment_terms_b2c: string|null,
     *     vat_franchise_mention: string|null
     * } Mentions composées par le résolveur serveur
     *
     * @throws \Scell\Sdk\Exceptions\ScellException 422 si pas d'émetteur
     */
    public function preview(array $params): array
    {
        $response = $this->http->post('invoice-mentions/preview', $params);

        /** @var array{invoice_footer: string|null, payment_terms_b2b: string|null, payment_terms_b2c: string|null, vat_franchise_mention: string|null} $data */
        $data = $response['data'] ?? [];

        return $data;
    }
}
