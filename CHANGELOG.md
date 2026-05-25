# Changelog

All notable changes to this project will be documented in this file.

## [2.15.0] - 2026-05-25

### Added

- **Initiales multi-pages — `initials_block.positions[]`** : nouveau format permettant
  de définir UNE position différente PAR PAGE pour le bloc paraphe. Chaque entrée
  peut surcharger `font_size`, `color`, `bold` au-delà des valeurs du bloc.
  - Nouveau DTO `Scell\Sdk\DTOs\InitialsPosition` (page, x, y, unit, fontSize, color,
    bold, pageWidthPx, pageHeightPx).
  - `InitialsBlock` accepte désormais un argument `positions: ?array<InitialsPosition>`.
  - Constructeur d'agrément `InitialsBlock::withPositions([...])`.
  - Champ `InitialsBlock::bold` ajouté (défaut bloc, surchargeable per-position).

```php
use Scell\Sdk\DTOs\InitialsBlock;
use Scell\Sdk\DTOs\InitialsPosition;

$block = InitialsBlock::withPositions(
    positions: [
        new InitialsPosition(page: 1, x: 90, y: 90),
        new InitialsPosition(page: 2, x: 88, y: 92, fontSize: 12),
        new InitialsPosition(page: 3, x: 85, y: 90, color: '#AA0000'),
    ],
    fontSize: 10,
    color: '#000000',
);
```

### Changed

- `InitialsBlock::toArray()` émet `positions[]` quand fourni et OMET `position`/`pages`
  legacy pour éviter toute ambiguïté côté serveur (qui priorise déjà `positions[]`).
- `InitialsBlock::position` devient nullable (`?BlockPosition`) — usage positionnel
  `new InitialsBlock(true, $pos, ...)` continue de fonctionner.

### Compatibility

- **100% rétrocompatible** : le format legacy `position + pages` (string `'all'` /
  `'except_last'` / `int[]`) reste pleinement supporté.
- Si les deux formats sont fournis, `positions[]` prévaut (alignement backend).

## [2.13.1] - 2026-05-24

### Added

- **`callback_url`** — Le tenant peut fournir une URL de callback à la
  création du devis. Après acceptation ou refus via le viewer public,
  le buyer est redirigé vers cette URL avec query string
  `?status=signed|refused&quote_id=<UUID>&quote_number=<num>&reason=<txt>`.
  Permet au tenant de capturer le buyer dans son propre flow métier
  (page de remerciement custom, dashboard client, automation
  post-signature).
- **`QuoteBuilder::callbackUrl(string $url)`** — méthode fluent pour
  définir l'URL au montage du devis.
- **`Quote::$callbackUrl`** — propriété DTO + mapping `fromArray`.

### Backend

- Migration `quotes.callback_url` (VARCHAR(500) nullable).
- Validation `nullable url max:500` dans `StoreQuoteRequest` et
  `UpdateQuoteRequest`.
- `QuoteResource` + `QuotePublicResource` exposent `callback_url`.
- Viewer SPA (`app.scell.io`) : helper `buildCallbackUrl()` + redirect
  `window.location.href` après accept/refuse avec query string enrichie.
  Fallback page Scell.io si URL invalide.

## [2.13.0] - 2026-05-21

### Added

- **`QuotePaymentScheduleResource`** — echeancier de paiement complet pour les devis :
  - `list(string $quoteId): PaymentScheduleLine[]` — GET `/v1/quotes/{quote}/payment-schedule`
  - `set(string $quoteId, array $lines): PaymentScheduleLine[]` — POST (remplace entierement)
  - `patch(string $quoteId, array $changes): PaymentScheduleLine[]` — PATCH (add/update/remove)
  - `delete(string $quoteId): void` — DELETE (supprime toutes les lignes)
  - `summary(string $quoteId): PaymentSummary` — GET `/v1/quotes/{quote}/payment-summary` (tracker)
  - `convertLine(string $quoteId, string $lineId, array $options): Invoice` — POST convert ligne → acompte
  - `presets(): array` — GET `/v1/payment-schedule-presets` (presets preconfigures)
- **`BrandingResource`** — configuration de marque tenant et sub-tenant :
  - `getTenant(): Branding`, `updateTenant(array $data): Branding`
  - `getSubTenant(string $id): Branding`, `updateSubTenant(string $id, array $data): Branding`
  - `logoUploadUrlTenant(string $mimeType): array` — URL presignee S3 upload logo tenant
  - `logoUploadUrlSubTenant(string $id, string $mimeType): array` — URL presignee sub-tenant
  - `previewTenant(): string`, `previewSubTenant(string $id): string` — apercu HTML/PDF
