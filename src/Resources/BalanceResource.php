<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\DTOs\Balance;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\Transaction;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour le solde et les transactions (LEGACY).
 *
 * @deprecated since 2.24.0. Tous les endpoints `/api/v1/balance/*` ont ete
 * supprimes cote backend Scell.io le 2026-05-10. Tout appel via cette resource
 * provoque maintenant un 404 silencieux suivi d'une `ScellException`.
 *
 * **Migration obligatoire vers `BillingResource`** :
 *
 * | Ancien BalanceResource                            | Nouveau BillingResource                                |
 * |---------------------------------------------------|--------------------------------------------------------|
 * | `$client->balance()->get()`                       | `$client->billing()->usage()`                          |
 * | `$client->balance()->reload($amount)`             | `$client->billing()->topUp(['amount_eur' => $amount])` |
 * | `$client->balance()->updateSettings([...])`       | (supprime - configurer via dashboard admin)            |
 * | `$client->balance()->transactions([...])`         | `$client->billing()->transactions([...])`              |
 * | `$client->balance()->debits()` / `credits()`      | `$client->billing()->transactions(['type' => '...'])`  |
 * | `$client->balance()->enableAutoReload()`          | (supprime - configurer via dashboard admin)            |
 * | `$client->balance()->disableAutoReload()`         | (supprime - configurer via dashboard admin)            |
 *
 * Cette classe est conservee pour la retrocompat des integrations existantes
 * mais sera **supprimee en v3.0.0**. Aucun fix ne sera applique en attendant —
 * les appels echouent en runtime sur l'API actuelle.
 *
 * @see BillingResource Remplacement officiel (`$client->billing()`)
 */
class BalanceResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Recupere le solde actuel.
     *
     * @deprecated since 2.24.0. Utiliser `$client->billing()->usage()` a la place.
     * L'endpoint `GET /api/v1/balance` a ete supprime le 2026-05-10 — appelle 404.
     */
    public function get(): Balance
    {
        $response = $this->http->get('balance');
        return Balance::fromArray($response['data']);
    }

    /**
     * Recharge le solde.
     *
     * @deprecated since 2.24.0. Utiliser `$client->billing()->topUp(['amount_eur' => $amount])`
     * a la place. L'endpoint `POST /api/v1/balance/reload` a ete supprime le 2026-05-10 — appelle 404.
     *
     * @param float $amount Montant a recharger (10-10000 EUR)
     * @return array{message: string, transaction: array{id: string, amount: float, balance_after: float}}
     */
    public function reload(float $amount): array
    {
        return $this->http->post('balance/reload', [
            'amount' => $amount,
        ]);
    }

    /**
     * Met a jour les parametres du solde.
     *
     * @deprecated since 2.24.0. L'endpoint `PUT /api/v1/balance/settings` a ete
     * supprime le 2026-05-10. Les parametres d'auto-reload sont desormais
     * configurables uniquement via le dashboard admin Scell.io.
     *
     * @param array{
     *     auto_reload_enabled?: bool,
     *     auto_reload_threshold?: float,
     *     auto_reload_amount?: float,
     *     low_balance_alert_threshold?: float,
     *     critical_balance_alert_threshold?: float
     * } $settings
     */
    public function updateSettings(array $settings): Balance
    {
        $response = $this->http->put('balance/settings', $settings);
        return Balance::fromArray($response['data']);
    }

    /**
     * Active le rechargement automatique.
     *
     * @deprecated since 2.24.0. Voir `updateSettings()` — config via dashboard admin uniquement.
     *
     * @param float $threshold Seuil declenchant le rechargement
     * @param float $amount Montant a recharger
     */
    public function enableAutoReload(float $threshold, float $amount): Balance
    {
        return $this->updateSettings([
            'auto_reload_enabled' => true,
            'auto_reload_threshold' => $threshold,
            'auto_reload_amount' => $amount,
        ]);
    }

    /**
     * Desactive le rechargement automatique.
     *
     * @deprecated since 2.24.0. Voir `updateSettings()` — config via dashboard admin uniquement.
     */
    public function disableAutoReload(): Balance
    {
        return $this->updateSettings([
            'auto_reload_enabled' => false,
        ]);
    }

    /**
     * Liste les transactions.
     *
     * @deprecated since 2.24.0. Utiliser `$client->billing()->transactions([...])`
     * a la place. L'endpoint `GET /api/v1/balance/transactions` a ete supprime
     * le 2026-05-10 — appelle 404.
     *
     * @param array{
     *     type?: string,
     *     service?: string,
     *     from?: DateTimeInterface|string,
     *     to?: DateTimeInterface|string,
     *     per_page?: int,
     *     page?: int
     * } $filters
     * @return PaginatedResult<Transaction>
     */
    public function transactions(array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get('balance/transactions', $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Transaction::fromArray($data));
    }

    /**
     * Liste uniquement les debits.
     *
     * @deprecated since 2.24.0. Utiliser `$client->billing()->transactions(['type' => 'debit'])`.
     *
     * @return PaginatedResult<Transaction>
     */
    public function debits(int $perPage = 25): PaginatedResult
    {
        return $this->transactions(['type' => 'debit', 'per_page' => $perPage]);
    }

    /**
     * Liste uniquement les credits.
     *
     * @deprecated since 2.24.0. Utiliser `$client->billing()->transactions(['type' => 'credit'])`.
     *
     * @return PaginatedResult<Transaction>
     */
    public function credits(int $perPage = 25): PaginatedResult
    {
        return $this->transactions(['type' => 'credit', 'per_page' => $perPage]);
    }

    /**
     * Normalise les filtres.
     */
    private function normalizeFilters(array $filters): array
    {
        $query = [];

        foreach ($filters as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $query[$key] = $value->format('Y-m-d');
            } else {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}
