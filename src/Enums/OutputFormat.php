<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Format de sortie de la facture electronique.
 */
enum OutputFormat: string
{
    /** Factur-X (PDF avec XML embarque) - Norme franco-allemande */
    case FacturX = 'facturx';

    /** UBL (Universal Business Language) - Norme europeenne */
    case UBL = 'ubl';

    /** CII (Cross-Industry Invoice) - Norme UN/CEFACT */
    case CII = 'cii';

    /**
     * Retourne le libelle complet.
     */
    public function label(): string
    {
        return match ($this) {
            self::FacturX => 'Factur-X (PDF/A-3)',
            self::UBL => 'UBL 2.1',
            self::CII => 'UN/CEFACT CII',
        };
    }

    /**
     * Retourne l'extension de fichier.
     */
    public function extension(): string
    {
        return match ($this) {
            self::FacturX => 'pdf',
            self::UBL => 'xml',
            self::CII => 'xml',
        };
    }
}
