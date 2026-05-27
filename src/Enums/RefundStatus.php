<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut de remboursement d'une facture (vue derivee).
 *
 * Retourne par l'API Scell.io sur `GET /api/v1/invoices` et
 * `GET /api/v1/invoices/{id}` dans le champ `refund_status`. C'est une
 * projection simple du statut Invoice :
 *
 *  - InvoiceStatus::Refunded             => RefundStatus::Full
 *  - InvoiceStatus::PartiallyRefunded    => RefundStatus::Partial
 *  - tout autre statut                   => RefundStatus::None
 *
 * Le backend maintient l'invariant via `CreditNoteObserver`.
 *
 * Disponible depuis SDK 2.20.0.
 */
enum RefundStatus: string
{
    /** Aucun avoir valide rattache a la facture. */
    case None = 'none';

    /** Au moins un avoir valide, somme strictement inferieure au total TTC. */
    case Partial = 'partial';

    /** Somme des avoirs valides >= total TTC (a 0.001 EUR pres). */
    case Full = 'full';

    /**
     * Libelle francais court.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucun remboursement',
            self::Partial => 'Partiellement remboursee',
            self::Full => 'Totalement remboursee',
        };
    }

    /**
     * Indique si la facture a au moins un avoir (partiel ou total).
     */
    public function hasRefund(): bool
    {
        return $this !== self::None;
    }
}
