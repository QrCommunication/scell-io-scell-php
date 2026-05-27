<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Type d'avoir : partiel ou total.
 *
 * Aligne sur le check constraint backend `credit_notes_type_check`.
 *
 * - `Partial` : avoir partiel, le montant rembourse est inferieur au total
 *               TTC de la facture mere. Une facture peut avoir N avoirs
 *               partiels successifs jusqu'a atteindre 100%.
 * - `Total`   : avoir total qui couvre l'integralite du TTC de la facture
 *               mere. La facture mere bascule alors en `refunded`.
 *
 * Disponible depuis SDK 2.21.0.
 */
enum CreditNoteType: string
{
    /** Avoir partiel - rembourse une partie du TTC. */
    case Partial = 'partial';

    /** Avoir total - rembourse l'integralite du TTC. */
    case Total = 'total';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Partial => 'Partiel',
            self::Total => 'Total',
        };
    }

    /**
     * Indique si l'avoir couvre l'integralite de la facture mere.
     */
    public function isTotal(): bool
    {
        return $this === self::Total;
    }
}
