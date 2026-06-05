# Changelog

All notable changes to this project will be documented in this file.

## [2.35.0] - 2026-06-05

### Added (scellement des devis — PAdES + ancrage Bitcoin)
- **DTO `QuoteSealing`** : expose le scellement d'un devis signé. Le PDF du
  devis est signé au format **PAdES**, et son empreinte **SHA-256** est ancrée
  dans la blockchain **Bitcoin** via **OpenTimestamps**. Champs :
  - `isSealed` (`bool`) — devis scellé ou non
  - `padesSignedAt` (`?string`, ISO 8601) — date de signature PAdES
  - `signedPdfSha256` (`?string`) — empreinte SHA-256 (hex) du PDF scellé,
    soit le hash ancré dans Bitcoin
  - `otsStatus` (`?string` : `pending` | `confirmed` | `failed`)
  - `otsSubmittedAt` (`?string`, ISO 8601) — soumission aux calendars OTS
  - `otsBitcoinConfirmedAt` (`?string`, ISO 8601) — confirmation de l'ancrage
  - `bitcoinBlockHeight` (`?int`) — hauteur du bloc Bitcoin d'ancrage
  - `otsProofBase64` (`?string`) — receipt OpenTimestamps (.ots) en base64
  - Helpers `isOtsConfirmed()`, `isOtsPending()`, `isOtsFailed()`.
- **`Quote::$sealing`** : nouvelle propriété optionnelle (`?QuoteSealing`),
  hydratée depuis la clé `sealing` de la réponse API. Présente une fois le
  devis scellé. Nouveau helper `Quote::isSealed()`.

### Changed
- `HttpClient::SDK_VERSION` : `2.35.0`.

## [2.34.0] - 2026-06-04

### Added (facturation récurrente — abonnements)
- **`RecurringInvoiceResource`** (`$client->recurringInvoices()`) : nouvelle
  resource exposée sur `ScellClient` (Bearer) et `ScellApiClient`
  (`sk_*`/`pk_*`), à côté de `buyers()`. Gère le cycle de vie complet d'un
  profil de facturation récurrente :
  - `list(array $filters = [])` → `PaginatedResult<RecurringInvoiceProfile>`
    (filtres `status`, `sub_tenant_id`, `per_page`)
  - `get(string $id)`, `create(array $data)` (201), `update(string $id, array $data)`
    (PUT), `delete(string $id)`
  - `occurrences(string $id, array $filters = [])` →
    `PaginatedResult<RecurringInvoiceOccurrence>` (suivi des échéances)
  - `pause()`, `activate()`, `cancel()` → renvoient le profil mis à jour
  - `runNow(string $id)` → déclenche une émission immédiate hors cadence (202,
    traitement asynchrone — suivre via `occurrences()`)
  - Les `Address` DTO passés en `buyer_address` / `buyer_shipping_address` sont
    automatiquement normalisés en tableaux (même pattern que `BuyerResource`).
- **DTO `RecurringInvoiceProfile`** : gabarit (buyer, lignes, format) + cadence
  (`recurrence{}`), statut, mode d'émission, fin de récurrence, `next_run_at`,
  `occurrences_count`, `totals{}`. Helpers `isActive()`, `isPaused()`,
  `isCompleted()`, `isCancelled()`, `isAutoSend()`, `isSandbox()`. Statut et
  modes exposés en chaînes (convention `Quote`).
- **DTO `RecurringInvoiceOccurrence`** : une échéance → une facture
  (`invoice_id` / `invoice_number`), `status`, `attempts`, `last_error`.
  Helpers `isEmitted()`, `isPending()`, `isFailed()`, `isSkipped()`.
- **Enums** : `RecurrenceIntervalUnit` (day/week/month/year),
  `RecurrenceEndMode` (never/on_date/after_occurrences),
  `RecurringEmissionMode` (draft/auto_send), `RecurringProfileStatus`
  (active/paused/completed/cancelled), `RecurringOccurrenceStatus`
  (pending/emitted/failed/skipped). Chacun avec `label()` + helpers
  (`canPause()`, `isTerminal()`, `color()`, …).

### Changed
- `HttpClient::SDK_VERSION` : `2.34.0`.

## [2.33.0] - 2026-06-04

### Added (autoliquidation TVA intra-UE — biens & services)
- **`VatCategory`** : 4 nouvelles catégories alignées sur le backend —
  `IntracomGoods` (K, livraison intracommunautaire de biens, art. 262 ter I),
  `Export` (G, exportation de biens hors UE, art. 262 I),
  `FranchiseBase` (E, franchise en base auto-entrepreneur, art. 293 B),
  `ExemptTraining` (E, formation professionnelle continue, art. 261-4-4°a).
- **`VatCategory::justification(string $lang = 'fr')`** : nouvelle méthode —
  mention légale exacte à inscrire sur la facture (BT-120), bilingue FR/EN,
  miroir du backend. `ReverseCharge` → « Autoliquidation - Article 283-2 du CGI ».
- **`InvoiceLineBuilder::withSupplyType('goods'|'services')`** : discrimine
  l'exonération intra-UE/export (biens → K/G, services → AE/O).
- **`InvoiceLineBuilder::withOverrideReason(string)`** : assume un taux divergent
  avec trace fiscale (évite le 409 ci-dessous).
- **`VatCorrectionRequiredException`** (409, `VAT_CORRECTION_REQUIRED`) : levée
  quand un taux est incohérent avec le contexte vendeur/acheteur sans
  `vat_override_reason`. Expose `getCorrections()` (taux/catégorie/mention
  suggérés par ligne) et `getHint()`. La facture n'est PAS persistée.

### Changed
- **`InvoiceLineBuilder::build()`** : les champs de pilotage TVA sont désormais
  émis en **top-level** (`vat_category`, `supply_type`, `place_of_supply`,
  `vat_override_reason`) au lieu de `metadata.*` — c'est ce que la résolution
  autoritaire serveur consomme réellement. **C'est ce qui fait enfin émettre le
  code AE/K + la mention légale** (auparavant `metadata.category` était ignoré
  → 0 % sans mention). Les noms de méthodes du builder sont inchangés.
