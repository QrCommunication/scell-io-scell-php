<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut d'une facture emise par Scell.io au tenant (billing platform).
 *
 * Aligne sur le check constraint backend `tenant_invoices_status_check`.
 * Distinct de `InvoiceStatus` (qui concerne les factures emises par les
 * tenants vers leurs clients). Concerne uniquement les factures Scell.io
 * facturees aux tenants (top-up de balance, packs de credits, etc.).
 *
 * Disponible depuis SDK 2.21.0.
 */
enum TenantInvoiceStatus: string
{
    /** Brouillon - facture creee mais non envoyee. */
    case Draft = 'draft';

    /** Envoyee au tenant. */
    case Sent = 'sent';

    /** Payee. */
    case Paid = 'paid';

    /** En retard - non payee passe la due_date. */
    case Overdue = 'overdue';

    /** Annulee. */
    case Cancelled = 'cancelled';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Envoyee',
            self::Paid => 'Payee',
            self::Overdue => 'En retard',
            self::Cancelled => 'Annulee',
        };
    }

    /**
     * Indique si la facture est encore payable.
     */
    public function isPayable(): bool
    {
        return $this === self::Sent || $this === self::Overdue;
    }

    /**
     * Indique si la facture est dans un etat terminal.
     */
    public function isFinal(): bool
    {
        return $this === self::Paid || $this === self::Cancelled;
    }
}
