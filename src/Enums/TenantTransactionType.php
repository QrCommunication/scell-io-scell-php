<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Type de transaction sur le solde de credits d'un tenant Scell.io.
 *
 * Aligne sur le check constraint backend `tenant_transactions_type_check`.
 * Le solde tenant est en credit comptable double-entry : tout debit est
 * compense par un credit (ou inversement).
 *
 * Disponible depuis SDK 2.21.0.
 */
enum TenantTransactionType: string
{
    /** Debit - consommation de credits (emission de facture, signature, etc.). */
    case Debit = 'debit';

    /** Credit - rechargement de solde (top-up Stripe, pack achete, ajustement manuel). */
    case Credit = 'credit';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Debit => 'Debit',
            self::Credit => 'Credit',
        };
    }

    /**
     * Signe arithmetique de la transaction (+1 pour credit, -1 pour debit).
     */
    public function sign(): int
    {
        return match ($this) {
            self::Credit => 1,
            self::Debit => -1,
        };
    }
}