- **`InvoiceResource::sendByEmail()`** — envoi facture par email :
  - `sendByEmail(string $invoiceId, array $options = []): array` — POST `/v1/invoices/{id}/send-by-email`
  - Options : `email`, `subject`, `message`, `cc`, `bcc`, `force_branding`
- **`QuoteBuilder::withPaymentSchedule(array $lines): self`** — echeancier lors de la creation du devis
- **Acces echeancier depuis `QuoteResource`** : `$api->quotes()->paymentSchedule()->list($quoteId)`
- **Nouveaux DTOs** :
  - `PaymentScheduleLine` — ligne d'echeancier (order, amountType, amountValue, status, dueDate, milestoneLabel, invoiceId, helpers: isPending/isInvoiced/isCancelled/isLocked/isOverdue)
  - `PaymentSummary` — tracker solde (totalTtc, netInvoiced, remaining, percentInvoiced, linesTotal, helpers: isComplete/allScheduleLinesInvoiced)
  - `Branding` — configuration marque (logoUrl, primaryColor, emailFooter, emailSignature, isComplete, helper: isReady)
- **`Buyer::$billingEmail`** — adresse email de facturation distincte de l'email de contact
- **5 exceptions typees** dans `Scell\Sdk\Exceptions\` :
  - `QuoteNotEditableException` (409 QUOTE_NOT_EDITABLE) — devis non modifiable (signe/accepte/facture)
  - `ScheduleLineAlreadyInvoicedException` (422 SCHEDULE_LINE_ALREADY_INVOICED)
  - `ScheduleSumExceedsTotalException` (422 SCHEDULE_SUM_EXCEEDS_TOTAL)
  - `BuyerHasNoEmailException` (422 BUYER_HAS_NO_EMAIL)
  - `InvoiceBrandingIncompleteException` (422 INVOICE_BRANDING_INCOMPLETE)
- **`BrandingResource`** enregistree dans `ScellApiClient::branding()` et `ScellClient::branding()`

## [2.12.0] - 2026-05-16

### Added — Signature blocks (paraphe + mentions juridiques + date)

Trois nouveaux champs **optionnels** sur `POST /api/v1/signatures`, exposes
via `SignatureBuilder` ou directement sur `SignatureResource::create()`.
100% retrocompatibles : un payload pre-v2.12.0 reste valide a l'identique.

- **`initials_block`** — bloc paraphe (initiales automatiques sur les pages
  du PDF signe). Modes `'auto'` / `'custom'`, source `'signer_name'` /
  `'custom'`, pages `'all'` / `'except_last'` / liste explicite.
- **`mentions[]`** — mentions juridiques per-signer (ex: « Lu et approuve »,
  « Bon pour accord »). Champ `signer_index` (0-based), position avec
  `page` obligatoire, `required` + `fallback_text`.
- **`date_block`** — bloc date du jour grave sur le PDF (format PHP `date()`
  + timezone IANA). La `page` peut etre `'last'`.

Nouveaux DTOs (tous `readonly`, validation defensive au constructeur) :

| DTO | Role |
|-----|------|
| `Scell\Sdk\DTOs\InitialsBlock` | Configuration bloc paraphe. |
| `Scell\Sdk\DTOs\Mention` | Mention juridique per-signer. |
| `Scell\Sdk\DTOs\DateBlock` | Configuration bloc date du jour. |
| `Scell\Sdk\DTOs\BlockPosition` | Position partagee (page + x/y/w/h + unit). |

Nouvelles methodes fluent sur `SignatureBuilder` :

- `initialsBlock(InitialsBlock|array $config)`
- `mentions(array $mentions)` — remplace toute liste precedente.
- `addMention(Mention|array $mention)` — append incremental.
- `dateBlock(DateBlock|array $config)`

Chaque setter accepte un tableau associatif (snake_case) OU un DTO type.

### Tests

- `tests/SignatureBlocksTest.php` — 10 nouveaux tests (serialization wire,
  retrocompat, support DTO + array, validation defensive, round-trip
  `BlockPosition`). Total suite : 68 tests / 285 assertions, PHPStan OK.

### Compat

- Aucun champ existant modifie ou supprime.
- Tous les DTOs nouveaux : pas d'impact sur le code consommateur pre-v2.12.0.
- Suit la cascade backend Scell.io : si le payload ne contient pas le champ,
  l'API se comporte comme avant (pas de bloc).

## [2.11.0] - 2026-05-15

### Added

- **QuoteResource** (`src/Resources/QuoteResource.php`) — resource complète pour les devis :
  - CRUD : `create()`, `list()`, `get()`, `update()`, `delete()`
  - Cycle de vie : `send()`, `cancel()`, `duplicate()`
  - Conversion : `convertToDeposit()`, `convertToBalance()` → retournent un `Invoice`
  - Audit : `auditLog()` → retourne `QuoteAuditEntry[]`
  - Lien public : `regeneratePublicLink()`, `revokePublicLink()`
  - PDF : `pdf()` (binaire), `preview()` (binaire sans persistance)
  - Builder : `builder()` → `QuoteBuilder`
- **QuoteBuilder** (`src/Builders/QuoteBuilder.php`) — builder fluent :
  `buyer()`, `buyerId()`, `buyerIndividual()`, `buyerAsIndividual()`, `shippingAddress()`,
  `line()`, `lines()`, `addLineDto()`, `validUntil()`, `signatureRequired()`,
  `paymentTerms()`, `notes()`, `metadata()`, `subTenantId()`, `companyId()`,
  `externalId()`, `depositSchedule()`, `currency()`, `build()`, `create()`
- **DTO Quote** (`src/DTOs/Quote.php`) — représente un devis avec helpers :
  `isDraft()`, `isSent()`, `isAccepted()`, `isRefused()`, `isCancelled()`,
  `isConverted()`, `isExpired()`, `isSigned()`, `isB2c()`, `isConvertible()`, `isSandbox()`
- **DTO QuoteLine** (`src/DTOs/QuoteLine.php`) — ligne de devis avec `create()` + `toArray()`
- **DTO QuoteSignature** (`src/DTOs/QuoteSignature.php`) — état signature électronique du buyer
- **DTO QuoteAuditEntry** (`src/DTOs/QuoteAuditEntry.php`) — entrée du journal d'audit
- **Invoice DTO extensions** (rétrocompatibles, tous champs `null` par défaut) :
  - `?string $invoiceType` — `'standard'` | `'deposit'` | `'balance'`
  - `?string $parentQuoteId` — ID du devis source (si convertie depuis devis)
  - `?string[] $parentInvoiceIds` — IDs des acomptes (si facture de solde)
- **InvoiceBuilder extensions** (5 nouvelles méthodes) :
  `parentQuoteId()`, `invoiceType()`, `depositAmount()`, `depositPercent()`, `depositLabel()`
- **HttpClient** : ajout de `postRaw()` pour les requêtes POST retournant du binaire (PDF preview)
- `ScellClient::quotes()` et `ScellApiClient::quotes()` exposent la `QuoteResource`

### Compat

- 100% rétrocompatible : les 3 nouveaux champs Invoice sont nullable et defaultent à `null`.
  Les payloads pre-v2.11.0 hydratent correctement sans changement.
- `InvoiceBuilder` : les 5 nouvelles méthodes sont additives, aucune méthode existante modifiée.

## [2.10.0] - 2026-05-15

### Fixed (mirror du fix backend du meme jour)

- `GET /tenant/fiscal/closings` retournait 500 cote API des qu'une cloture
  journaliere etait ancree OpenTimestamps (la colonne BYTEA `ots_proof`
  faisait crasher `json_encode()`). Le backend l'a corrige en exposant le
  receipt en base64. Ce SDK relaye le nouveau champ via
  `FiscalClosingSummary::$otsProofBase64`.

### Added

- DTO `FiscalClosingSummary` enrichi avec les champs :
  - `subTenantId`, `firstSequenceNumber`, `lastSequenceNumber`
  - `closingHash` (clarification de `chainHash`)
  - `previousClosingHash`
  - `totals` (raw) et `cumulativeTotals`
  - `csvPath`, `csvHash` (CSV de cloture sur S3)
  - `otsProofBase64`, `otsStatus`, `otsSubmittedAt`,
    `otsBitcoinConfirmedAt`, `otsCalendars`
  - `metadata`
- Methodes d'aide sur `FiscalClosingSummary` :
  - `hasOtsProof(): bool` — receipt OpenTimestamps disponible ?
  - `decodeOtsProof(): ?string` — decode le base64 vers le binaire
    `.ots` (verifiable avec `ots verify` ou toute librairie OTS).

### Compat

- 100% retrocompatible : tous les nouveaux champs sont optionnels et
  defaultent a `null`. Les payloads pre-v2.10.0 (avec uniquement
  `chain_hash`, `total_debit`, `total_credit`) continuent d'hydrater
  correctement.

### Changed

- `HttpClient::SDK_VERSION` bumpe de `'2.8.0'` (drift historique par
  rapport au tag Git) a `'2.10.0'`.

## [2.9.0] - 2026-05-11

### Added

- `SubTenantResource::superpdpAuthorize(string $id): SuperPDPAuthorizeUrl` : nouvel endpoint `POST /tenant/sub-tenants/{id}/superpdp-authorize` qui retourne une URL OAuth authorize fraiche (prefilled `login_hint` + `superpdp_company_number`) + un `state` anti-CSRF. A utiliser pour relancer le tunnel SuperPDP d'un sub-tenant (statut `pending_superpdp`, `superpdp_failed`, ou lorsque `refreshSuperPDPStatus()` retourne `MISSING_ACCESS_TOKEN`).
- Nouveau DTO `Scell\Sdk\DTOs\SuperPDPAuthorizeUrl` (`authorizeUrl`, `state`).
- `SubTenantResource::delete()` accepte desormais une option `cascade` pour supprimer en cascade les Companies du sub-tenant : `$api->subTenants()->delete($id, ['cascade' => true])`. Le backend retourne `companies_deleted` dans la reponse 200.

### Changed

- `SubTenantResource::delete(string $id)` -> `delete(string $id, array $options = []): array`. Le retour `void` devient `array{message?: string, companies_deleted?: int}`. **BC : les appels existants qui ignorent la valeur de retour continuent de fonctionner.** Seule rupture potentielle : un test qui type-hint `void` sur le retour.
- `HttpClient::delete(string $path)` -> `delete(string $path, array $query = []): array`. BC compatible (parametre optionnel).
- PHPDoc enrichi sur `SubTenantResource::refreshSuperPDPStatus()` documentant la nouvelle reponse 422 `MISSING_ACCESS_TOKEN` (avec `authorize_url` + `state`) et les codes `RATE_LIMITED` / `REFRESH_FAILED`.

### Errors a gerer cote consommateur

- `422 SUB_TENANT_HAS_COMPANIES` (avec `companies_count`) : le sub-tenant a des Companies actives mais aucune piece fiscale. Le caller peut retenter avec `['cascade' => true]`.
- `422 SUB_TENANT_HAS_FISCAL_ENTRIES` : refus systematique (compliance ISCA, factures ou avoirs presents). Aucun force flag possible.
- `422 MISSING_ACCESS_TOKEN` sur `refreshSuperPDPStatus()` : ouvrir `authorize_url` du payload (ou regenerer via `superpdpAuthorize()`).

## [2.8.0] - 2026-05-11

### Breaking

- Suppression du champ `company_id` du DTO `Scell\Sdk\DTOs\ApiKey` et de toutes les reponses `ApiKeyResource` (`list`, `create`). Le backend (refonte 2026-05-11) a supprime la colonne `api_keys.company_id` : une cle `sk_*` appartient desormais au tenant, jamais a une `company_id` precise.
- Pour cibler un sub-tenant lors d'une operation (creation de facture, signature, etc.), passer `sub_tenant_id` dans le payload POST. Sans `sub_tenant_id`, l'action utilise `tenant.default_company_id` du master tenant.

### Changed

- `ApiKeyResource::create(array $data)` : ne plus envoyer `company_id` dans `$data`. Si vous le passiez, le backend l'ignorera silencieusement. Aucune signature de methode n'a change cote SDK (passe-through array).
- Nouvelles erreurs HTTP a gerer cote consommateur :
  - `401 TENANT_NOT_RESOLVED` : la cle `sk_*` n'est pas rattachee a un tenant actif.
  - `404 SUB_TENANT_NOT_FOUND` : `sub_tenant_id` fourni mais introuvable / hors scope tenant.
  - `422 NO_ISSUER_COMPANY` : pas de `sub_tenant_id` et `tenant.default_company_id` non defini.

### Migration

- Mettre a jour tout code qui lit `$apiKey->companyId` ou `$apiKey->company_id` : ce champ n'existe plus, le SDK ne le retournait deja plus via le DTO `readonly`.
- Si vous appeliez `$client->apiKeys()->create(['company_id' => $id, ...])`, retirer la cle ; la cle creee couvre desormais l'ensemble du tenant.
- Pour emettre une facture / signature scopee a un sub-tenant, ajouter `sub_tenant_id` au payload `create()` :

```php
$invoice = $client->tenantDirectInvoices()->create([
    'sub_tenant_id' => $subTenantId, // remplace l'ancien 'company_id'
    'buyer' => [...],
    'lines' => [...],
]);
```

## [2.7.1] - 2026-05-10

### Fixed
- `SubTenantResource::getSuperPdpStatus`, `refreshSuperPdpStatus` et `getResumeUrl` envoyaient sur `/sub-tenants/{id}/...` au lieu de `/tenant/sub-tenants/{id}/...`. L'ancien path résolvait sur le bloc Sanctum (dashboard SPA) → 401 systématique pour les clés API `sk_*`. Workaround utilisateur : appel HTTP direct au bon endpoint.


The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),

## [2.7.0] - 2026-05-10

### Added

- **`TenantSignatureResource`** : nouvelle ressource pour les 4 endpoints URL-nested cote tenant (auth `X-API-Key sk_*` sans contrainte `company_id`) :
  - `list(array $filters = [])` -> `GET /api/v1/tenant/signatures` (toutes les signatures du tenant : parent + tous sub-tenants)
  - `get(string $id)` -> `GET /api/v1/tenant/signatures/{id}`
  - `listForSubTenant(string $subTenantId, array $filters = [])` -> `GET /api/v1/tenant/sub-tenants/{subTenantId}/signatures` (anti-IDOR via middleware `sub-tenant`)
  - `getForSubTenant(string $subTenantId, string $id)` -> `GET /api/v1/tenant/sub-tenants/{subTenantId}/signatures/{id}`
- **`ScellTenantClient::signatures()`** : expose la nouvelle ressource (alongside `invoices()`, `creditNotes()`).
- **`ScellApiClient::tenantSignatures()`** : meme pattern que `tenantInvoices()` pour les integrations server-side qui veulent rester sur un seul client.
- Filtres de liste : `status` (pending|completed|refused|expired), `environment` (sandbox|production), `per_page` (max 100), `page`. Les valeurs `null` sont filtrees, `SignatureStatus` enum supporte.
- Tests unitaires `tests/TenantSignatureResourceTest.php` (6 tests) couvrant les 4 endpoints + memoization sur les deux clients.

### Why

Le scope `tenant_id` etait deja accessible via `SignatureResource::list()` (depuis v2.6.0) mais necessitait une cle API attachee a une `company_id`. Les master tenants sans `company_id` (ou souhaitant cross-company) recevaient `403 COMPANY_REQUIRED`. La nouvelle surface URL-nested (alignee sur `/v1/tenant/invoices` et `/v1/tenant/credit-notes`) leve cette contrainte et donne un acces uniforme parent + tous sub-tenants.

### Backward compatibility

100% retro-compatible. `SignatureResource::list()` / `get()` continuent de fonctionner inchanges (auth `api.key` avec `company_id` requise). Les operations write (create, remind, cancel, download, audit-trail) restent sur `/v1/signatures` via `SignatureResource`.

## [2.6.0] - 2026-05-10

### Added

- **`SignatureResource::list()`** : nouveau filtre documente `sub_tenant_id` (anti-IDOR : scope vers un sub-tenant du tenant courant, 403 sinon). Les filtres `status`, `environment`, `company_id`, `per_page`, `page` continuent de fonctionner.
- **`SignatureResource::get()`** : desormais utilisable avec auth `sk_live_*` / `sk_test_*` (avant : Sanctum SPA only).

### Changed

- **Scope de `GET /api/v1/signatures` et `GET /api/v1/signatures/{id}`** : le backend passe d'un scope `user_id` a un scope `tenant_id` (via `company.tenant_id`). Les SDKs utilisant `sk_live_*` / `sk_test_*` voient desormais toutes les signatures du tenant et de ses sub-tenants, et non plus celles du seul user createur.

### Fixed

- **Bug backend (cote API)** : `GET /api/v1/signatures` retournait 500 sous auth `api.key` car `$request->user()` n'etait pas resolu. Fixe cote backend ; aucune modification cote SDK n'est requise pour beneficier du fix.

## [2.4.0] - 2026-05-10

### Fixed (CRITICAL — DTO de-serialization bug)

- **`CompanyData::fromArray()`** : lisait `data['address']['line1']` (nested) alors que l'API renvoie l'adresse à plat (`data['address_line1']`, `data['postal_code']`, `data['city']`, `data['country']`). Tous les champs adresse arrivaient en chaîne vide depuis v2.0.0. Le DTO supporte désormais les deux shapes (flat preferred, nested kept for backward compat).
- **`SireneLookupResult::fromArray()`** : lisait `payload['sirene_lookup_succeeded']` au niveau racine alors que l'API n'a JAMAIS exposé ce champ — elle expose `data.sirene_lookup_failed: true` (négation) au cas manual_entry, ou rien au cas success. Le flag `$sireneLookupSucceeded` était donc systématiquement `false` après une réponse réussie.

### Added

- **`CompanyData::$legalName`** (BS-46 alias raison sociale, snake_case API : `legal_name`).
- **`CompanyData::$creationDate`** (renommage de `$createdAt` pour matcher l'API `creation_date` ; `$createdAt` reste lisible en fallback dans `fromArray`).
- **`CompanyData::$employeeRange`** (champ INSEE `employee_range`).
- **`SireneLookupResult::$manualEntryRequired`** (bool) — true quand les deux providers (Etalab + INSEE) sont en échec et que le widget doit basculer en saisie manuelle.
- **`SireneLookupResult::$code`** (?string) — code retour API : `'SIRENE_MANUAL_ENTRY_REQUIRED'`, `'SIRENE_NOT_FOUND'`, `null` au cas success.

### Tests

- `tests/SireneLookupResultTest.php` (5 tests) : couvre la vraie shape capturée en prod (RL CONSEIL Etalab success, Microsoft manual_entry fallback, SIRET_NOT_FOUND, retro-compat nested address, payload vide).
- `HttpClientAuthTest::sdk_version_constant_is_in_sync_with_release` mis à jour (`'2.4.0'`).

### Tag

```bash
git tag -a v2.4.0 -m "fix(dto): correct CompanyData address parsing + SireneLookupResult discriminant"
git push origin v2.4.0
```

---

## [2.3.0] - 2026-05-10

### Added

- **`HttpClient::withPublishableKey(string $key): self`** — nouveau mode d'auth qui ecrit le header `X-Publishable-Key`. Permet enfin d'appeler les endpoints widget (`/widget/onboarding/sirene/lookup`, `/widget/onboarding/sub-tenant`) qui exigent ce header specifique.
- **`ScellApiClient::withPublishableKey(string $key, ?Config $config = null): self`** — factory statique cohérente avec `withApiKey()`.
- **`ScellPublicClient`** — nouvelle classe dediee aux contextes widget public (mirror du `ScellPublicClient` du SDK JS `@scell/sdk`). Accepte une cle `pk_live_*` / `pk_test_*` et expose uniquement la `OnboardingResource`.
- Tests : `tests/HttpClientAuthTest.php` (11 tests, lock chacun des 4 modes d'auth + sandbox routing pk_/sk_).

### Fixed

- **Bug doc critique** : `OnboardingResource::lookupSirene()` documentait `ScellApiClient::withApiKey('pk_live_...')` qui envoyait `X-API-Key: pk_live_*` (rejete cote serveur, 401). Le docblock pointe desormais vers `ScellPublicClient` / `withPublishableKey()`.
- **Drift `HttpClient::SDK_VERSION`** : la constante etait restee a `'1.12.0'` malgre les releases successives. Synchronisee a `'2.3.0'` (impacte le `User-Agent` envoye par chaque requete).

### Migration

Aucun breaking change — les API existantes (`withApiKey`, `withTenantKey`, `withBearerToken`) sont inchangees. Si vous utilisiez le hack non-fonctionnel `ScellApiClient::withApiKey('pk_live_...')` pour le widget, remplacer par `ScellApiClient::withPublishableKey('pk_live_...')` ou `new ScellPublicClient('pk_live_...')`.

### Tag

```bash
git tag -a v2.3.0 -m "feat(http): add withPublishableKey() for widget endpoints (X-Publishable-Key header)"
git push origin v2.3.0
```

---

## [2.2.0] - 2026-05-10

### Added

- `BillingResource::payInvoice(string $invoiceId): PaymentIntent` — initie le paiement Stripe d'une facture plateforme via `POST /api/v1/tenant/billing/invoices/{id}/pay`. Retourne un `PaymentIntent` avec `clientSecret` a passer a Stripe.js `confirmCardPayment()`.
- DTO `Scell\Sdk\DTOs\PaymentIntent` — champs : `clientSecret`, `paymentIntentId`, `amount` (centimes), `currency` (ISO 4217), `status`.

### Errors

- `ScellException` (404) si la facture n'appartient pas au tenant
- `ScellException` (422) si le statut de la facture ne permet pas le paiement (draft, paid, cancelled)

---

## [2.1.0] - 2026-05-08

### Added

- `TenantDirectInvoiceResource::download(string $invoiceId, string $format = 'facturx'): string` — telecharge le binary d'une facture du tenant. Comble le gap v2 ou `tenantInvoices` n'avait pas de download (l'endpoint v1 `/tenant/invoices/{id}/download` etait supprime, et le v2 company-scoped `/invoices/{id}/download/pdf` retournait 403 COMPANY_REQUIRED avec une cle tenant).
- `TenantInvoiceResource::download(string $invoiceId, string $format = 'facturx'): string` — alias direct (sans subTenantId).
- `TenantInvoiceResource::downloadForSubTenant(string $subTenantId, string $invoiceId, string $format = 'facturx'): string` — variant scope sub-tenant strict (404 si la facture n'appartient pas au sub-tenant ET au tenant).
- Support des 3 formats : `'facturx'` (defaut, PDF/A-3 + XML CII embarque), `'pdf'` (rendu visuel pur), `'xml'` (UBL ou CII brut).

### Backend endpoints (consommes par ces methodes)

- `GET /api/v1/tenant/invoices/{invoiceId}/download[?format=]`
- `GET /api/v1/tenant/sub-tenants/{subTenantId}/invoices/{invoiceId}/download[?format=]`

Le scope tenant_id est verifie cote serveur via la company associee a la facture (ownership chain : invoice → company → tenant). Le scope sub-tenant rajoute un filtre strict sur `companies.sub_tenant_id`.

### Tag

```bash
git tag -a v2.1.0 -m "feat(invoices): add tenant-scoped invoice download (fix v2 SDK gap)"
git push origin v2.1.0
```

### Notes

- Pas de breaking change. Composer ne lit pas `version` depuis composer.json (regle stricte du projet : tag Git only).
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.0.0 — 2026-05-08

Major release. Aligne le SDK PHP sur Scell.io API v2 : nouveau modele
d'onboarding pilote par SuperPDP, suppression du champ legacy
`kyc_status` sur `SubTenant`, ajout de 5 nouveaux endpoints (lookup
Sirene, creation SubTenant via widget, statut SuperPDP, refresh,
resume URL).

> Aucune entree `version` n'est ajoutee a `composer.json`. Composer lit
> la version depuis le tag Git `v2.0.0` (regle stricte projet).

### Breaking Changes

- **`SubTenant` n'expose plus `kycStatus` / `kycVerifiedAt` / `kycDelegated`.**
  Le backend ne renvoie plus ces champs.
- **Nouveau champ obligatoire `OnboardingStatus $onboardingStatus`** sur
  le constructeur de `SubTenant` (BackedEnum, 6 valeurs).
- L'endpoint `refreshSuperPDPStatus` est rate-limite a 1 requete / minute /
  sub-tenant. Le 429 est expose comme `RateLimitException`.

### Added

- **`Scell\Sdk\Enums\OnboardingStatus`** — BackedEnum 6 valeurs :
  `PendingSuperPDP`, `SuperPDPRedirected`, `SuperPDPAuthorized`,
  `SuperPDPPendingReview`, `Active`, `SuperPDPFailed`. Helpers
  `isTerminal()` / `isInProgress()`.
- **Nouveaux DTOs** :
  - `RecommendedAction` (i18n FR/EN structure + helpers
    `title($locale)`, `message($locale)`, `ctaLabel($locale)`)
  - `SubTenantSummary` (combine `SubTenant` + `RecommendedAction`)
  - `CompanyData` (resultat lookup Sirene normalise)
  - `SireneLookupResult` (`data: ?CompanyData`, `sireneLookupSucceeded: bool`)
  - `CreateSubTenantResult` (`subTenant`, `recommendedAction`, `resumeUrl`)
  - `ResumeUrlResult` (`resumeUrl`, `expiresAt: DateTimeImmutable`)
- **`SubTenant`** nouveaux champs : `onboardingStatus`,
  `superpdpCompanyVerificationStatus`,
  `superpdpUserIdentityVerificationStatus`, `lastPolledAt`,
  `resumeUrl`, `contactFirstName`, `contactLastName`. Helpers
  `isOnboarded()`, `isPending()`, `hasFailed()`.
- **`SubTenantResource::getSuperPDPStatus($id): SubTenantSummary`**
  — `GET /sub-tenants/{id}/superpdp-status`.
- **`SubTenantResource::refreshSuperPDPStatus($id): SubTenantSummary`**
  — `POST /sub-tenants/{id}/superpdp-status/refresh` (rate-limite).
- **`SubTenantResource::getResumeUrl($id): ResumeUrlResult`**
  — `POST /sub-tenants/{id}/resume-url`.
- **`OnboardingResource::lookupSirene($siret): SireneLookupResult`**
  — `POST /widget/onboarding/sirene/lookup` (publishable-key).
- **`OnboardingResource::createSubTenant($payload): CreateSubTenantResult`**
  — `POST /widget/onboarding/sub-tenant` (publishable-key). Accepte
  un `CompanyData` instance ou un array brut.

### Migration Guide

Remplacer `kycStatus` par `onboardingStatus` :

| Legacy `kycStatus` | Nouveau `OnboardingStatus`                                                              |
|--------------------|-----------------------------------------------------------------------------------------|
| `'pending'`        | `PendingSuperPDP`, `SuperPDPRedirected`, `SuperPDPAuthorized`, `SuperPDPPendingReview`  |
| `'verified'`       | `Active`                                                                                |
| `'rejected'`       | `SuperPDPFailed`                                                                        |

```php
// AVANT (v1.x)
if ($subTenant->kycStatus === 'verified') { /* ... */ }