- `VatCategory::exemptionReason()` : `ZeroRated` retourne désormais `null`
  (aligné backend — EN16931 BR-Z n'exige pas de mention).
- `VatCategory::en16931Code()` : ajout des codes `K` (IntracomGoods) et `G` (Export).
- `HttpClient::SDK_VERSION` : `2.33.0` (corrige une valeur obsolète `2.27.1`).

## [2.32.0] - 2026-06-04

### Changed (doc du contrat de création d'avoir)
- `creditNotes()->create()` : docstring réécrite — un avoir **partiel** exige de
  **sélectionner des lignes de la facture source** via `items[].invoice_line_id`
  (prix + taux de TVA exact hérités par ligne ; multi-taux 20 %/5,5 %/exonéré 0 % OK).
  `type` ('partial'|'total') documenté, `quantity` optionnel.
- `creditNotes()->remainingCreditable()` : type de retour corrigé (l'ancien `lines`/
  `total_ht/tax/ttc` était faux) → `items[]` avec invoice_line_id/remaining_quantity/
  tax_rate/remaining_amount_ht + total_remaining + can_be_credited.


## [2.31.0] - 2026-06-04

### Added
- **Simulateur de seuils pré-émission** (`$client->subTenants->simulateThresholds($id, ['amount' => ..., 'category' => ...])`) :
  projette les jauges de seuils SI une facture HT hypothétique était émise dans
  `category` (goods|service|accommodation). Le `level`/`actionable` reflète l'état
  POST-facture → vérifier un franchissement AVANT d'émettre. Lecture seule.
  Retourne `array{data, simulated, disclaimer}`.

## [2.30.0] - 2026-06-04

### Added
- **Suivi de seuils micro-entrepreneur** (`$client->subTenants->getThresholds($id)`) :
  jauges de seuils FR (franchise TVA base/majoree + plafond du regime micro),
  CA HT cumule par categorie, palier d'alerte (`warning_80` … `micro_ceiling_exceeded`)
  et date projetee de franchissement. Seuils = regles datees (loi 2025-1044).
  Information non contractuelle (champ `disclaimer`).
- **Statut fiscal declare** (`$client->subTenants->updateFiscalStatus($id, $data)`) :
  MAJ regime / statut TVA / type d'activite / date de debut / numero de TVA.
  Passer `vat_status = 'liable'` bascule la facturation vers la TVA (les factures
  suivantes portent la TVA et n'affichent plus la mention art. 293 B) ; un numero
  de TVA devient requis. Retourne un DTO `SubTenant`.
- **Telechargement CSV de cloture** (`$client->fiscal->downloadClosing($closingId)`) :
  CSV (format de marche) d'une cloture quotidienne/mensuelle/annuelle, bytes bruts,
  scope tenant strict. `fiscal->closings(['closing_type' => ..., 'sub_tenant_id' => ...])`
  pour filtrer.

## [2.29.1] - 2026-06-04

### Fixed
- Doc: the country reference endpoint (`reference()`) requires authentication
  (Sanctum or `sk_*`/`pk_*` API key) — corrected the docblocks that wrongly
  described it as public. No behaviour change (the SDK always authenticates).

## [2.29.0] - 2026-06-04

### Added
- `ReferenceResource` (`$client->reference()`) exposing the public country
  company reference: `countries()` and `country(string $code)`. Backed by
  `GET /api/v1/reference/countries[/{code}]` (no auth). For each country it
  returns the VAT number (label/example/regex/VIES-checkable), the national
  registration identifier (label/scheme ISO 6523/example/regex/required-for-B2B)
  and the known legal forms — to build country-aware buyer/seller forms.
- DTOs `CountryReference` and `LegalForm`.
- Wired on `ScellClient`, `ScellApiClient` and `ScellPublicClient`.

## [2.28.1] - 2026-06-03

### Fixed
- Remove a redundant `!== null` guard in `InitialsBlock::fromArray()` flagged by
  PHPStan (`isset()` already excludes null). Behaviour unchanged; fixes the
  release CI which was failing on static analysis since v2.27.1. No API change.

## [2.28.0] - 2026-06-03

Aligns the fiscal kill-switch wrappers with the **step-up** hardening shipped on
the API (June 2026). The kill-switch is the emergency halt of the fiscal system;
activating or deactivating it now requires the `fiscal:admin` scope (fail-closed),
a `reason` of **>= 20 characters**, and — in production — an out-of-band email
confirmation.

### Changed

- **`fiscal()->killSwitchDeactivate()` now takes a required `array $data`**
  argument (`['reason' => string, 'confirmation_token' => ?string]`), mirroring
  `killSwitchActivate()`. The previous no-argument form is removed: against the
  current API it already failed (the server requires a `reason`).
- `killSwitchActivate()` / `killSwitchDeactivate()` documented to accept an
  optional `confirmation_token` (out-of-band token received by email on the
  first production call).

### Migration

```php
// Before (2.27.x)
$scell->fiscal()->killSwitchDeactivate();

// After (2.28.0)
$scell->fiscal()->killSwitchDeactivate([
    'reason' => 'Incident resolu, reprise de la facturation normale',
    // 'confirmation_token' => '...', // production only, received by email on the 1st call
]);
```

## [2.27.1] - 2026-05-28

### Fixed
- `InvoiceStatus` aligné sur les **16 statuts canoniques** du backend (check
  constraint PostgreSQL `invoices_status_check`). Retrait du statut fantôme
  `processing` (déprécié, jamais émis par l'API ; le backend expose `validating`).

## [2.27.0] - 2026-05-28

### Added

- **`SignatureBuilder::addSignaturePosition()` — parametre `signerIndex`**
  (optionnel, `int` 0-base) — affecte explicitement une position de signature
  a un signataire precis (`0` = premier signataire ajoute, `1` = deuxieme,
  etc.). Serialise en `signer_index` dans le payload uniquement s'il est
  defini.

- **Positions multiples par signataire** — EU-SES autorise desormais
  **plusieurs** positions de signature pour un meme signataire. Appeler
  `addSignaturePosition()` autant de fois que necessaire avec le meme
  `signerIndex` (ex: le signataire 0 signe pages 1 et 3). Combinable avec
  `documentIndex` (multi-document).

### Notes

- 100% retrocompatible : sans `signerIndex`, le mapping positionnel historique
  (1 position par signataire dans l'ordre) reste applique. Le comportement
  existant est inchange.

## [2.26.0] - 2026-05-28

### Added

- **`Resources\SupplierResource`** — registre des fournisseurs (miroir cote
  emetteur de `BuyerResource`). Scope strict par (tenant, sub_tenant).
  Methodes : `list()` (filtres `q`, `is_individual`, `per_page`, `page`),
  `get()`, `create()`, `update()` (PATCH partiel) et `delete()`.
  Consomme les endpoints backend `GET|POST /api/v1/suppliers`,
  `GET|PATCH|PUT|DELETE /api/v1/suppliers/{id}`.

- **`DTOs\Supplier`** — DTO immutable miroir de `Buyer` cote fournisseur.
  Champs : `id`, `tenantId`, `subTenantId`, `name`, `isIndividual`,
  `billingAddress` (`Address`), `country`, `siret`, `vatNumber`, `legalId`,
  `legalIdScheme`, `email`, `phone`, `metadata`, `notes`, `createdAt`,
  `updatedAt`. **Pas** de `shippingAddress`, `billingEmail` ni de
  resolution TVA (concepts acheteur uniquement).

- **`ScellClient::suppliers()` + `ScellApiClient::suppliers()`** — exposent
  la nouvelle resource, meme pattern lazy que `buyers()`.

## [2.25.0] - 2026-05-28

### Added

- **`Enums\PaymentMeansCode`** — nouvelle enumeration des codes
  UN/ECE 4461 (BT-81 EN16931 / Factur-X) supportes cote backend Scell.io.
  11 cases couvrant les moyens de paiement les plus courants en B2B France :

  | Case | Value | FR / EN |
  |------|-------|---------|
  | `INSTRUMENT_NOT_DEFINED` | `1` | Non specifie / Unspecified |
  | `IN_CASH` | `10` | Especes / Cash |
  | `CHEQUE` | `20` | Cheque / Cheque |
  | `CREDIT_TRANSFER` | `30` | Virement / Credit transfer |
  | `PAYMENT_TO_BANK_ACCOUNT` | `42` | Versement bancaire / Bank account transfer |
  | `BANK_CARD` | `48` | Carte bancaire / Bank card |
  | `DIRECT_DEBIT` | `49` | Prelevement / Direct debit |
  | `STANDING_AGREEMENT` | `57` | Accord permanent / Standing agreement |
  | `SEPA_CREDIT_TRANSFER` | `58` | Virement SEPA / SEPA credit transfer |
  | `SEPA_DIRECT_DEBIT` | `59` | Prelevement SEPA / SEPA direct debit |
  | `CLEARING_BETWEEN_PARTNERS` | `97` | Compensation / Clearing between partners |

  Helpers :
  - `->label()` — libelle francais court (UI dashboard)
  - `->labelEn()` — English label (i18n / SDK doc)
  - `::commonB2bFrance(): array` — ordre prioritaire pour selecteurs UI
    (SEPA virement -> virement -> cheque -> CB -> SEPA prelevement -> especes)

- **`DTOs\Invoice::$paymentMeansCode` (`?PaymentMeansCode`) +
  `$paymentMeansText` (`?string`)** — nouveaux champs persistes apres
  `markPaid()`, exposes par l'API sur les listes et detail invoice.
  `tryFrom()` defensive : un code inconnu introduit cote backend ne
  casse pas le SDK (fallback `null`).

- **`DTOs\CreditNote::$paymentMeansCode` (`?PaymentMeansCode`) +
  `$paymentMeansText` (`?string`)** — meme contrat pour les avoirs.

### Changed (BREAKING)

- **`InvoiceResource::markPaid(string $id, PaymentMeansCode|string $paymentMeansCode, array $optional = [])`**
  La signature de `POST /v1/invoices/{id}/mark-paid` change :
  `payment_means_code` est maintenant un **argument positionnel REQUIS**
  (typesafe `PaymentMeansCode|string`). L'ancien tableau `$data` devient
  `$optional` (clefs : `payment_means_text`, `payment_reference`,
  `paid_at`, `note`). Toute migration depuis 2.24.x doit ajouter le code
  comme 2e parametre :

  ```php
  // AVANT (2.24.x) - rejete par le backend 2026-05-28
  $client->invoices()->markPaid('invoice-uuid', [
      'payment_reference' => 'VIR-2026-001',
  ]);

  // APRES (2.25.0+)
  $client->invoices()->markPaid(
      'invoice-uuid',
      PaymentMeansCode::SEPA_CREDIT_TRANSFER,
      [
          'payment_reference' => 'VIR-2026-001',
          'payment_means_text' => 'Compte BNP ...4567',
      ],
  );
  ```

- **`TenantIncomingInvoiceResource::markPaid(string $invoiceId, PaymentMeansCode|string $paymentMeansCode, array $optional = [])`**
  Meme refonte de signature pour `POST /v1/tenant/invoices/incoming/{id}/mark-paid`.
  L'ancienne signature `markPaid(string $invoiceId, ?string $reference = null, array $data = [])`
  est remplacee. Le parametre `$reference` se passe maintenant via
  `$optional['payment_reference']` :

  ```php
  // AVANT (2.24.x)
  $tenant->incomingInvoices()->markPaid('invoice-uuid', 'VIR-2026-001', [
      'paid_at' => '2026-01-28',
      'note' => 'Paiement SEPA',
  ]);

  // APRES (2.25.0+)
  $tenant->incomingInvoices()->markPaid(
      'invoice-uuid',
      PaymentMeansCode::SEPA_CREDIT_TRANSFER,
      [
          'payment_reference' => 'VIR-2026-001',
          'paid_at' => '2026-01-28',
          'note' => 'Paiement SEPA',
      ],
  );
  ```

  Forme minimale (string brute acceptee aussi) : `markPaid($id, '58')`.

### Notes

- L'endpoint Sanctum SPA `POST /v1/invoices/incoming/{invoice}/mark-paid`
  (dashboard) n'est PAS exposable via clef API `sk_*` / `pk_*` et n'est
  donc pas couvert par le SDK PHP. Pour les operations equivalentes via
  SDK, utiliser `TenantIncomingInvoiceResource::markPaid()` (endpoint
  `tenant/invoices/incoming/{id}/mark-paid` accessible avec clef API).

- Toute requete envoyee sans `payment_means_code` est rejetee 422 par le
  backend (`The given data was invalid. payment_means_code: Le mode
  d'encaissement est obligatoire`). La signature du SDK force le passage
  du parametre a la compilation pour eliminer cette classe d'erreurs.

- Le typage `PaymentMeansCode|string` permet aux integrations existantes
  de passer une valeur string brute (`'58'`, `'30'`, etc.) sans forcer
  la migration immediate vers l'enum, tout en gardant la verification
  d'enum cote backend.

## [2.24.0] - 2026-05-28

### Added

- **`Resources\CreditPacksResource`** — nouvelle resource publique pour
  consulter les packs de credits prepayes Scell.io (one-shot avec bonus,
  comme `starter` / `pro` / `business`). Endpoint backend
  `GET /api/v1/packs/public` (no auth requise, accepte aussi `pk_*` et `sk_*`).
  Wire-uppee sur les 3 clients existants : `ScellApiClient`,
  `ScellPublicClient`, `ScellClient`.

  ```php
  // Liste publique (publishable key suffit)
  $client = ScellApiClient::withPublishableKey('pk_live_xxx');
  $packs = $client->creditPacks()->list();
  foreach ($packs as $pack) {
      echo "{$pack->name} : {$pack->amountEuros} EUR -> {$pack->creditsEuros} EUR";
      if ($pack->hasBonus()) {
          echo " (+{$pack->bonusPercent}%)";
      }
  }

  // Recuperer un pack par slug
  $pro = $client->creditPacks()->get('pro');
  ```

- **`DTOs\CreditPack`** — nouveau DTO mappant le payload `packs/public` :
  `id`, `slug`, `name`, `amountEur` (cents), `amountEuros` (float),
  `creditsEur`, `creditsEuros`, `bonusEur`, `bonusPercent`, `currency`,
  `position`, `isRecommended`, `description`, `bonusEuros`. Helpers
  `hasBonus()` et `bonusInEuros()` pour les calculs derives.

- **`BillingResource::listPacks()`** et **`BillingResource::checkoutPack($slug)`** —
  acces tenant authentifie aux packs et a leur achat :
  - `listPacks()` — `GET /api/v1/tenant/billing/packs` (vue tenant, equivalent
    fonctionnel de `creditPacks()->list()` mais avec contexte tenant)
  - `checkoutPack($slug)` — `POST /api/v1/tenant/billing/packs/{packSlug}/checkout`.
    En sandbox : credit direct + reponse `mode = 'sandbox_bypass'`. En production :
    cree un Stripe PaymentIntent + reponse `mode = 'live'|'live_existing'` avec
    `client_secret` a confirmer cote front. Idempotent en mode live.

  ```php
  $billing = $client->billing();

  // Liste les packs
  $packs = $billing->listPacks();

  // Initier l'achat (le checkout devient automatiquement Factur-X + crediter
  // la balance via le webhook payment_intent.succeeded en production)
  $checkout = $billing->checkoutPack('pro');
  if ($checkout['mode'] === 'sandbox_bypass') {
      echo "Sandbox : {$checkout['credit_eur']} cents credites directement";
  } else {
      // mode = 'live' ou 'live_existing'
      echo "Confirmer le PaymentIntent {$checkout['client_secret']} cote Stripe.js";
  }
  ```

- **`FiscalResource::exportFecAll($startDate, $endDate, $format, $download)`** —
  endpoint cross sub-tenant FEC export consolide. Couvre P0.1
  (`GET /v1/tenant/fiscal/fec/all`, disponible depuis 2026-05-27). Le format
  par defaut est `pipe` (norme FEC francaise CGI BOI-CF-IOR-60-40-10),
  alternatif `tab`. Avec `download = true`, retourne le binaire ZIP brut au
  lieu des metadata JSON.

  ```php
  // Metadata seulement (chemin serveur du ZIP genere)
  $meta = $client->fiscal()->exportFecAll('2026-01-01', '2026-12-31');
  echo $meta['data']['file_path'];

  // Telechargement direct du ZIP
  $zipBinary = $client->fiscal()->exportFecAll(
      new \DateTimeImmutable('2026-01-01'),
      new \DateTimeImmutable('2026-12-31'),
      format: 'pipe',
      download: true,
  );
  file_put_contents('fec-all-2026.zip', $zipBinary);
  ```

- **`TenantIncomingInvoiceResource::dispute()`** — contestation de facture
  entrante (litige ouvert). Distinct de `reject()` (refus definitif).
  Endpoint backend cible `POST /tenant/invoices/incoming/{id}/dispute`.
  Accepte un type de litige optionnel (`DisputeType` enum) et un montant
  attendu pour les `amount_dispute`.

  ```php
  // Dispute simple
  $invoice = $client->incomingInvoices()->dispute(
      'invoice-uuid',
      'Montant facture incorrect',
  );

  // Avec type et montant attendu
  $invoice = $client->incomingInvoices()->dispute(
      'invoice-uuid',
      'Facture 1500 EUR mais devis a 1200 EUR',
      DisputeType::AmountDispute,
      1200.00,
  );
  ```

- **`DTOs\PaymentSchedulePreset`** — DTO typed pour les 5 presets natifs
  d'echeancier de paiement (`full_upfront`, `deposit_50_balance_50`,
  `deposit_30_balance_70`, `thirds_30_30_40`, `quarterly`). Helpers :
  `lineCount()`, `isValid()` (sum = 100), `toScheduleLines()` (transforme en
  payload pour `POST /quotes/{id}/payment-schedule`).

- **`QuotePaymentScheduleResource::presetsDtos()`** — alternative typed a
  `presets()`. Retourne `PaymentSchedulePreset[]` au lieu du tableau brut.
  `presets()` conserve son comportement existant (tableau brut) pour la
  retrocompat.

  ```php
  $presets = $client->quotes()->paymentSchedule()->presetsDtos();
  foreach ($presets as $preset) {
      echo "{$preset->key}: {$preset->label}\n";
  }

  // Appliquer le preset 'thirds_30_30_40' directement sur un devis
  $schedule = $client->quotes()->paymentSchedule();
  $thirds = $schedule->presetsDtos()[3];
  $schedule->set($quoteId, $thirds->toScheduleLines());
  ```

### Deprecated

- **`Resources\BalanceResource`** (classe entiere) — tous les endpoints
  `/api/v1/balance/*` ont ete supprimes cote backend Scell.io le 2026-05-10.
  Tout appel via cette resource provoque maintenant un **404 silencieux suivi
  d'une `ScellException`**. La classe est conservee pour la retrocompat mais
  sera supprimee en v3.0.0. Migration obligatoire vers `BillingResource` :

  | Ancien BalanceResource                            | Nouveau BillingResource                                  |
  |---------------------------------------------------|----------------------------------------------------------|
  | `$client->balance()->get()`                       | `$client->billing()->usage()`                            |
  | `$client->balance()->reload($amount)`             | `$client->billing()->topUp(['amount_eur' => $amount])`   |
  | `$client->balance()->updateSettings([...])`       | _supprime_ — config via dashboard admin                  |
  | `$client->balance()->transactions([...])`         | `$client->billing()->transactions([...])`                |
  | `$client->balance()->debits()`                    | `$client->billing()->transactions(['type' => 'debit'])`  |
  | `$client->balance()->credits()`                   | `$client->billing()->transactions(['type' => 'credit'])` |
  | `$client->balance()->enableAutoReload()`          | _supprime_ — config via dashboard admin                  |
  | `$client->balance()->disableAutoReload()`         | _supprime_ — config via dashboard admin                  |

- **`ScellClient::balance()`** — accessor de `BalanceResource`. Idem
  deprecation, redirige vers `ScellClient::billing()` (NB : disponible
  uniquement sur `ScellApiClient` actuellement — l'expose sur `ScellClient`
  Bearer Sanctum est sur la roadmap v2.25.0).

- **`ScellTenantClient::balance()`** — endpoint legacy
  `GET /api/v1/tenant/balance` egalement supprime cote backend.
  Migration : utiliser `ScellApiClient::withApiKey($sk)->billing()->usage()` /
  `transactions()`.

### Fixed

- **`HttpClient::SDK_VERSION`** mis a jour de `'2.10.0'` a `'2.24.0'` (la
  constante etait restee figee depuis 14 versions, dont 4 majeures de
  fonctionnalites). Le header `User-Agent` envoye a l'API porte maintenant
  la bonne version, ce qui ameliore l'observabilite cote backend (audit
  logs, rate-limit per-version).

### Notes

- **Refund support (P1.16) — non applicable cote SDK**. Le backend
  Scell.io n'expose **pas** d'endpoint `POST /invoices/{id}/refund`. La
  notion de "refund" est cote backend portee par deux mecaniques distinctes :
  1. **Factures clients (Invoice)** : refund = creation d'un avoir (CreditNote)
     via `creditNotes()->create()`. Le statut `refund_status` /
     `total_refunded` est ensuite propage automatiquement par
     `CreditNoteObserver` sur la facture mere. Le SDK PHP supporte deja ce
     flow depuis 2.20.0 (champs DTO `Invoice::$refundStatus`,
     `Invoice::$totalRefunded`, statuts `Refunded` / `PartiallyRefunded` sur
     l'enum `InvoiceStatus`).
  2. **Factures Scell -> tenant (TenantInvoice payees par Stripe)** : refund
     trigger automatique via le webhook `charge.refunded` qui cree une
     CreditNote interne et met a jour `refund_status` sur `TenantInvoice`.
     Pas d'API publique pour declencher un refund manuellement — c'est
     volontaire (le refund doit passer par Stripe Dashboard ou le support).

  Aucune methode SDK `refund()` ne sera ajoutee tant que le backend n'expose
  pas un endpoint dedie.

- **`IncomingInvoices::dispute()` cote `TenantIncomingInvoiceResource`** —
  la methode appelle `POST /api/v1/tenant/invoices/incoming/{id}/dispute`.
  Ce path n'est **pas encore expose** sous `sk_*` cote backend (seul le
  pendant Sanctum SPA `POST /api/v1/invoices/incoming/{invoice}/dispute`
  existe — couvert par `ScellClient::invoices()->dispute()`). Pour
  consommateurs en `sk_*`, attendre le rollout backend (prevu Q2 2026)
  ou utiliser le client `ScellClient` (Bearer Sanctum) qui supporte deja
  `dispute()` via `InvoiceResource::dispute()`.

- **191 tests** (vs 156 avant), **920 assertions**. 35 nouveaux tests
  unitaires + d'integration HTTP (mocks Guzzle) couvrant les 6 surfaces
  modifiees.

## [2.21.0] - 2026-05-27

### Added

- **Cartographie exhaustive des enums backend Scell.io.** Ajout de 16 enums
  manquants pour aligner le SDK PHP sur l'integralite des `BackedEnum` PHP
  + check constraints PostgreSQL du backend. Tous suivent le meme pattern
  que les enums existants (PHP 8.1+, `declare(strict_types=1)`, namespace
  `Scell\Sdk\Enums`, helpers metier specifiques).

  **Enums Invoice/Quote (alignes sur les BackedEnum backend) :**
  - `InvoiceTemplateKind` — `invoice` / `quote` / `both`. Helper `matches()`.
  - `InvoiceType` — `standard` / `deposit` / `balance`. Helpers
    `facturXTypeCode()`, `isDepositOrBalance()`, `omitLatePaymentMentions()`.
  - `PaymentScheduleLineAmountType` — `percent` / `amount`. Helper
    `computeTtc()` qui arrondit half-up a 2 decimales.
  - `PaymentScheduleLineStatus` — `pending` / `invoiced` / `cancelled`.
    Helpers `canInvoice()`, `canCancel()`, `isTerminal()`.
  - `QuoteAuditAction` — 21 cases couvrant l'audit log SHA-256 append-only
    des devis (created, sent, viewed, signed, accepted, refused, expired,
    converted, deposit_generated_from_schedule, etc.). Helpers
    `isMutation()`, `isStateTransition()`, `isModification()`.
  - `QuoteStatus` — `draft` / `sent` / `viewed` / `accepted` / `refused` /
    `expired` / `converted` / `cancelled`. Helpers `canEdit()`,
    `canConvert()`, `canCancel()`, `canDelete()`, `isTerminal()`.

  **Enums check constraint DB :**
  - `CreditNoteStatus` — `draft` / `sent`. Helper `canEdit()`.
  - `CreditNoteType` — `partial` / `total`. Helper `isTotal()`.
  - `SignatureArchiveStatus` — `pending` / `archived` / `glacier` / `error`
    (lifecycle S3 + Object Lock COMPLIANCE 11 ans). Helper `isArchived()`.
  - `InvoiceArchiveStatus` — `pending` / `archived` / `glacier` / `error`
    (lifecycle S3 + Object Lock COMPLIANCE 10 ans fiscaux). Helper
    `isArchived()`.
  - `TenantKybStatus` — `pending` / `documents_submitted` / `under_review` /
    `verified` / `rejected`. Helpers `canIssueInvoices()`, `isFinal()`.
  - `CompanyStatus` — `pending_kyc` / `active` / `suspended`. Helper
    `canIssueInvoices()`.
  - `ApiKeyStatus` — `active` / `revoked`. Helper `isUsable()`.
  - `TenantInvoiceStatus` — `draft` / `sent` / `paid` / `overdue` /
    `cancelled` (factures Scell.io vers les tenants, distinct de
    `InvoiceStatus`). Helpers `isPayable()`, `isFinal()`.
  - `TenantTransactionType` — `debit` / `credit`. Helper `sign()` (+1 / -1).
  - `OnboardingSessionStatus` — `initiated` / `siret_verified` /
    `vat_verified` / `documents_pending` / `documents_submitted` /
    `under_review` / `completed` / `failed` / `expired`. Distinct de
    `OnboardingStatus` (qui suit le SubTenant cote backend). Helpers
    `isFinal()`, `isActive()`.

  ```php
  use Scell\Sdk\Enums\InvoiceType;
  use Scell\Sdk\Enums\QuoteStatus;
  use Scell\Sdk\Enums\TenantKybStatus;

  // Type-safe consumption
  if ($invoice->type === InvoiceType::Deposit) {
      // Acompte : TVA exigible mais pas de mentions L441-10
  }

  // Lifecycle helpers
  if ($quote->status->canConvert()) {
      $client->quotes->convertToBalance($quote->id);
  }

  // Backend constraint enforcement
  if (! $tenant->kyb_status->canIssueInvoices()) {
      throw new TenantNotReadyException();
  }
  ```

### Notes

- Aucun breaking change. Les DTOs continuent d'exposer les valeurs en
  `string` quand le SDK ne typait pas encore en enum — les helpers
  `Enum::from()` / `::tryFrom()` permettent au consommateur de basculer
  progressivement vers un typage strict.
- Couvre tous les check constraints PostgreSQL du backend
  (`*_status_check`, `*_type_check`) ainsi que les `BackedEnum` PHP des
  domaines Invoice, Quote, Tenant, Company, ApiKey, Onboarding,
  Archiving.

## [2.20.0] - 2026-05-27

### Added

- **Statuts Invoice — alignement complet sur `invoices_status_check` backend.**
  Le SDK expose desormais toutes les valeurs du check constraint PostgreSQL
  `invoices_status_check` de l'API Scell.io. Nouveaux cases sur
  `Scell\Sdk\Enums\InvoiceStatus` :
  - `Refunded` (`refunded`) — facture totalement avoiree (avoirs >= total TTC)
  - `PartiallyRefunded` (`partially_refunded`) — au moins un avoir, somme < TTC
  - `Validating` (`validating`) — validation en cours (file de jobs)
  - `Transmitting` (`transmitting`) — transmission en cours
  - `Disputed` (`disputed`) — contestee (litige ouvert)
  - `Received` (`received`) — recue cote acheteur (entrante)
  - `Completed` (`completed`) — cycle de vie cloture

  Le backend pose `refunded` / `partially_refunded` automatiquement via
  `CreditNoteObserver` quand un avoir atteint le statut
  `validated/sent/transmitted/accepted` et que la somme cumulee atteint (ou
  reste inferieure a) le total TTC de la facture mere.

  ```php
  $invoice = $client->invoices->get('019d...');

  if ($invoice->status === InvoiceStatus::Refunded) {
      // Facture integralement remboursee
  }

  // Helper enum
  $invoice->status->isRefunded(); // true si Refunded ou PartiallyRefunded
  $invoice->status->isFinal();    // true pour Refunded (mais pas PartiallyRefunded)
  ```

- **Enum `RefundStatus`** — projection simplifiee du statut de remboursement
  retournee par l'API dans le champ `refund_status` (`none|partial|full`).
  Plus simple a consommer dans une UI qu'un check sur `InvoiceStatus`.

  ```php
  use Scell\Sdk\Enums\RefundStatus;

  echo $invoice->refundStatus->label(); // "Partiellement remboursee"
  $invoice->refundStatus->hasRefund();  // true si Partial ou Full
  ```

- **`Invoice::$refundStatus` et `Invoice::$totalRefunded`** — nouveaux champs
  en lecture sur le DTO, mappes depuis les champs API `refund_status` et
  `total_refunded` exposes par `GET /api/v1/invoices` et
  `GET /api/v1/invoices/{id}`. Defaut `RefundStatus::None` / `0.0` quand
  l'API ne retourne pas les champs (retrocompat).

- **`Invoice::isRefunded()`, `isPartiallyRefunded()`, `isFullyRefunded()`** —
  helpers DTO qui combinent les deux sources de verite (`refundStatus` projete
  par le backend + `status` Invoice). Preferer ces helpers a `hasCreditNotes()`
  (qui compte les objets credit_notes, drafts inclus) et `isFullyCredited()`
  (qui compare un float `creditedAmount` au TTC — sensible aux arrondis).

  ```php
  $invoice->isRefunded();          // true si partial OU full
  $invoice->isPartiallyRefunded(); // true uniquement si partial
  $invoice->isFullyRefunded();     // true uniquement si full
  ```

### Notes

- Aucun breaking change. Les anciens consommateurs continuent de fonctionner :
  le mapping `fromArray()` est defensif (`RefundStatus::tryFrom` + fallback
  `None`), et les nouveaux champs ont des defauts safe.
- Les statuts ajoutes sur l'enum n'invalident aucun code existant (PHP
  permet les match exhaustifs sans `default` quand le compilateur peut
  inferer la couverture — verifier vos `match ($invoice->status)` si vous
  en utilisez ailleurs que via le SDK).
- `InvoiceStatus::Processing` reste disponible (legacy backend) mais est
  marquee `@deprecated` — preferer `Validating` pour les nouveaux flux.

## [2.19.0] - 2026-05-27

### Added

- **`InvoiceBuilder::parentQuoteId(string $id)`** sur les factures standard —
  Le builder fluent acceptait deja la methode mais le payload final n'incluait
  pas le champ `parent_quote_id` sur les factures standard. Le backend Scell.io
  expose desormais ce champ sur `POST /api/v1/invoices` pour creer une facture
  standard reliee a un devis existant (au-dela des endpoints dedies acompte/solde
  `POST /quotes/{id}/convert-to-deposit` et `convert-to-balance` qui restent la
  voie nominale pour ces deux types).

  ```php
  $invoice = $client->invoices->builder()
      ->outgoing()
      ->facturX()
      ->seller($sellerSiret, 'Acme', $sellerAddress)
      ->buyer($buyerSiret, 'Client', $buyerAddress)
      ->addLine('Prestation conseil', 1.0, 5000.00, 20.0)
      ->parentQuoteId('019d1234-5678-7000-8000-abcdef012345')
      ->create();

  // $invoice->parentQuoteId === '019d1234-5678-7000-8000-abcdef012345'
  ```

  Restrictions backend (le SDK ne les reproduit pas — il delegue les erreurs) :
  - `parent_quote_id` n'est accepte que si `invoice_type === 'standard'` (ou absent).
    Pour `deposit`/`balance`, passer par les endpoints dedies.
  - Anti-IDOR : le backend retourne **404** si le devis n'appartient pas au tenant
    courant, **422** si `invoice_type` est invalide.

### Fixed

- **`InvoiceResource::normalizeCreatePayload()`** propage desormais le champ
  `parent_quote_id` vers l'API. Avant 2.19.0, le builder set
  `parentQuoteId()` etait silencieusement drop a la serialisation finale (le
  champ n'apparaissait pas dans le body HTTP POST).

## [2.18.0] - 2026-05-26

### Added

- **`BuyerResource::vatContext()`** — Resout le contexte TVA cross-border d'une ligne
  de facture. Accepte un `buyer_id` enregistre ou un buyer inline (pays, statut B2B/B2C,
  numero TVA). Retourne un `VatResolution` avec le taux applicable, la categorie, le
  code EN16931 pour Factur-X/UBL et la justification textuelle.

  ```php
  // Mode buyer_id
  $r = $client->buyers()->vatContext('019cb416-...', ['category' => 'STANDARD']);
  echo $r->rate;                // 20.0
  echo $r->category->value;     // 'STANDARD'

  // Mode buyer inline (reverse charge EU B2B)
  $r = $client->buyers()->vatContext(
      ['country' => 'DE', 'vat_number' => 'DE123456789'],
      ['category' => 'STANDARD'],
  );
  echo $r->category->value;     // 'REVERSE_CHARGE'
  echo $r->en16931Code;         // 'AE'
  ```

- **`VatResolution` DTO** (`Scell\Sdk\DTOs\VatResolution`) — Resultat de la resolution TVA.
  Proprietes : `rate`, `category` (VatCategory), `en16931Code`, `exemptionReason`,
  `justification`, `isAutoResolved`, `rule`. Methodes : `fromArray()`, `toArray()`.

- **`LineVatContext` DTO** (`Scell\Sdk\DTOs\LineVatContext`) — Contexte d'une ligne pour
  la resolution. Proprietes : `category` (VatCategory|null), `placeOfSupply`, `serviceNature`.
  Utile pour les overrides art. 259 A CGI (lieu de prestation force).

- **`Vat\BuyerContext` DTO** (`Scell\Sdk\DTOs\Vat\BuyerContext`) — Caracteristiques fiscales
  d'un acheteur inline (distinct du DTO `Buyer` complet). Proprietes : `country`, `isIndividual`,
  `vatNumber`, `vatNumberValid`. Ne contient que les champs necessaires a la resolution TVA.

- **`VatCategory` enum** (`Scell\Sdk\Enums\VatCategory`) — 8 cases correspondant aux regimes
  TVA du moteur de regles Scell.io : `Standard` (20 %), `Intermediate` (10 %), `Reduced` (5,5 %),
  `SuperReduced` (2,1 %), `ZeroRated`, `Exempt`, `ReverseCharge`, `OutOfScope`. Helpers :
  `defaultRate()`, `en16931Code()`, `exemptionReason()`, `label()`.

- **`InvoiceLineBuilder`** (`Scell\Sdk\Builders\InvoiceLineBuilder`) — Builder fluent pour
  construire le payload d'une ligne de facture avec gestion de la TVA.
  Methodes : `withCategory(VatCategory)` (derive tax_rate + metadata.category + metadata.exemption_reason),
  `withPlaceOfSupply(string)`, `withServiceNature(string)`, `withDescription()`, `withQuantity()`,
  `withUnitPrice()`, `withTaxRate()`, `withMetadata()`, `build()`.

  ```php
  $line = (new InvoiceLineBuilder())
      ->withDescription('Logiciel SaaS EU')
      ->withQuantity(1)
      ->withUnitPrice(500.00)
      ->withCategory(VatCategory::ReverseCharge)
      ->withPlaceOfSupply('FR')  // art. 259 A CGI
      ->build();
  // $line['tax_rate'] = 0.0
  // $line['metadata']['category'] = 'REVERSE_CHARGE'
  // $line['metadata']['exemption_reason'] = 'reverse_charge'
  // $line['metadata']['place_of_supply'] = 'FR'
  ```

## [2.17.0] - 2026-05-26

### Security
- Documentation alignée avec les hardenings serveur Scell.io (audit 2026-05-26) :
  - Le secret webhook n'est désormais exposé en clair QU'UNE FOIS, à la création (`webhooks.create()`) ou à la régénération (`webhooks.regenerateSecret()`). Les `webhooks.get()` ultérieurs retournent un fingerprint masqué + `secret_last4`. Stockez le secret immédiatement dans un secret manager.
  - La signature webhook utilise le format Stripe-like `X-Scell-Signature: t={timestamp},v1=HMAC(timestamp.payload)` avec fenêtre anti-replay de 5 minutes. La classe `WebhookVerifier` (déjà présente depuis v2.x) gère ce format nativement.
  - Les URLs webhook configurées via `webhooks.create()` doivent obligatoirement être HTTPS et pointer vers une IP publique (validation SSRF côté serveur).
- Champ `WebhookDTO::$secret_last4` ajouté (toujours présent, sert au debug et au fingerprint).

## [2.16.0] - 2026-05-26

### Added

- **Multi-document signatures — `attachments[]`** : la creation de signature
  (`POST /api/v1/signatures`) accepte desormais jusqu'a **10 pieces jointes
  PDF** (20 Mo cumules avec le document principal). Le backend Scell.io merge
  automatiquement principal + PJs en un PDF unique (page de garde +
  numerotation continue) avant submission au prestataire de signature
  partenaire.
  - Nouvelle methode fluent `SignatureBuilder::addAttachment(string $document, string $documentName): self`
    (`$document` doit etre deja encode en base64).
  - Helper de convenance `SignatureBuilder::addAttachmentFromFile(string $path, ?string $name = null): self`
    qui lit le fichier + encode base64 automatiquement.
  - Setter bulk `SignatureBuilder::attachments(array $attachments): self`
    (remplace toute liste precedente, max 10).
  - Champ `attachments` accepte aussi en payload brut dans `SignatureResource::create([...])`.
- **`document_index`** : nouveau champ optionnel sur les positions permettant
  de cibler un document precis dans un bundle multi-PDF. `0` = document
  principal (`document`/`document_name`), `1..N` = attachments dans l'ordre.
  Defaut `null` (= document principal cote backend). Plage validee 0..10.
  - `BlockPosition::$documentIndex` (utilise par `Mention` + `DateBlock`).
  - `InitialsPosition::$documentIndex` (paraphe multi-pages, per-position).
  - `SignatureBuilder::addSignaturePosition(..., ?int $documentIndex = null)`.

```php
use Scell\Sdk\DTOs\BlockPosition;
use Scell\Sdk\DTOs\InitialsBlock;
use Scell\Sdk\DTOs\InitialsPosition;
use Scell\Sdk\DTOs\Mention;

$signature = $client->signatures()->builder()
    ->title('Contrat + CGV + annexe')
    ->document(file_get_contents('contrat.pdf'), 'contrat.pdf')
    ->addAttachmentFromFile('cgv.pdf')                // index 1
    ->addAttachmentFromFile('annexe.pdf')             // index 2
    ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
    ->addSignaturePosition(page: 5, x: 70, y: 80, documentIndex: 0)  // contrat
    ->addSignaturePosition(page: 1, x: 70, y: 80, documentIndex: 2)  // annexe
    ->addMention(new Mention(
        label: 'Lu et approuve',
        signerIndex: 0,
        position: new BlockPosition(x: 10, y: 80, page: 1, documentIndex: 1),
    ))
    ->initialsBlock(InitialsBlock::withPositions([
        new InitialsPosition(page: 1, x: 90, y: 90, documentIndex: 0),
        new InitialsPosition(page: 1, x: 90, y: 90, documentIndex: 1),
    ]))
    ->create();
```

### Compatibility

- **100% retrocompatible** : `attachments` et `document_index` sont strictement
  optionnels. Si absents, le payload reste identique a v2.15.x (mono-document).
- Validations defensives : exception levee si > 10 attachments ou si
  `documentIndex` hors plage `0..10`.

## [2.15.1] - 2026-05-25

### Changed

- Documentation: rewording générique des mentions du fournisseur de signature partenaire (aucun changement de surface publique).

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
- `SignatureBuilder::uiConfig(array $config)` — signature changee : accepte un tableau associatif (anciennement 3 parametres positionnels `logoUrl`/`primaryColor`/`companyName`). Accepte les 21 champs UI alignés sur la spec EU-SES certifiée (`sidebar_logo`, `sidebar_background_color`, `sidebar_text_color`, `header_*`, `footer_*`, `button_*`, `sign_button_*`, `hide_*`, `iframe_ancestors`).

### Removed (BREAKING)
- `SignatureBuilder::uiConfig($logoUrl, $primaryColor, $companyName)` (signature 3-params) supprimee. Utiliser `uiConfig(array $config)` avec les nouveaux champs de la spec EU-SES (`sidebar_logo`, `sidebar_background_color`, etc.). Le champ `company_name` n'a pas d'équivalent (non supporté par la spec EU-SES).

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
