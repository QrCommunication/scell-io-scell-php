<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Type de facture emise.
 *
 * Aligne sur `App\Enums\Invoice\InvoiceType` du backend Scell.io.
 * Distingue les factures standards des factures d'acompte / de solde
 * issues d'un devis avec echeancier de paiement.
 *
 * Disponible depuis SDK 2.21.0.
 */
enum InvoiceType: string
{
    /** Facture standard (type Factur-X 380). */
    case Standard = 'standard';

    /**
     * Facture d'acompte (type Factur-X 386).
     *
     * TVA immediatement exigible (CGI art. 289). Generee depuis une ligne
     * d'echeancier d'un devis. Plusieurs acomptes peuvent etre emis pour
     * un meme devis.
     */
    case Deposit = 'deposit';

    /**
     * Facture de solde (type Factur-X 380).
     *
     * Emise une seule fois apres tous les acomptes. Deduit automatiquement
     * les acomptes deja factures (BG-22 code '80').
     */
    case Balance = 'balance';

    /**
     * Code Factur-X UNTDID 1001 correspondant.
     */
    public function facturXTypeCode(): string
    {
        return match ($this) {
            self::Standard, self::Balance => '380',
            self::Deposit => '386',
        };
    }

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Deposit => 'Acompte',
            self::Balance => 'Solde',
        };
    }

    /**
     * Indique si c'est une facture d'acompte ou de solde (issue d'un devis).
     */
    public function isDepositOrBalance(): bool
    {
        return $this !== self::Standard;
    }

    /**
     * Indique si les mentions de penalites de retard (L441-10) doivent etre
     * omises. Acompte = TVA exigible mais pas encore due au sens commercial.
     */
    public function omitLatePaymentMentions(): bool
    {
        return $this === self::Deposit;
    }
}
