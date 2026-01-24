<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Direction de la facture (vente ou achat).
 */
enum Direction: string
{
    /** Facture de vente (emise) */
    case Outgoing = 'outgoing';

    /** Facture d'achat (recue) */
    case Incoming = 'incoming';

    /**
     * Retourne le libelle en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Outgoing => 'Vente',
            self::Incoming => 'Achat',
        };
    }
}
