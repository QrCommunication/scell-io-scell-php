<?php

declare(strict_types=1);

namespace Scell\Sdk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Scell\Sdk\Resources\InvoiceResource;
use Scell\Sdk\Resources\SignatureResource;
use Scell\Sdk\ScellApiClient;

/**
 * Facade Laravel pour le client API Scell (API Key).
 *
 * @method static InvoiceResource invoices()
 * @method static SignatureResource signatures()
 *
 * @see \Scell\Sdk\ScellApiClient
 *
 * @example
 * ```php
 * use Scell\Sdk\Laravel\Facades\ScellApi;
 *
 * // Creer une facture
 * $invoice = ScellApi::invoices()->builder()
 *     ->invoiceNumber('FACT-2024-001')
 *     ->outgoing()
 *     ->facturX()
 *     ->issueDate(now())
 *     ->seller('12345678901234', 'Ma Societe', [...])
 *     ->buyer('98765432109876', 'Client SA', [...])
 *     ->addLine('Prestation', 1, 1000.00, 20.0)
 *     ->create();
 *
 * // Creer une signature
 * $signature = ScellApi::signatures()->builder()
 *     ->title('Contrat')
 *     ->documentFromFile('/path/to/doc.pdf')
 *     ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
 *     ->create();
 * ```
 */
class ScellApi extends Facade
{
    /**
     * Retourne le nom du binding dans le container.
     */
    protected static function getFacadeAccessor(): string
    {
        return ScellApiClient::class;
    }
}