// APRES (v2.0)
use Scell\Sdk\Enums\OnboardingStatus;
if ($subTenant->onboardingStatus === OnboardingStatus::Active) { /* ... */ }
// ou
if ($subTenant->isOnboarded()) { /* ... */ }
```

Pour l'UI, preferer le `RecommendedAction` localise :

```php
$summary = $api->subTenants()->getSuperPDPStatus($id);
if ($summary->recommendedAction) {
    $title = $summary->recommendedAction->title('fr');
    $cta   = $summary->recommendedAction->ctaUrl;
}
```

### Backend requirements

Scell.io API v2.0+ (release 2026-05-08). Les nouveaux endpoints
retournent 404 sur les backends anterieurs.

### Tag Git

Le tag a creer pour declencher Packagist :

```bash
git tag -a v2.0.0 -m "feat: API v2 onboarding (SuperPDP) + breaking change kyc_status -> onboarding_status"
git push origin v2.0.0
```

## 1.17.0 — 2026-05-06

### Added
- **`$client->invoiceTemplates()->uploadLogo($id, $file, $filename = null)`** —
  upload d'un logo pour un template (multipart S3). Accepte un path string
  (auto-fopen) ou une resource. Formats : jpeg, png, webp, svg/svgz. Max 2MB.
  Retourne le template avec le nouveau `logo_url` (URL publique CDN).
- **`HttpClient::postMultipart($path, $multipart)`** — primitive Guzzle
  multipart, reutilise auth/timeout/error handling.

### Backend requirements
Backend Scell.io v0.7.0+ (endpoint `POST /v1/invoice-templates/{id}/logo`).

### Use case
Permet aux integrateurs de configurer le branding (logo + couleurs +
mentions custom) **une fois pour toutes** via SDK, sans avoir besoin de
re-passer ces parametres sur chaque facture. Override par-facture reste
prioritaire sur les defauts du template.

```php
// Upload du logo une fois pour toutes
$tpl = $client->invoiceTemplates()->uploadLogo(
    $templateId,
    '/path/to/logo.png'
);

// Configurer les couleurs / mentions
$client->invoiceTemplates()->update($templateId, [
    'primary_color' => '#1F2937',
    'accent_color' => '#6366F1',
    'footer_text' => 'Mentions legales custom',
]);

// Marquer comme template tenant default
$client->invoiceTemplates()->markDefault($templateId);
```

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
- Legacy fiscal certification terminology replaced with ISCA across all PHPDoc comments and documentation
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
- Renamed legacy fiscal certification references to ISCA (conformite ISCA)

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
