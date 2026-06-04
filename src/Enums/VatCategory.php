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

    /** Autoliquidation prestations de services intracom B2B — 0 % (Art. 283-2 CGI), AE en EN16931 */
    case ReverseCharge = 'REVERSE_CHARGE';

    /** Hors champ — 0 % (preneur hors UE, Art. 259-1 / 259 B CGI), O en EN16931 */
    case OutOfScope = 'OUT_OF_SCOPE';

    /** Livraison intracommunautaire de BIENS exoneree — 0 % (Art. 262 ter, I CGI), K en EN16931 */
    case IntracomGoods = 'INTRACOM_GOODS';

    /** Exportation de BIENS hors UE exoneree — 0 % (Art. 262, I CGI), G en EN16931 */
    case Export = 'EXPORT';

    /** Franchise en base de TVA (auto-entrepreneur) — 0 % (Art. 293 B CGI), E en EN16931 */
    case FranchiseBase = 'FRANCHISE_BASE';

    /** Exoneration formation professionnelle continue — 0 % (Art. 261-4-4°a CGI), E en EN16931 */
    case ExemptTraining = 'EXEMPT_TRAINING';

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
            self::OutOfScope,
            self::IntracomGoods,
            self::Export,
            self::FranchiseBase,
            self::ExemptTraining => 0.0,
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
            self::Exempt,
            self::FranchiseBase,
            self::ExemptTraining => 'E',
            self::ReverseCharge => 'AE',
            self::IntracomGoods => 'K',
            self::Export        => 'G',
            self::OutOfScope    => 'O',
        };
    }

    /**
     * Raison d'exoneration ou de taux nul, si applicable.
     *
     * Aligne le backend (`App\Enums\Billing\VatCategory::exemptionReason`) :
     * ZERO_RATED ne porte PAS de raison (EN16931 BR-Z n'exige pas de mention).
     * Retourne null pour les categories a taux positif (STANDARD, INTERMEDIATE, etc.).
     */
    public function exemptionReason(): ?string
    {
        return match ($this) {
            self::Exempt         => 'exempt',
            self::ReverseCharge  => 'reverse_charge',
            self::OutOfScope     => 'out_of_scope',
            self::IntracomGoods  => 'intracom_goods',
            self::Export         => 'export',
            self::FranchiseBase  => 'franchise_base',
            self::ExemptTraining => 'exempt_training',
            default              => null,
        };
    }

    /**
     * Mention legale de TVA a faire figurer sur la facture (BT-22 / BT-120) pour
     * les categories non-taxees qui l'exigent. Bilingue FR/EN (FR par defaut).
     *
     * Miroir exact de `App\Enums\Billing\VatCategory::justification()` (backend).
     * Le serveur reste autoritaire : cette methode sert d'aide a l'affichage UI.
     * Retourne null pour les categories taxees (S) et ZERO_RATED (Z).
     *
     * @param string $lang 'fr' (defaut) ou 'en'
     */
    public function justification(string $lang = 'fr'): ?string
    {
        $en = strtolower($lang) === 'en';

        return match ($this) {
            self::ReverseCharge => $en
                ? 'Reverse charge - VAT to be accounted for by the recipient (Art. 196 of Directive 2006/112/EC)'
                : 'Autoliquidation - Article 283-2 du CGI',
            self::IntracomGoods => $en
                ? 'Exempt intra-Community supply of goods (Art. 138 of Directive 2006/112/EC)'
                : 'Exonération de TVA - Article 262 ter, I du CGI (livraison intracommunautaire)',
            self::Export => $en
                ? 'VAT-exempt export of goods (Art. 146 of Directive 2006/112/EC)'
                : 'Exonération de TVA - Article 262, I du CGI (exportation)',
            self::OutOfScope => $en
                ? 'Outside the scope of French VAT (Art. 259-1 of the French General Tax Code)'
                : 'TVA non applicable - Article 259-1 du CGI',
            self::FranchiseBase => $en
                ? 'VAT not applicable - Article 293 B of the French General Tax Code (small business exemption)'
                : 'TVA non applicable, article 293 B du CGI',
            self::ExemptTraining => $en
                ? 'VAT-exempt vocational training (Art. 261-4-4° a of the French General Tax Code)'
                : 'Exonération de TVA - Article 261-4-4°a du CGI (formation professionnelle continue)',
            self::Exempt => $en
                ? 'VAT-exempt transaction (Art. 261 of the French General Tax Code)'
                : 'Opération exonérée de TVA - Article 261 du CGI',
            default => null,
        };
    }

    /**
     * Libelle court en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Standard       => 'Taux normal (20 %)',
            self::Intermediate   => 'Taux intermediaire (10 %)',
            self::Reduced        => 'Taux reduit (5,5 %)',
            self::SuperReduced   => 'Taux super-reduit (2,1 %)',
            self::ZeroRated      => 'Taux zero (0 %)',
            self::Exempt         => 'Exonere (art. 261 CGI)',
            self::ReverseCharge  => 'Autoliquidation (art. 283-2 CGI)',
            self::OutOfScope     => 'Hors champ TVA (art. 259-1 CGI)',
            self::IntracomGoods  => 'Livraison intracommunautaire (art. 262 ter CGI)',
            self::Export         => 'Exportation hors UE (art. 262 I CGI)',
            self::FranchiseBase  => 'Franchise en base (art. 293 B CGI)',
            self::ExemptTraining => 'Formation pro. exoneree (art. 261-4-4°a CGI)',
        };
    }
}
