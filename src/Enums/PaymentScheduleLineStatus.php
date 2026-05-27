<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut d'une ligne d'echeancier de paiement (devis).
 *
 * Aligne sur `App\Enums\Quote\PaymentScheduleLineStatus` du backend.
 *
 * Transitions autorisees :
 *   - Pending   -> Invoiced   (facture d'acompte emise pour cette ligne)
 *   - Pending   -> Cancelled  (annulation manuelle avant facturation)
 *   - Invoiced  -> Pending    (facture supprimee, SET NULL sur invoice_id)
 *
 * `Invoiced` et `invoice_id IS NOT NULL` sont synchronises par le CHECK
 * `chk_qpsl_invoice_consistency` en base.
 *
 * Disponible depuis SDK 2.21.0.
 */
enum PaymentScheduleLineStatus: string
{
    /** En attente de facturation. */
    case Pending = 'pending';

    /** Facture d'acompte emise pour cette ligne. */
    case Invoiced = 'invoiced';

    /** Annulee manuellement avant facturation. */
    case Cancelled = 'cancelled';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Invoiced => 'Facture',
            self::Cancelled => 'Annule',
        };
    }

    /**
     * Couleur de badge Ant Design associee au statut.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'orange',
            self::Invoiced => 'green',
            self::Cancelled => 'default',
        };
    }

    /**
     * Si la ligne peut encore etre facturee.
     */
    public function canInvoice(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Si la ligne peut etre annulee.
     */
    public function canCancel(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Si la ligne est dans un etat terminal (plus d'action possible).
     */
    public function isTerminal(): bool
    {
        return $this === self::Cancelled;
    }
}
