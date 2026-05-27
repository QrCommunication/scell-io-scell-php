<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Type de montant d'une ligne d'echeancier de devis.
 *
 * Aligne sur `App\Enums\Quote\PaymentScheduleLineAmountType` du backend.
 *
 * - `Percent` : pourcentage du total TTC du devis (0.01-100). Le montant
 *               TTC reel est fige a la signature via `amount_ttc_snapshot`.
 * - `Amount`  : montant fixe en euros TTC (> 0).
 *
 * Contrainte XOR : une ligne est soit en pourcentage, soit en montant fixe.
 * Validee par le CHECK `chk_qpsl_amount_type` en base.
 *
 * Disponible depuis SDK 2.21.0.
 */
enum PaymentScheduleLineAmountType: string
{
    /** Pourcentage du total TTC du devis. */
    case Percent = 'percent';

    /** Montant fixe en euros TTC. */
    case Amount = 'amount';

    /**
     * Libelle affiche dans l'interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Pourcentage (%)',
            self::Amount => 'Montant fixe (EUR)',
        };
    }

    /**
     * Suffixe d'unite pour l'affichage de la valeur.
     */
    public function unit(): string
    {
        return match ($this) {
            self::Percent => '%',
            self::Amount => 'EUR',
        };
    }

    /**
     * Calcule le montant TTC concret depuis la valeur de la ligne et le
     * total TTC du devis. Arrondi a 2 decimales (half-up).
     */
    public function computeTtc(float|string $amountValue, float|string $quoteTotalTtc): float
    {
        $value = (float) $amountValue;
        $total = (float) $quoteTotalTtc;

        return match ($this) {
            self::Percent => round($total * $value / 100, 2),
            self::Amount => round($value, 2),
        };
    }
}
