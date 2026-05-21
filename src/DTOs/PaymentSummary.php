<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Tracker du solde restant a facturer pour un devis avec echeancier.
 *
 * Alimente par les factures d'acompte successives associees au devis.
 * La facturation "reste a facturer" correspond au total TTC du devis
 * moins les montants nets factures (factures - avoirs).
 */
final readonly class PaymentSummary
{
    public function __construct(
        public string $quoteId,
        /** Total TTC du devis (source de verite). */
        public float $totalTtc,
        /** Somme brute des factures d'acompte emises. */
        public float $totalInvoiced,
        /** Somme des avoirs credites (valeur positive). */
        public float $totalCredited,
        /** Montant net facture = totalInvoiced - totalCredited. */
        public float $netInvoiced,
        /** Montant restant a facturer (peut remonter si avoirs). */
        public float $remaining,
        /** Pourcentage facture net (0-100). */
        public float $percentInvoiced,
        /** Nombre de lignes d'echeancier au total. */
        public int $linesTotal,
        /** Nombre de lignes converties en facture. */
        public int $linesInvoiced,
        /** Nombre de lignes en attente. */
        public int $linesPending,
        /** Indique si la facture de solde a ete emise. */
        public bool $balanceInvoiceEmitted,
        /** ID de la facture de solde si emise. */
        public ?string $balanceInvoiceId = null,
        /** Lignes d'echeancier avec leur statut courant. */
        public ?array $lines = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $lines = null;
        if (isset($data['lines']) && is_array($data['lines'])) {
            $lines = array_map(
                fn(array $line) => PaymentScheduleLine::fromArray($line),
                $data['lines'],
            );
        }

        return new self(
            quoteId: $data['quote_id'],
            totalTtc: (float) $data['total_ttc'],
            totalInvoiced: (float) ($data['total_invoiced'] ?? 0),
            totalCredited: (float) ($data['total_credited'] ?? 0),
            netInvoiced: (float) ($data['net_invoiced'] ?? 0),
            remaining: (float) ($data['remaining'] ?? $data['total_ttc']),
            percentInvoiced: (float) ($data['percent_invoiced'] ?? 0),
            linesTotal: (int) ($data['lines_total'] ?? 0),
            linesInvoiced: (int) ($data['lines_invoiced'] ?? 0),
            linesPending: (int) ($data['lines_pending'] ?? 0),
            balanceInvoiceEmitted: (bool) ($data['balance_invoice_emitted'] ?? false),
            balanceInvoiceId: $data['balance_invoice_id'] ?? null,
            lines: $lines,
        );
    }

    /** Indique si la facturation est complete (solde emis). */
    public function isComplete(): bool
    {
        return $this->balanceInvoiceEmitted;
    }

    /** Indique si toutes les echeances sont facturees (sans compter le solde). */
    public function allScheduleLinesInvoiced(): bool
    {
        return $this->linesPending === 0 && $this->linesInvoiced === $this->linesTotal;
    }
}
