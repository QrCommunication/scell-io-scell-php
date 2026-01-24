<?php

declare(strict_types=1);

namespace Scell\Sdk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Scell\Sdk\Resources\BalanceResource;
use Scell\Sdk\Resources\CompanyResource;
use Scell\Sdk\Resources\InvoiceResource;
use Scell\Sdk\Resources\SignatureResource;
use Scell\Sdk\Resources\WebhookResource;
use Scell\Sdk\ScellClient;

/**
 * Facade Laravel pour le client Scell (Bearer token).
 *
 * @method static InvoiceResource invoices()
 * @method static SignatureResource signatures()
 * @method static CompanyResource companies()
 * @method static BalanceResource balance()
 * @method static WebhookResource webhooks()
 *
 * @see \Scell\Sdk\ScellClient
 *
 * @example
 * ```php
 * use Scell\Sdk\Laravel\Facades\Scell;
 *
 * // Lister les factures
 * $invoices = Scell::invoices()->list();
 *
 * // Creer une signature
 * $signature = Scell::signatures()->builder()
 *     ->title('Contrat')
 *     ->documentFromFile('/path/to/doc.pdf')
 *     ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
 *     ->create();
 *
 * // Consulter le solde
 * $balance = Scell::balance()->get();
 * ```
 */
class Scell extends Facade
{
    /**
     * Retourne le nom du binding dans le container.
     */
    protected static function getFacadeAccessor(): string
    {
        return ScellClient::class;
    }
}
