<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Type de document supporte par un Invoice Template.
 *
 * Aligne sur `App\Enums\Invoice\InvoiceTemplateKind` du backend Scell.io.
 * Un template peut etre dedie aux factures, aux devis, ou utilisable pour
 * les deux (`Both`). Disponible depuis SDK 2.21.0.
 */
enum InvoiceTemplateKind: string
{
    /** Template dedie aux factures (Factur-X/UBL/CII). */
    case Invoice = 'invoice';

    /** Template dedie aux devis (PDF + signature canvas). */
    case Quote = 'quote';

    /** Template utilisable pour factures ET devis. */
    case Both = 'both';

    /**
     * Verifie si le template peut etre utilise pour un kind donne.
     *
     * Un template `Both` matche toujours. Sinon, comparaison stricte sur la valeur.
     */
    public function matches(string $kind): bool
    {
        return $this->value === $kind || $this === self::Both;
    }

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Facture',
            self::Quote => 'Devis',
            self::Both => 'Facture et Devis',
        };
    }
}
