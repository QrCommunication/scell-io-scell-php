# Scell.io PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/scell/sdk.svg?style=flat-square)](https://packagist.org/packages/scell/sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/scell/sdk.svg?style=flat-square)](https://packagist.org/packages/scell/sdk)
[![License](https://img.shields.io/packagist/l/scell/sdk.svg?style=flat-square)](https://packagist.org/packages/scell/sdk)
[![PHP Version](https://img.shields.io/packagist/php-v/scell/sdk.svg?style=flat-square)](https://packagist.org/packages/scell/sdk)

SDK PHP officiel pour l'API Scell.io - Facturation electronique (Factur-X/UBL/CII) et signature electronique (eIDAS EU-SES).

## Features

- Facturation electronique conforme (Factur-X, UBL 2.1, UN/CEFACT CII)
- Signature electronique simple (eIDAS EU-SES)
- Integration Laravel native avec auto-discovery
- Builders fluent pour factures et signatures
- Verification HMAC-SHA256 des webhooks
- Retry automatique avec backoff exponentiel
- DTOs types et Enums PHP 8.2+
- Gestion d'erreurs complete

## Installation

```bash
composer require scell/sdk
```

## Configuration

### Variables d'environnement

```env
# API
SCELL_BASE_URL=https://api.scell.io/api/v1
SCELL_API_KEY=sk_live_...

# Optionnel - Pour le dashboard (Bearer token)
SCELL_BEARER_TOKEN=eyJ...

# Webhooks
SCELL_WEBHOOK_SECRET=whsec_...
```

## Utilisation Standalone

### Client API (integration backend)

Pour les integrations serveur-a-serveur avec API Key:

```php
use Scell\Sdk\ScellApiClient;
use Scell\Sdk\DTOs\Address;
use Scell\Sdk\Enums\AuthMethod;

// Initialisation
$api = ScellApiClient::withApiKey('sk_live_...');

// Mode sandbox pour les tests
$api = ScellApiClient::sandbox('sk_test_...');
```

### Creer une facture

```php
use Scell\Sdk\DTOs\Address;

$invoice = $api->invoices()->builder()
    ->invoiceNumber('FACT-2024-001')
    ->externalId('my-internal-id')
    ->outgoing()
    ->facturX()
    ->issueDate(new DateTime())
    ->dueDate((new DateTime())->modify('+30 days'))
    ->seller(
        siret: '12345678901234',
        name: 'Ma Societe SARL',
        address: new Address(
            line1: '1 Rue de la Paix',
            postalCode: '75001',
            city: 'Paris'
        )
    )
    ->buyer(
        siret: '98765432109876',
        name: 'Client SA',
        address: new Address(
            line1: '2 Avenue du Commerce',
            postalCode: '69001',
            city: 'Lyon'
        )
    )
    ->addLine('Prestation de conseil', 10, 100.00, 20.0)
    ->addLine('Formation', 2, 500.00, 20.0)
    ->archiveEnabled()
    ->create();

echo "Facture creee: {$invoice->id}";
echo "Total TTC: {$invoice->totalTtc} EUR";
```

### Creer une signature

```php
use Scell\Sdk\Enums\AuthMethod;

$signature = $api->signatures()->builder()
    ->title('Contrat de prestation')
    ->description('Contrat annuel de maintenance')
    ->externalId('contract-2024-001')
    ->documentFromFile('/path/to/contract.pdf')
    ->addEmailSigner('Jean', 'Dupont', 'jean.dupont@example.com')
    ->addSmsSigner('Marie', 'Martin', '+33612345678')
    ->addSignaturePosition(page: 5, x: 100, y: 700, width: 200, height: 50)
    ->uiConfig(
        logoUrl: 'https://example.com/logo.png',
        primaryColor: '#0066CC',
        companyName: 'Ma Societe'
    )
    ->redirectUrls(
        completeUrl: 'https://example.com/signed',
        cancelUrl: 'https://example.com/cancelled'
    )
    ->expiresAt((new DateTime())->modify('+7 days'))
    ->create();

echo "Signature creee: {$signature->id}";
echo "Statut: {$signature->status->label()}";

// Recuperer les URLs de signature
foreach ($signature->signers as $signer) {
    echo "{$signer->fullName()}: {$signer->signingUrl}";
}
```

### Client Dashboard (Bearer token)

Pour les operations via le dashboard utilisateur:

```php
use Scell\Sdk\ScellClient;

$client = new ScellClient($bearerToken);

// Lister les factures
$invoices = $client->invoices()->list([
    'direction' => 'outgoing',
    'status' => 'validated',
    'per_page' => 50,
]);

foreach ($invoices->data as $invoice) {
    echo "{$invoice->invoiceNumber}: {$invoice->totalTtc} EUR";
}

// Pagination
if ($invoices->hasNextPage()) {
    $nextPage = $client->invoices()->list(['page' => $invoices->nextPage()]);
}

// Consulter le solde
$balance = $client->balance()->get();
echo "Solde: {$balance->formatted()}";

if ($balance->isLow()) {
    echo "Attention: solde bas!";
}

// Gerer les entreprises
$companies = $client->companies()->list();

// Gerer les webhooks
$webhooks = $client->webhooks()->list();
```

### Telecharger des fichiers

```php
// Telecharger une facture
$download = $api->invoices()->download($invoiceId, 'converted');
echo "URL: {$download['url']}"; // URL temporaire (15 min)

// Telecharger un document signe
$download = $api->signatures()->download($signatureId, 'signed');

// Piste d'audit
$audit = $api->invoices()->auditTrail($invoiceId);
foreach ($audit['data'] as $entry) {
    echo "{$entry['action']}: {$entry['details']}";
}
```

## Integration Laravel

### Installation

Le SDK supporte l'auto-discovery Laravel. Publiez la configuration:

```bash
php artisan vendor:publish --tag=scell-config
```

### Configuration (.env)

```env
SCELL_API_KEY=sk_live_...
SCELL_WEBHOOK_SECRET=whsec_...
```

### Utilisation avec Facades

```php
use Scell\Sdk\Laravel\Facades\ScellApi;
use Scell\Sdk\Laravel\Facades\Scell;
use Scell\Sdk\Laravel\Facades\ScellWebhook;

// Creer une facture (API Key)
$invoice = ScellApi::invoices()->builder()
    ->invoiceNumber('FACT-2024-001')
    ->outgoing()
    ->facturX()
    // ...
    ->create();

// Consulter le solde (Bearer token)
$balance = Scell::balance()->get();

// Verifier un webhook
$payload = ScellWebhook::verify(
    request()->getContent(),
    request()->header('X-Scell-Signature')
);
```

### Controller de Webhook

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Scell\Sdk\Laravel\Facades\ScellWebhook;
use Scell\Sdk\Exceptions\ScellException;

class ScellWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $payload = ScellWebhook::verify(
                $request->getContent(),
                $request->header('X-Scell-Signature')
            );
        } catch (ScellException $e) {
            return response()->json(['error' => 'Signature invalide'], 400);
        }

        $event = $payload['event'];
        $data = $payload['data'];

        match ($event) {
            'invoice.validated' => $this->handleInvoiceValidated($data),
            'invoice.transmitted' => $this->handleInvoiceTransmitted($data),
            'signature.completed' => $this->handleSignatureCompleted($data),
            'signature.refused' => $this->handleSignatureRefused($data),
            'balance.low' => $this->handleBalanceLow($data),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function handleInvoiceValidated(array $data): void
    {
        // Traiter la facture validee
        $invoiceId = $data['id'];
        // ...
    }

    private function handleSignatureCompleted(array $data): void
    {
        // Telecharger le document signe
        $signatureId = $data['id'];
        $download = ScellApi::signatures()->download($signatureId, 'signed');
        // ...
    }
}
```

### Injection de dependances

```php
use Scell\Sdk\ScellApiClient;
use Scell\Sdk\Webhooks\WebhookVerifier;

class InvoiceService
{
    public function __construct(
        private readonly ScellApiClient $api
    ) {}

    public function createInvoice(Order $order): Invoice
    {
        return $this->api->invoices()->builder()
            ->invoiceNumber($order->generateInvoiceNumber())
            ->outgoing()
            ->facturX()
            // ...
            ->create();
    }
}
```

## Gestion des erreurs

```php
use Scell\Sdk\Exceptions\ScellException;
use Scell\Sdk\Exceptions\ValidationException;
use Scell\Sdk\Exceptions\AuthenticationException;
use Scell\Sdk\Exceptions\RateLimitException;

try {
    $invoice = $api->invoices()->create([...]);
} catch (ValidationException $e) {
    // Erreurs de validation
    foreach ($e->getErrors() as $field => $messages) {
        echo "$field: " . implode(', ', $messages);
    }
} catch (AuthenticationException $e) {
    // API Key invalide
    echo "Authentification echouee: {$e->getMessage()}";
} catch (RateLimitException $e) {
    // Limite de requetes atteinte
    $retryAfter = $e->getRetryAfter();
    echo "Reessayez dans {$retryAfter} secondes";
} catch (ScellException $e) {
    // Autre erreur API
    echo "Erreur: {$e->getMessage()}";
    echo "Code: {$e->getScellCode()}";
}
```

## Types et Enums

Le SDK utilise des enums PHP 8.2+ pour les valeurs predefinies:

```php
use Scell\Sdk\Enums\Direction;
use Scell\Sdk\Enums\OutputFormat;
use Scell\Sdk\Enums\InvoiceStatus;
use Scell\Sdk\Enums\SignatureStatus;
use Scell\Sdk\Enums\AuthMethod;
use Scell\Sdk\Enums\WebhookEvent;
use Scell\Sdk\Enums\Environment;

// Direction de facture
Direction::Outgoing; // Vente
Direction::Incoming; // Achat

// Format de sortie
OutputFormat::FacturX; // Factur-X PDF/A-3
OutputFormat::UBL;     // UBL 2.1
OutputFormat::CII;     // UN/CEFACT CII

// Methode d'authentification
AuthMethod::Email; // OTP par email
AuthMethod::Sms;   // OTP par SMS
AuthMethod::Both;  // Email + SMS

// Evenements webhook
WebhookEvent::InvoiceValidated;
WebhookEvent::SignatureCompleted;
WebhookEvent::BalanceLow;
```

## Configuration avancee

```php
use Scell\Sdk\Config;
use Scell\Sdk\ScellApiClient;

$config = new Config(
    baseUrl: 'https://api.scell.io/api/v1',
    timeout: 60,
    connectTimeout: 15,
    retryAttempts: 5,
    retryDelay: 200,
    verifySsl: true,
    webhookSecret: 'whsec_...',
);

$api = ScellApiClient::withApiKey('sk_live_...', $config);
```

## Tests

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer analyse

# Run all checks
composer check
```

## API Reference

### Resources

| Resource | Description |
|----------|-------------|
| `invoices()` | Gestion des factures electroniques |
| `signatures()` | Gestion des signatures electroniques |
| `companies()` | Gestion des entreprises |
| `balance()` | Consultation du solde |
| `webhooks()` | Gestion des webhooks |

### Webhook Events

| Event | Description |
|-------|-------------|
| `invoice.created` | Facture creee |
| `invoice.validated` | Facture validee et conforme |
| `invoice.transmitted` | Facture transmise au PDP |
| `invoice.rejected` | Facture rejetee |
| `signature.created` | Signature creee |
| `signature.signer_completed` | Un signataire a signe |
| `signature.completed` | Tous les signataires ont signe |
| `signature.refused` | Signature refusee |
| `signature.expired` | Signature expiree |
| `balance.low` | Solde bas (seuil configurable) |

## Requirements

- PHP 8.2+
- Guzzle 7.0+
- Laravel 11/12 (optionnel)

## Contributing

Les contributions sont bienvenues. Merci de:

1. Fork le repository
2. Creer une branche (`git checkout -b feature/amazing-feature`)
3. Commit les changements (`git commit -m 'Add amazing feature'`)
4. Push sur la branche (`git push origin feature/amazing-feature`)
5. Ouvrir une Pull Request

### Code Standards

- PSR-12 pour le style de code
- PHPStan niveau 8 minimum
- Tests pour toute nouvelle fonctionnalite

## Security

Si vous decouvrez une vulnerabilite, merci d'envoyer un email a security@scell.io plutot que d'ouvrir une issue publique.

## Changelog

Voir [CHANGELOG.md](CHANGELOG.md) pour l'historique des versions.

## License

MIT License. Voir [LICENSE](LICENSE) pour plus d'informations.

## Support

- Documentation: [docs.scell.io](https://docs.scell.io)
- Email: support@scell.io
- Issues: [GitHub Issues](https://github.com/scell-io/sdk-php/issues)
