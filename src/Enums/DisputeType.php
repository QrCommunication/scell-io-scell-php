<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Types de litige pour les factures entrantes.
 */
enum DisputeType: string
{
    /** Litige sur le montant */
    case AmountDispute = 'amount_dispute';

    /** Litige sur la qualite */
    case QualityDispute = 'quality_dispute';

    /** Litige sur la livraison */
    case DeliveryDispute = 'delivery_dispute';

    /** Autre type de litige */
    case Other = 'other';

    /**
     * Retourne le libelle en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::AmountDispute => 'Litige sur le montant',
            self::QualityDispute => 'Litige sur la qualite',
            self::DeliveryDispute => 'Litige sur la livraison',
            self::Other => 'Autre',
        };
    }

    /**
     * Retourne tous les types sous forme de tableau.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn(self $type) => $type->value, self::cases());
    }
}
