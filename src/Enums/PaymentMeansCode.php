<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Codes UN/ECE 4461 — Payment Means Type Code (BT-81 EN16931 / Factur-X).
 *
 * Conformes au standard officiel UN/ECE Recommendation 16, code list 4461
 * (Payment Means). Le SDK n'expose que le sous-ensemble retenu cote backend
 * Scell.io — typiquement les moyens de paiement rencontres en B2B France.
 *
 * Reference FNFE-MPE : https://fnfe-mpe.org/factur-x/specifications/
 * Reference horstoeko/zugferd : ZugferdPaymentMeans::UNTDID_4461_*
 *
 * Disponible depuis SDK 2.25.0 (API 2026-05-28).
 *
 * @example
 * ```php
 * use Scell\Sdk\Enums\PaymentMeansCode;
 *
 * // Marquer une facture payee par virement SEPA
 * $client->invoices()->markPaid(
 *     'invoice-uuid',
 *     PaymentMeansCode::SEPA_CREDIT_TRANSFER,
 *     [
 *         'payment_reference' => 'VIR-2026-001234',
 *         'payment_means_text' => 'Compte Credit Mutuel ...4567',
 *     ]
 * );
 *
 * // Libelles localises
 * echo PaymentMeansCode::SEPA_CREDIT_TRANSFER->label();   // "Virement SEPA"
 * echo PaymentMeansCode::SEPA_CREDIT_TRANSFER->labelEn(); // "SEPA credit transfer"
 *
 * // Selection prioritaire des moyens B2B France
 * foreach (PaymentMeansCode::commonB2bFrance() as $code) {
 *     echo "{$code->value} → {$code->label()}\n";
 * }
 * ```
 */
enum PaymentMeansCode: string
{
    /** Fallback generique (BR-CO-27 tolere). */
    case INSTRUMENT_NOT_DEFINED = '1';

    /** Especes. */
    case IN_CASH = '10';

    /** Cheque. */
    case CHEQUE = '20';

    /** Virement (international, non-SEPA). */
    case CREDIT_TRANSFER = '30';

    /** Versement bancaire (sans IBAN). */
    case PAYMENT_TO_BANK_ACCOUNT = '42';

    /** Carte bancaire. */
    case BANK_CARD = '48';

    /** Prelevement (international, non-SEPA). */
    case DIRECT_DEBIT = '49';

    /** Accord permanent / mandat. */
    case STANDING_AGREEMENT = '57';

    /** Virement SEPA (priorise en B2B France). */
    case SEPA_CREDIT_TRANSFER = '58';

    /** Prelevement SEPA. */
    case SEPA_DIRECT_DEBIT = '59';

    /** Compensation inter-comptes. */
    case CLEARING_BETWEEN_PARTNERS = '97';

    /**
     * Libelle francais court — pour UI dashboard ou export PDF (BT-82 par defaut).
     */
    public function label(): string
    {
        return match ($this) {
            self::INSTRUMENT_NOT_DEFINED => 'Non specifie',
            self::IN_CASH => 'Especes',
            self::CHEQUE => 'Cheque',
            self::CREDIT_TRANSFER => 'Virement',
            self::PAYMENT_TO_BANK_ACCOUNT => 'Versement bancaire',
            self::BANK_CARD => 'Carte bancaire',
            self::DIRECT_DEBIT => 'Prelevement',
            self::STANDING_AGREEMENT => 'Accord permanent',
            self::SEPA_CREDIT_TRANSFER => 'Virement SEPA',
            self::SEPA_DIRECT_DEBIT => 'Prelevement SEPA',
            self::CLEARING_BETWEEN_PARTNERS => 'Compensation',
        };
    }

    /**
     * English label — for i18n / SDK doc consumers.
     */
    public function labelEn(): string
    {
        return match ($this) {
            self::INSTRUMENT_NOT_DEFINED => 'Unspecified',
            self::IN_CASH => 'Cash',
            self::CHEQUE => 'Cheque',
            self::CREDIT_TRANSFER => 'Credit transfer',
            self::PAYMENT_TO_BANK_ACCOUNT => 'Bank account transfer',
            self::BANK_CARD => 'Bank card',
            self::DIRECT_DEBIT => 'Direct debit',
            self::STANDING_AGREEMENT => 'Standing agreement',
            self::SEPA_CREDIT_TRANSFER => 'SEPA credit transfer',
            self::SEPA_DIRECT_DEBIT => 'SEPA direct debit',
            self::CLEARING_BETWEEN_PARTNERS => 'Clearing between partners',
        };
    }

    /**
     * Codes les plus courants en B2B France, ordre de priorite d'affichage
     * recommande pour les selecteurs UI.
     *
     * @return array<int, self>
     */
    public static function commonB2bFrance(): array
    {
        return [
            self::SEPA_CREDIT_TRANSFER,
            self::CREDIT_TRANSFER,
            self::CHEQUE,
            self::BANK_CARD,
            self::SEPA_DIRECT_DEBIT,
            self::IN_CASH,
        ];
    }
}
