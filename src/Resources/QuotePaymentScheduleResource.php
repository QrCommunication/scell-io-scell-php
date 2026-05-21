<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\Invoice;
use Scell\Sdk\DTOs\PaymentScheduleLine;
use Scell\Sdk\DTOs\PaymentSummary;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour l'echeancier de paiement d'un devis.
 *
 * Permet de definir, modifier et tracker les echeances associees
 * a un devis. Chaque ligne peut etre convertie en facture d'acompte.
 *
 * Les lignes sont verrouillee a la signature du devis (acceptation).
 *
 * @example
 * ```php
 * $api = ScellApiClient::withApiKey('sk_live_...');
 *
 * // Definir un echeancier 30/70
 * $lines = $api->quotes()->paymentSchedule()->set($quoteId, [
 *     ['amount_type' => 'percent', 'amount_value' => 30, 'due_date' => '2026-06-15', 'description' => 'Acompte 30%'],
 *     ['amount_type' => 'percent', 'amount_value' => 70, 'milestone_label' => 'Livraison finale'],
 * ]);
 *
 * // Tracker le solde restant
 * $summary = $api->quotes()->paymentSchedule()->summary($quoteId);
 * echo "Reste a facturer : {$summary->remaining} EUR";
 *
 * // Convertir une ligne en facture
 * $invoice = $api->quotes()->paymentSchedule()->convertLine($quoteId, $lineId, [
 *     'issue_date' => '2026-06-15',
 *     'due_date'   => '2026-07-15',
 * ]);
 * ```
 */
class QuotePaymentScheduleResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Recupere l'echeancier de paiement d'un devis.
     *
     * @return PaymentScheduleLine[]
     */
    public function list(string $quoteId): array
    {
        $response = $this->http->get("quotes/{$quoteId}/payment-schedule");
        return array_map(
            fn(array $line) => PaymentScheduleLine::fromArray($line),
            $response['data'] ?? $response,
        );
    }

    /**
     * Definit (remplace entierement) l'echeancier de paiement du devis.
     *
     * Toutes les lignes existantes sont remplacees. Le devis doit etre
     * en statut 'draft' ou 'sent' (non signe).
     *
     * @param array<int, array{
     *     amount_type: 'percent'|'amount',
     *     amount_value: float,
     *     due_date?: string,
     *     milestone_label?: string,
     *     description?: string,
     *     auto_generate?: bool
     * }> $lines
     * @return PaymentScheduleLine[]
     *
     * @throws \Scell\Sdk\Exceptions\QuoteNotEditableException si le devis est signe
     * @throws \Scell\Sdk\Exceptions\ScheduleSumExceedsTotalException si la somme > 100%
     */
    public function set(string $quoteId, array $lines): array
    {
        $response = $this->http->post("quotes/{$quoteId}/payment-schedule", ['lines' => $lines]);
        return array_map(
            fn(array $line) => PaymentScheduleLine::fromArray($line),
            $response['data'] ?? $response,
        );
    }

    /**
     * Modifie partiellement l'echeancier (add / update / remove).
     *
     * @param array{
     *     add?: array[],
     *     update?: array[],
     *     remove?: string[]
     * } $changes
     * @return PaymentScheduleLine[]
     *
     * @throws \Scell\Sdk\Exceptions\QuoteNotEditableException si le devis est signe
     */
    public function patch(string $quoteId, array $changes): array
    {
        $response = $this->http->patch("quotes/{$quoteId}/payment-schedule", $changes);
        return array_map(
            fn(array $line) => PaymentScheduleLine::fromArray($line),
            $response['data'] ?? $response,
        );
    }

    /**
     * Supprime toutes les lignes de l'echeancier.
     *
     * @throws \Scell\Sdk\Exceptions\QuoteNotEditableException si le devis est signe
     */
    public function delete(string $quoteId): void
    {
        $this->http->delete("quotes/{$quoteId}/payment-schedule");
    }

    /**
     * Recupere le tracker de solde restant a facturer.
     *
     * Inclut les montants factures (acomptes emis), les avoirs eventuels
     * et le calcul du solde restant.
     */
    public function summary(string $quoteId): PaymentSummary
    {
        $response = $this->http->get("quotes/{$quoteId}/payment-summary");
        return PaymentSummary::fromArray($response['data'] ?? $response);
    }

    /**
     * Convertit une ligne d'echeancier en facture d'acompte.
     *
     * Le devis doit etre en statut 'accepted'. La ligne doit etre
     * en statut 'pending' (non encore facturee).
     *
     * @param array{
     *     issue_date?: string,
     *     due_date?: string,
     *     output_format?: string
     * } $options
     *
     * @throws \Scell\Sdk\Exceptions\ScheduleLineAlreadyInvoicedException si la ligne est deja facturee
     * @throws \Scell\Sdk\Exceptions\QuoteNotEditableException si le devis n'est pas en statut acceptable
     */
    public function convertLine(string $quoteId, string $lineId, array $options = []): Invoice
    {
        $response = $this->http->post(
            "quotes/{$quoteId}/payment-schedule/lines/{$lineId}/convert",
            $options,
        );
        return Invoice::fromArray($response['data']);
    }

    /**
     * Recupere les 4 presets d'echeancier disponibles.
     *
     * Les presets sont des modeles preconfigures (30/70, mensuel 3x, etc.)
     * que le tenant peut appliquer directement sur un devis.
     *
     * @return array<int, array{name: string, description: string, lines: array[]}>
     */
    public function presets(): array
    {
        $response = $this->http->get('payment-schedule-presets');
        return $response['data'] ?? $response;
    }
}
