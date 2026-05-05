# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.16.0 — 2026-05-06

### Added
- **`$client->fiscal->iscaSelfAttestationDownload(?string $subTenantId)`** — telecharge l'auto-attestation
  ISCA **NOMINATIVE** au format PDF binaire pour le tenant authentifie ou pour
  un sub_tenant specifique. Le PDF inclut l'identite nominative du beneficiaire
  (raison sociale, SIRET, TVA, adresse, contact, statut KYB/KYC) en plus du
  nom + version du logiciel. Le hash SHA-256 couvre l'identite — preuve
  cryptographique de la non-transferabilite.
- **`$client->fiscal->iscaMeasuresRegisterDownload()`** — registre des mesures ISCA (PDF).
- **`$client->fiscal->iscaTechnicalDossierDownload()`** — dossier technique ISCA (PDF, NF Z 42-025).

### Notes
- Backend requis : Scell.io v0.6.0+ (ledger increvable + attestation nominative).
- Auth : `tk_*` tenant key. La methode avec `$subTenantId` verifie l'appartenance
  cross-tenant cote backend (404 si IDOR).
- Bump : 1.15.0 -> 1.16.0

## 1.14.0 — 2026-05-03

### Added
- **Invoice Templates** : nouveau resource `$client->invoiceTemplates()` pour la personnalisation des factures et avoirs.
  - DTO `InvoiceTemplate` (scope, logo, couleurs, mentions custom, advanced_options).
  - 6 methodes : `list()`, `get()`, `create()`, `update()`, `delete()`, `markDefault()`.
  - 3 scopes : `system` (Scell), `tenant` (par tenant), `sub_tenant` (specifique).
  - Cascade de resolution serveur : explicit > sub_tenant default > tenant default > system default.
