<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Categorie de TVA applicable a une ligne de facture.
 *
 * Chaque valeur correspond a un code EN16931 et un taux par defaut
 * applique en France metropolitaine. Utilisez `VatCategory::defaultRate()`
 * pour obtenir le taux standard et `en16931Code()` pour le code XML
 * requis dans les flux Factur-X / UBL.
 */
enum VatCategory: string
{
    /** Taux normal — 20 % (FR), S en EN16931 */
    case Standard = 'STANDARD';

    /** Taux intermediaire — 10 % (FR), S en EN16931 */
    case Intermediate = 'INTERMEDIATE';

    /** Taux reduit — 5,5 % (FR), S en EN16931 */
    case Reduced = 'REDUCED';

    /** Taux super-reduit — 2,1 % (FR), S en EN16931 */
    case SuperReduced = 'SUPER_REDUCED';

    /** Taux zero — 0 % (export, livraisons intracommunautaires), Z en EN16931 */
    case ZeroRated = 'ZERO_RATED';

    /** Exonere — 0 % (education, sante, ...), E en EN16931 */
    case Exempt = 'EXEMPT';

    /** Autoliquidation — 0 % (B2B intracommunautaire avec numero TVA valide), AE en EN16931 */
    case ReverseCharge = 'REVERSE_CHARGE';

    /** Hors champ — 0 % (pays tiers, operateur non assujetti), O en EN16931 */
    case OutOfScope = 'OUT_OF_SCOPE';

    /**
     * Taux par defaut en France metropolitaine (en pourcentage, ex: 20.0 pour 20 %).
     */
    public function defaultRate(): float
    {
        return match ($this) {
            self::Standard      => 20.0,
            self::Intermediate  => 10.0,
            self::Reduced       => 5.5,
            self::SuperReduced  => 2.1,
            self::ZeroRated,
            self::Exempt,
            self::ReverseCharge,
            self::OutOfScope    => 0.0,
        };
    }

    /**
     * Code EN16931 / UNTDID 5305 utilise dans les flux Factur-X et UBL.
     */
    public function en16931Code(): string
    {
        return match ($this) {
            self::Standard,
            self::Intermediate,
            self::Reduced,
            self::SuperReduced  => 'S',
            self::ZeroRated     => 'Z',
            self::Exempt        => 'E',
            self::ReverseCharge => 'AE',
            self::OutOfScope    => 'O',
        };
    }

    /**
     * Raison d'exoneration ou de taux nul, si applicable.
     *
     * Retourne null pour les categories a taux positif (STANDARD, INTERMEDIATE, etc.).
     */
    public function exemptionReason(): ?string
    {
        return match ($this) {
            self::ZeroRated     => 'zero_rated',
            self::Exempt        => 'exempt',
            self::ReverseCharge => 'reverse_charge',
            self::OutOfScope    => 'out_of_scope',
            default             => null,
        };
    }

    /**
     * Libelle court en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Standard      => 'Taux normal (20 %)',
            self::Intermediate  => 'Taux intermediaire (10 %)',
            self::Reduced       => 'Taux reduit (5,5 %)',
            self::SuperReduced  => 'Taux super-reduit (2,1 %)',
            self::ZeroRated     => 'Taux zero (0 %)',
            self::Exempt        => 'Exonere',
            self::ReverseCharge => 'Autoliquidation',
            self::OutOfScope    => 'Hors champ TVA',
        };
    }
}
