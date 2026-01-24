<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\DTOs\Balance;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\Transaction;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour le solde et les transactions.
 *
 * Permet de consulter le solde, recharger et voir l'historique des transactions.
 */
class BalanceResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Recupere le solde actuel.
     */
    public function get(): Balance
    {
        $response = $this->http->get('balance');
        return Balance::fromArray($response['data']);
    }

    /**
     * Recharge le solde.
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
     * @return PaginatedResult<Transaction>
     */
    public function debits(int $perPage = 25): PaginatedResult
    {
        return $this->transactions(['type' => 'debit', 'per_page' => $perPage]);
    }

    /**
     * Liste uniquement les credits.
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
