<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Unite de l'intervalle de recurrence d'un profil de facturation recurrente.
 *
 * Aligne sur le backend Scell.io. Combinee a `interval_count`, definit la
 * cadence d'emission (ex: unit=month + count=3 => tous les trimestres).
 *
 * Disponible depuis SDK 2.34.0.
 */
enum RecurrenceIntervalUnit: string
{
    /** Cadence en jours. */
    case Day = 'day';

    /** Cadence en semaines (combinable avec `day_of_week`). */
    case Week = 'week';

    /** Cadence en mois (combinable avec `day_of_month`). */
    case Month = 'month';

    /** Cadence en annees. */
    case Year = 'year';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Day => 'Jour',
            self::Week => 'Semaine',
            self::Month => 'Mois',
            self::Year => 'Annee',
        };
    }
}