- **Daily Closure email + CSV** : les tenants actifs recoivent automatiquement un email quotidien avec CSV de cloture (format de marche, signed URL valable 5 jours).
- **Avoirs** : validation renforcee — `invoice_id` doit obligatoirement pointer sur une facture existante du meme tenant, en statut creditable, et non totalement avoiree. L'override des champs buyer/seller est maintenant interdit (heritage strict, autocertification ISCA).
- DTO `Invoice` et `CreditNote` : nouveau champ `invoiceTemplateId` (heritage automatique de la facture vers l'avoir).

### Notes
- Aucun breaking change : `invoice_template_id` est optionnel, defaut = template system.
- La personnalisation visuelle (logo, couleurs) est appliquee cote serveur lors de la generation PDF source. Factur-X XML : seul `custom_mentions` est ajoute aux conditions de paiement (BR-CO-26 conforme).

## 1.13.0 — 2026-05-03

### Added
- **B2C support** : nouveau flag `buyer_is_individual` pour les factures et avoirs avec acheteur particulier.
  - `InvoiceBuilder::buyerIndividual(string $name, Address|array $address)` — helper dedie pour B2C, marque automatiquement `buyer_is_individual=true` sans necessiter de SIRET.
  - `InvoiceBuilder::asB2c(bool $value = true)` — bascule explicite B2C/B2B sur un builder existant.
  - DTO `Invoice::$buyerIsIndividual` (bool) + `Invoice::isB2c()` / `Invoice::isB2b()`.
  - DTO `CreditNote::$buyerIsIndividual` + `CreditNote::isB2c()` / `CreditNote::isB2b()` — herite automatiquement de la facture.
  - Conformite Factur-X / UBL / CII : balises BT-46 (BuyerLegalOrganisation) / BT-47 (BuyerTaxIdentifier) / BT-48 (BuyerVATIdentifier) sont **omises** quand `buyer_is_individual=true` (BR-CO-26 EN16931).

### Changed
- `InvoiceBuilder::buyer(?string $siret, string $name, Address|array $address)` — premier parametre devient nullable. Compatibilite ascendante preservee : passer un SIRET fonctionne comme avant. En B2C, prefere `buyerIndividual()` pour la clarte.
- `TenantDirectInvoiceResource::create()` accepte `buyer_is_individual` (top-level) ou `buyer.is_individual` (imbrique) dans le payload. Les deux sont propages.

### Notes
- Aucun breaking change : tous les appels existants continuent de fonctionner sans modification.
- En B2C, le SIRET / VAT / legal_id sont optionnels cote API. Les mentions legales B2B (Code de commerce L441-10 : penalites de retard 3x taux legal, indemnite forfaitaire de recouvrement 40 EUR) sont automatiquement omises de Factur-X.

## 1.12.0 — 2026-04-15

### Added
- `SignatureBuilder::signatureOptions(array $options)` — signature_mode, signer_must_read, user_editable_data, timezone.
- `Signer::$message` (setter et property) — message custom envoye au signataire avec placeholder `{OTP}` (max 500 chars).
- `addSignaturePosition()` accepte un 6e parametre `$unit` (`'percent'` par defaut, `'pixel'` pour coordonnees absolues).

### Changed (BREAKING)
- `SignatureBuilder::uiConfig(array $config)` — signature changee : accepte un tableau associatif (anciennement 3 parametres positionnels `logoUrl`/`primaryColor`/`companyName`). Accepte les 21 champs UI alignes sur la spec OpenAPI.com (`sidebar_logo`, `sidebar_background_color`, `sidebar_text_color`, `header_*`, `footer_*`, `button_*`, `sign_button_*`, `hide_*`, `iframe_ancestors`).

### Removed (BREAKING)
- `SignatureBuilder::uiConfig($logoUrl, $primaryColor, $companyName)` (signature 3-params) supprimee. Utiliser `uiConfig(array $config)` avec les nouveaux champs spec OpenAPI.com (`sidebar_logo`, `sidebar_background_color`, etc.). Le champ `company_name` n'a pas d'equivalent (non supporte par OpenAPI.com).

### Fixed
- Documentation `addSignaturePosition()` : x/y sont en `'percent'` par defaut (0-100), pas pixels.

## [1.11.0] - 2026-04-07

### Fixed

- **Critical:** `InvoiceResource::normalizeCreatePayload()` no longer requires `invoice_number` — the field was dead code since v1.9.0 and caused an `undefined index` error when using the fluent builder

### Changed

- `InvoiceBuilder::invoiceNumber()` marked `@deprecated` — invoice numbers are server-generated since v1.9.0; the method is kept for backward compatibility but has no effect
- NF525 terminology replaced with ISCA across all PHPDoc comments and documentation
- `ScellTenantClient` now exposes `onboarding()` accessor returning `OnboardingResource`, consistent with `ScellApiClient`
- `HttpClient::SDK_VERSION` bumped to `1.11.0`
- PHPDoc examples updated: `sk_live_*`/`sk_test_*` replaced with `tk_live_*`/`tk_test_*` throughout

## [1.10.0] - 2026-04-05

### Added

- **OnboardingResource**: SuperPDP OAuth2 Authorization Code flow for partner onboarding
  - `createSession(array $data): OnboardingSession` — initialize onboarding session
  - `getSession(string $sessionId): OnboardingSession` — retrieve session status
  - `getSuperPDPAuthorizeUrl(string $sessionId): array` — get OAuth2 authorize URL (returns `authorize_url` + `state`)
  - `superpdpCallback(string $sessionId, string $code, string $state): array` — exchange OAuth2 code and provision tenant
- `ScellApiClient::onboarding()` accessor added

### Added (DTO)

- `OnboardingSession` DTO with full session fields

## [1.9.2] - 2026-03-30

### Added

- **FiscalResource ISCA document downloads**: new download methods for ISCA compliance documents
- Renamed all internal NF525 references to ISCA (conformite ISCA)

## [1.9.0] - 2026-03-30

### Changed

- **Server-generated invoice numbers**: `invoice_number` is no longer accepted as input when creating invoices. Numbers are assigned automatically by Scell.io (DRAFT prefix at creation, fiscal number at submit)
- `InvoiceBuilder::invoiceNumber()` is now a no-op (kept for backward compatibility)
- README and llms.txt updated to reflect auto-numbering behavior

## [1.8.0] - 2026-03-29

### Added

- **International invoicing**: optional SIRET, VAT number, and legal ID support
  - `Invoice` DTO: `sellerSiret`/`buyerSiret` now nullable; added `vatNumber`, `country`, `legalId` fields
  - `Company` DTO: `siret` now nullable; added `legalId`, `legalIdScheme` fields
  - Supports EU and non-EU invoices (UK, CH, etc.)

## [1.7.0] - 2026-03-29

### Added

- **CreditNoteResource** for `ScellClient` (Bearer token / dashboard): `list()`, `get()`, `create()`, `send()`, `download()`, `remainingCreditable()`
- **`InvoiceResource::submit()`**: `POST /invoices/{id}/submit` for submitting an invoice for processing
- **TenantDirectCreditNoteResource**: added `get()`, `update()`, `send()`, `download()`, `remainingCreditable()`
- 5 additional webhook events documented in README

### Fixed

- `Config::sandbox()` now uses the correct sandbox URL
- `HttpClient` User-Agent now uses `SDK_VERSION` constant instead of a hardcoded string
- `InsufficientBalanceException`: HTTP 402 now correctly mapped in `ScellException::fromResponse()`

## [1.6.1] - 2026-03-31

### Changed

- Clarified `ScellApiClient` PHPDoc: authentication mode, path normalization comment
- Minor documentation improvements

## [1.6.0] - 2026-03-31

### Fixed

- `Config::SANDBOX_BASE_URL` corrected from `https://sandbox.api.scell.io/api/v1` to `https://api.scell.io/api/v1` — sandbox routing is handled server-side by key prefix (`tk_test_*` vs `tk_live_*`), not by URL

## [1.5.0] - 2026-03-12

### Added

- **ScellApiClient Resources**: Full multi-tenant support with 8 new resource accessors: `subTenants()`, `fiscal()`, `stats()`, `billing()`, `creditNotes()`, `tenantInvoices()`, `directInvoices()`, `incomingInvoices()`
- **Sub-Tenants**: `SubTenantResource` with CRUD operations and `findByExternalId()`
- **Tenant Invoices**: `TenantInvoiceResource` for managing sub-tenant invoices (create, submit, update, delete, status)
- **Direct Invoices**: `TenantDirectInvoiceResource` for tenant-level invoices with bulk operations (`bulkCreate`, `bulkSubmit`, `bulkStatus`)
- **Incoming Invoices**: `TenantIncomingInvoiceResource` for supplier invoices (create, accept, reject, markPaid)
- **Signature Audit Trail**: `SignatureResource::auditTrail()` for retrieving signature action history

### Fixed

- Corrected API key prefix from `sk_` to `tk_` across documentation and examples

## [1.4.0] - 2026-02-08

### Added

- **Fiscal Compliance** (LF 2026): `FiscalResource` with 22 methods covering compliance dashboard, integrity checks, closings, FEC export, attestation, ledger entries, kill switch, anchors, rules, and forensic export
- **Billing**: `BillingResource` with invoices, usage, top-up, and transactions
- **Stats**: `StatsResource` with overview, monthly, and sub-tenant overview
- **API Keys**: `ApiKeyResource` with CRUD operations
- **Bulk Operations**: `bulkCreate()`, `bulkSubmit()`, `bulkStatus()` on `TenantDirectInvoiceResource`
- New DTOs: `FiscalCompliance`, `FiscalIntegrityReport`, `FiscalClosingSummary`, `FiscalEntry`, `FiscalKillSwitchStatus`, `FiscalRule`, `FiscalAnchor`, `FiscalAttestation`, `BillingInvoice`, `BillingUsage`, `BillingTransaction`, `StatsOverview`, `StatsMonthly`, `ApiKey`

## [1.2.0] - 2026-01-24

### Added

- **Mark Paid Support**: Mark incoming invoices as paid (mandatory status in French e-invoicing lifecycle)
  - `$client->invoices()->markPaid($id, $data)` - Mark invoice as paid with optional payment reference

- **Download Invoice Files**: Download original invoice files as binary content
  - `$client->invoices()->downloadContent($id)` - Download PDF (Factur-X)
  - `$client->invoices()->downloadContent($id, 'xml')` - Download XML (UBL/CII)

- **New Invoice Fields**:
  - `paid_at` - Payment timestamp
  - `payment_reference` - Bank transfer ID, check number, etc.
  - `payment_note` - Optional payment note

- **New Invoice Status**:
  - `InvoiceStatus::Paid` - Invoice has been marked as paid

- **New Webhook Event**:
  - `invoice.incoming.paid` - Triggered when incoming invoice is marked as paid

- **New Helper Methods**:
  - `Invoice::isPaid()` - Check if invoice has been paid
  - `Invoice::isIncoming()` - Check if invoice is from a supplier

- **HttpClient Enhancement**:
  - `getRaw()` method for downloading binary content (PDF, XML files)

## [1.1.0] - 2026-01-24

### Added
- Incoming invoices support (supplier invoices)
  - `incoming()` - List incoming invoices with filtering
  - `accept()` - Accept an incoming invoice
  - `reject()` - Reject an incoming invoice with reason code
  - `dispute()` - Dispute an incoming invoice
- New enums for incoming invoice workflows
  - `RejectionCode` - Rejection codes (incorrect_amount, duplicate, unknown_order, incorrect_vat, other)
  - `DisputeType` - Dispute types (amount_dispute, quality_dispute, delivery_dispute, other)
- New webhook events for incoming invoices
  - `invoice.incoming.received` - Incoming invoice received
  - `invoice.incoming.accepted` - Incoming invoice accepted
  - `invoice.incoming.rejected` - Incoming invoice rejected
  - `invoice.incoming.disputed` - Incoming invoice disputed

## [1.0.0] - 2026-01-24

### Added
- Initial release of Scell.io PHP SDK
- Support for electronic invoicing (Factur-X, UBL, CII)
- Support for electronic signatures (eIDAS EU-SES)
- Laravel 11/12 integration with auto-discovery
- Fluent builders for invoices and signatures
- Webhook verification with HMAC-SHA256
- Retry middleware with exponential backoff
- Comprehensive DTOs and Enums
- Full error handling with typed exceptions

### Features
- `ScellClient` for Bearer token authentication (dashboard)
- `ScellApiClient` for API Key authentication (server-to-server)
- Resources: Invoices, Signatures, Companies, Balance, Webhooks
- Laravel Facades: `Scell`, `ScellApi`, `ScellWebhook`

### Requirements
- PHP 8.2+
- Guzzle 7.0+
- Laravel 11/12 (optional)
