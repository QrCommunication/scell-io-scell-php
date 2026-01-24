<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Codes de rejet pour les factures entrantes.
 */
enum RejectionCode: string
{
    /** Montant incorrect */
    case IncorrectAmount = 'incorrect_amount';

    /** Facture en double */
    case Duplicate = 'duplicate';

    /** Commande inconnue */
    case UnknownOrder = 'unknown_order';

    /** TVA incorrecte */
    case IncorrectVat = 'incorrect_vat';

    /** Autre motif */
    case Other = 'other';

    /**
     * Retourne le libelle en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::IncorrectAmount => 'Montant incorrect',
            self::Duplicate => 'Facture en double',
            self::UnknownOrder => 'Commande inconnue',
            self::IncorrectVat => 'TVA incorrecte',
            self::Other => 'Autre',
        };
    }

    /**
     * Retourne tous les codes sous forme de tableau.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn(self $code) => $code->value, self::cases());
    }
}
