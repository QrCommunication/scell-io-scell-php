<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\DTOs\Address;
use Scell\Sdk\DTOs\Invoice;
use Scell\Sdk\DTOs\InvoiceLine;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\Enums\Direction;
use Scell\Sdk\Enums\DisputeType;
use Scell\Sdk\Enums\InvoiceStatus;
use Scell\Sdk\Enums\OutputFormat;
use Scell\Sdk\Enums\PaymentMeansCode;
use Scell\Sdk\Enums\RejectionCode;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les factures electroniques.
 *
 * Permet de creer, lister et gerer les factures Factur-X/UBL/CII.
 */
class InvoiceResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les factures avec filtrage optionnel.
     *
     * @param array{
     *     direction?: Direction|string,
     *     status?: InvoiceStatus|string,
     *     environment?: string,
     *     company_id?: string,
     *     from?: DateTimeInterface|string,
     *     to?: DateTimeInterface|string,
     *     per_page?: int,
     *     page?: int
     * } $filters
     * @return PaginatedResult<Invoice>
     */
    public function list(array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get('invoices', $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Invoice::fromArray($data));
    }

    /**
     * Recupere une facture par son ID.
     */
    public function get(string $id): Invoice
    {
        $response = $this->http->get("invoices/{$id}");
        return Invoice::fromArray($response['data']);
    }

    /**
     * Cree une nouvelle facture.
     *
     * @param array{
     *     direction: Direction|string,
     *     output_format: OutputFormat|string,
     *     issue_date: DateTimeInterface|string,
     *     total_ht: float,
     *     total_tax: float,
     *     total_ttc: float,
     *     seller_siret: string,
     *     seller_name: string,
     *     seller_address: Address|array,
     *     buyer_siret: string,
     *     buyer_name: string,
     *     buyer_address: Address|array,
     *     lines: InvoiceLine[]|array[],
     *     external_id?: string,
     *     due_date?: DateTimeInterface|string,
     *     currency?: string,
     *     archive_enabled?: bool
     * } $data
     */
    public function create(array $data): Invoice
    {
        $payload = $this->normalizeCreatePayload($data);
        $response = $this->http->post('invoices', $payload);
        return Invoice::fromArray($response['data']);
    }

    /**
     * Update a draft invoice.
     *
     * Only invoices in `draft` status can be updated. Once submitted or
     * validated, the invoice is immutable (ISCA compliance).
     *
     * @param array<string, mixed> $data Partial invoice data
     */
    public function update(string $id, array $data): Invoice
    {
        $response = $this->http->put("invoices/{$id}", $data);
        return Invoice::fromArray($response['data']);
    }

    /**
     * Delete a draft invoice.
     *
     * Only invoices in `draft` status can be deleted. Once submitted,
     * validated, or transmitted, deletion is blocked (ISCA compliance).
     */
    public function delete(string $id): void
    {
        $this->http->delete("invoices/{$id}");
    }

    /**
     * Liste les factures entrantes (fournisseurs).
     *
     * @param array{
     *     status?: InvoiceStatus|string,
     *     from?: DateTimeInterface|string,
     *     to?: DateTimeInterface|string,
     *     per_page?: int,
     *     page?: int
     * } $params
     * @return PaginatedResult<Invoice>
     */
    public function incoming(array $params = []): PaginatedResult
    {
        $query = $this->normalizeFilters($params);
        $response = $this->http->get('invoices/incoming', $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Invoice::fromArray($data));
    }

    /**
     * Accepte une facture entrante.
     *
     * @param string $id ID de la facture
     * @param array{
     *     comment?: string,
     *     metadata?: array
     * } $data Donnees optionnelles
     */
    public function accept(string $id, array $data = []): Invoice
    {
        $response = $this->http->post("invoices/{$id}/accept", $data);
        return Invoice::fromArray($response['data']);
    }

    /**
     * Rejette une facture entrante.
     *
     * @param string $id ID de la facture
     * @param string $reason Motif du rejet
     * @param RejectionCode|string $reasonCode Code de rejet
     */
    public function reject(string $id, string $reason, RejectionCode|string $reasonCode): Invoice
    {
        $code = $reasonCode instanceof RejectionCode ? $reasonCode->value : $reasonCode;

        $response = $this->http->post("invoices/{$id}/reject", [
            'reason' => $reason,
            'reason_code' => $code,
        ]);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Conteste une facture entrante.
     *
     * @param string $id ID de la facture
     * @param string $reason Motif de la contestation
     * @param DisputeType|string $disputeType Type de litige
     * @param float|null $expectedAmount Montant attendu (optionnel)
     */
    public function dispute(
        string $id,
        string $reason,
        DisputeType|string $disputeType,
        ?float $expectedAmount = null
    ): Invoice {
        $type = $disputeType instanceof DisputeType ? $disputeType->value : $disputeType;

        $payload = array_filter([
            'reason' => $reason,
            'dispute_type' => $type,
            'expected_amount' => $expectedAmount,
        ], fn($value) => $value !== null);

        $response = $this->http->post("invoices/{$id}/dispute", $payload);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Marque une facture comme payee (endpoint `POST /invoices/{id}/mark-paid`).
     *
     * Cette action est obligatoire dans le cycle de vie de la facturation
     * electronique francaise pour finaliser le traitement d'une facture
     * sortante (ou entrante via direction).
     *
     * Depuis l'API 2026-05-28, le code moyen de paiement (BT-81 Factur-X)
     * est REQUIS — le backend rejette toute requete sans `payment_means_code`
     * avec un `422 ValidationException`. Le SDK ne peut pas le rendre
     * optionnel sans casser silencieusement l'appel : la signature impose
     * donc un second argument typesafe.
     *
     * @param string $id UUID de la facture
     * @param PaymentMeansCode|string $paymentMeansCode Code UN/ECE 4461 (REQUIS)
     *                                                    accepte un enum ou la valeur string brute
     * @param array{
     *     payment_means_text?: string|null,
     *     payment_reference?: string|null,
     *     paid_at?: string|null,
     *     note?: string|null
     * } $optional Champs optionnels supplementaires
     *
     * @return Invoice
     *
     * @throws \Scell\Sdk\Exceptions\ValidationException 422 si payment_means_code invalide
     *
     * @example
     * ```php
     * use Scell\Sdk\Enums\PaymentMeansCode;
     *
     * $invoice = $client->invoices()->markPaid(
     *     'invoice-uuid',
     *     PaymentMeansCode::SEPA_CREDIT_TRANSFER,
     *     [
     *         'payment_reference' => 'VIR-2026-001234',
     *         'payment_means_text' => 'Compte BNP ...4567',
     *         'paid_at' => '2026-05-28',
     *     ],
     * );
     * ```
     *
     * @since 2.25.0 — `$paymentMeansCode` est devenu requis (BT-81 obligatoire)
     */
    public function markPaid(
        string $id,
        PaymentMeansCode|string $paymentMeansCode,
        array $optional = []
    ): Invoice {
        $payload = $optional;
        $payload['payment_means_code'] = $paymentMeansCode instanceof PaymentMeansCode
            ? $paymentMeansCode->value
            : $paymentMeansCode;

        $response = $this->http->post("invoices/{$id}/mark-paid", $payload);

        return Invoice::fromArray($response['data']);
    }

    /**
     * Soumet une facture pour traitement.
     *
     * @param string $id UUID de la facture
     * @return array{data: array, message?: string}
     */
    public function submit(string $id): array
    {
        return $this->http->post("invoices/{$id}/submit");
    }

    /**
     * Envoie la facture par email a l'acheteur.
     *
     * L'acheteur doit avoir un email ou billing_email renseigne.
     * Si le branding tenant est complet (logo + couleur + footer), il est
     * utilise. Sinon, le branding Scell.io est applique par defaut.
     * La facture est automatiquement validee (draft → validated) si necessaire.
     *
     * @param string $invoiceId UUID de la facture
     * @param array{
     *     email?: string,
     *     subject?: string,
     *     message?: string,
     *     cc?: string[],
     *     bcc?: string[],
     *     force_branding?: 'tenant'|'scell'
     * } $options
     * @return array{message: string, sent_at: string, recipient: string}
     *
     * @throws \Scell\Sdk\Exceptions\BuyerHasNoEmailException si aucun email acheteur
     * @throws \Scell\Sdk\Exceptions\InvoiceBrandingIncompleteException si branding tenant force mais incomplet
     */
    public function sendByEmail(string $invoiceId, array $options = []): array
    {
        return $this->http->post("invoices/{$invoiceId}/send-by-email", $options);
    }

    /**
     * Cree une facture avec le builder fluent.
     *
     * @return InvoiceBuilder
     */
    public function builder(): InvoiceBuilder
    {
        return new InvoiceBuilder($this);
    }

    /**
     * Telecharge un fichier de facture (retourne une URL temporaire).
     *
     * @param string $id ID de la facture
     * @param string $type Type de fichier: 'original', 'converted', 'pdf'
     * @return array{url: string, expires_at: string}
     */
    public function download(string $id, string $type = 'converted'): array
    {
        return $this->http->get("invoices/{$id}/download/{$type}");
    }

    /**
     * Telecharge le contenu binaire d'une facture.
     *
     * @param string $id UUID de la facture
     * @param string $format Format du fichier: 'pdf' (Factur-X) ou 'xml' (UBL/CII)
     * @return string Contenu binaire du fichier
     */
    public function downloadContent(string $id, string $format = 'pdf'): string
    {
        return $this->http->getRaw("invoices/{$id}/download", [
            'format' => $format,
        ]);
    }

    /**
     * Recupere la piste d'audit de la facture.
     *
     * @return array{data: array[], integrity_valid: bool}
     */
    public function auditTrail(string $id): array
    {
        return $this->http->get("invoices/{$id}/audit-trail");
    }

    /**
     * Convertit une facture vers un autre format.
     *
     * @param string $invoiceId ID de la facture
     * @param OutputFormat|string $targetFormat Format cible
     * @return array{message: string, invoice_id: string, target_format: string}
     */
    public function convert(string $invoiceId, OutputFormat|string $targetFormat): array
    {
        $format = $targetFormat instanceof OutputFormat ? $targetFormat->value : $targetFormat;

        return $this->http->post('invoices/convert', [
            'invoice_id' => $invoiceId,
            'target_format' => $format,
        ]);
    }

    /**
     * Normalise les filtres de liste.
     */
    private function normalizeFilters(array $filters): array
    {
        $query = [];

        foreach ($filters as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($value instanceof Direction || $value instanceof InvoiceStatus) {
                $query[$key] = $value->value;
            } elseif ($value instanceof DateTimeInterface) {
                $query[$key] = $value->format('Y-m-d');
            } else {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * Normalise le payload de creation.
     */
    private function normalizeCreatePayload(array $data): array
    {
        $payload = [];

        // Champs simples
        $payload['direction'] = $data['direction'] instanceof Direction
            ? $data['direction']->value
            : $data['direction'];
        $payload['output_format'] = $data['output_format'] instanceof OutputFormat
            ? $data['output_format']->value
            : $data['output_format'];
        $payload['issue_date'] = $data['issue_date'] instanceof DateTimeInterface
            ? $data['issue_date']->format('Y-m-d')
            : $data['issue_date'];
        $payload['total_ht'] = $data['total_ht'];
        $payload['total_tax'] = $data['total_tax'];
        $payload['total_ttc'] = $data['total_ttc'];

        // Vendeur
        $payload['seller_siret'] = $data['seller_siret'];
        $payload['seller_name'] = $data['seller_name'];
        $payload['seller_address'] = $data['seller_address'] instanceof Address
            ? $data['seller_address']->toArray()
            : $data['seller_address'];

        // Acheteur — deux modes :
        //   1. buyer_id : reference le registre Buyer existant. Les autres
        //      champs buyer_* deviennent optionnels (snapshot pris du registre).
        //   2. buyer_* a plat : l'API upsert un Buyer dans le registre par
        //      SIRET (B2B) ou email (B2C / fallback) avant de snapshoter.
        if (isset($data['buyer_id'])) {
            $payload['buyer_id'] = $data['buyer_id'];
        }
        if (isset($data['buyer_siret'])) {
            $payload['buyer_siret'] = $data['buyer_siret'];
        }
        if (isset($data['buyer_name'])) {
            $payload['buyer_name'] = $data['buyer_name'];
        }
        if (isset($data['buyer_address'])) {
            $payload['buyer_address'] = $data['buyer_address'] instanceof Address
                ? $data['buyer_address']->toArray()
                : $data['buyer_address'];
        }

        // Adresse de livraison (Factur-X BG-13). Optionnelle. Si identique
        // a buyer_address, l'API ne l'emet pas dans le XML (presomption
        // EN16931 ship=bill).
        if (isset($data['buyer_shipping_address'])) {
            $payload['buyer_shipping_address'] = $data['buyer_shipping_address'] instanceof Address
                ? $data['buyer_shipping_address']->toArray()
                : $data['buyer_shipping_address'];
        }

        // Flag B2C : transmettre uniquement si explicitement defini
        if (array_key_exists('buyer_is_individual', $data)) {
            $payload['buyer_is_individual'] = (bool) $data['buyer_is_individual'];
        }

        // Lignes
        $payload['lines'] = array_map(
            fn($line) => $line instanceof InvoiceLine ? $line->toArray() : $line,
            $data['lines']
        );

        // Champs optionnels
        if (isset($data['external_id'])) {
            $payload['external_id'] = $data['external_id'];
        }
        if (isset($data['due_date'])) {
            $payload['due_date'] = $data['due_date'] instanceof DateTimeInterface
                ? $data['due_date']->format('Y-m-d')
                : $data['due_date'];
        }
        if (isset($data['currency'])) {
            $payload['currency'] = $data['currency'];
        }
        if (isset($data['archive_enabled'])) {
            $payload['archive_enabled'] = $data['archive_enabled'];
        }

        // Champs acomptes standalone (SDK 2.15.0)
        if (isset($data['invoice_type'])) {
            $payload['invoice_type'] = $data['invoice_type'];
        }
        if (array_key_exists('deposit_group_id', $data)) {
            // Passer explicitement null pour rejoindre un groupe sans UUID
            $payload['deposit_group_id'] = $data['deposit_group_id'];
        }
        if (isset($data['deposit_total_ht'])) {
            $payload['deposit_total_ht'] = $data['deposit_total_ht'];
        }
        if (isset($data['deposit_reference_text'])) {
            $payload['deposit_reference_text'] = $data['deposit_reference_text'];
        }

        // Lien soft vers le devis source (SDK 2.19.0).
        // Permet de creer une facture standard reliee a un devis existant.
        // Anti-IDOR cote backend : 404 si le devis n'appartient pas au tenant
        // courant. 422 si invoice_type != 'standard' (les acomptes/soldes
        // passent par les endpoints dedies POST /quotes/{id}/convert-to-deposit
        // et convert-to-balance qui set parent_quote_id automatiquement).
        if (isset($data['parent_quote_id'])) {
            $payload['parent_quote_id'] = $data['parent_quote_id'];
        }

        return $payload;
    }
}

/**
 * Builder fluent pour creer des factures.
 */
class InvoiceBuilder
{
    private array $data = [];
    private array $lines = [];

    public function __construct(
        private readonly InvoiceResource $resource
    ) {}

    /**
     * @deprecated since v1.9.0 — invoice numbers are server-generated. This method has no effect.
     */
    public function invoiceNumber(string $number): self
    {
        $this->data['invoice_number'] = $number;
        return $this;
    }

    public function externalId(string $id): self
    {
        $this->data['external_id'] = $id;
        return $this;
    }

    public function direction(Direction $direction): self
    {
        $this->data['direction'] = $direction;
        return $this;
    }

    public function outgoing(): self
    {
        return $this->direction(Direction::Outgoing);
    }

    public function incoming(): self
    {
        return $this->direction(Direction::Incoming);
    }

    public function format(OutputFormat $format): self
    {
        $this->data['output_format'] = $format;
        return $this;
    }

    public function facturX(): self
    {
        return $this->format(OutputFormat::FacturX);
    }

    public function ubl(): self
    {
        return $this->format(OutputFormat::UBL);
    }

    public function cii(): self
    {
        return $this->format(OutputFormat::CII);
    }

    public function issueDate(DateTimeInterface|string $date): self
    {
        $this->data['issue_date'] = $date;
        return $this;
    }

    public function dueDate(DateTimeInterface|string $date): self
    {
        $this->data['due_date'] = $date;
        return $this;
    }

    public function currency(string $currency): self
    {
        $this->data['currency'] = $currency;
        return $this;
    }

    public function seller(string $siret, string $name, Address|array $address): self
    {
        $this->data['seller_siret'] = $siret;
        $this->data['seller_name'] = $name;
        $this->data['seller_address'] = $address;
        return $this;
    }

    /**
     * Configure l'acheteur (B2B par defaut).
     *
     * Pour un acheteur particulier (B2C), prefere {@see buyerIndividual()}
     * qui evite de passer un SIRET vide et marque buyer_is_individual=true.
     *
     * @param string|null $siret SIRET de l'acheteur (14 chiffres). Optionnel — null si VAT/legal_id fourni ou B2C.
     */
    public function buyer(?string $siret, string $name, Address|array $address): self
    {
        if ($siret !== null) {
            $this->data['buyer_siret'] = $siret;
        }
        $this->data['buyer_name'] = $name;
        $this->data['buyer_address'] = $address;
        return $this;
    }

    /**
     * Configure l'acheteur comme un particulier (B2C).
     *
     * En B2C :
     * - SIRET / SIREN / VAT / legal_id ne sont pas obligatoires
     * - Factur-X / UBL / CII : balises BT-46/BT-47/BT-48 omises (BR-CO-26)
     * - Mentions de penalites de retard B2B (Code de commerce L441-10) supprimees
     */
    public function buyerIndividual(string $name, Address|array $address): self
    {
        $this->data['buyer_name'] = $name;
        $this->data['buyer_address'] = $address;
        $this->data['buyer_is_individual'] = true;
        return $this;
    }

    /**
     * Marque explicitement la facture comme B2C (acheteur particulier).
     */
    public function asB2c(bool $value = true): self
    {
        $this->data['buyer_is_individual'] = $value;
        return $this;
    }

    /**
     * Reference un acheteur existant du registre par son ID. Quand utilise,
     * les autres `buyer_*` deviennent optionnels — l'API snapshot l'etat
     * courant du registre sur la facture emise.
     */
    public function buyerId(string $id): self
    {
        $this->data['buyer_id'] = $id;
        return $this;
    }

    /**
     * Adresse de livraison (Factur-X BG-13 / BT-71..80). Optionnelle.
     * Quand identique a l'adresse de facturation, l'API n'emet pas le bloc
     * SHIP TO dans le XML (presomption EN16931 ship=bill).
     */
    public function shippingAddress(Address|array $address): self
    {
        $this->data['buyer_shipping_address'] = $address;
        return $this;
    }

    public function addLine(string $description, float $quantity, float $unitPrice, float $taxRate): self
    {
        $this->lines[] = InvoiceLine::create($description, $quantity, $unitPrice, $taxRate);
        return $this;
    }

    public function addLineDto(InvoiceLine $line): self
    {
        $this->lines[] = $line;
        return $this;
    }

    public function archiveEnabled(bool $enabled = true): self
    {
        $this->data['archive_enabled'] = $enabled;
        return $this;
    }

    /**
     * Lie cette facture au devis dont elle est issue.
     *
     * Deux usages :
     *  1. Conversion devis → acompte/solde via QuoteResource::convertToDeposit()
     *     ou convertToBalance() : `parent_quote_id` est set automatiquement par
     *     ces endpoints dedies.
     *  2. Facture standard ($invoiceType absent ou 'standard') referencant un
     *     devis source : appeler parentQuoteId() explicitement avant create().
     *     Anti-IDOR : le backend retourne 404 si le devis n'appartient pas au
     *     tenant courant, 422 si invoice_type != 'standard'.
     *
     * Disponible explicitement sur les factures standard depuis SDK 2.19.0.
     */
    public function parentQuoteId(string $id): self
    {
        $this->data['parent_quote_id'] = $id;
        return $this;
    }

    /**
     * Definit le type de facture dans le cycle devis-facture.
     *
     * @param 'standard'|'deposit'|'balance' $type
     */
    public function invoiceType(string $type): self
    {
        $this->data['invoice_type'] = $type;
        return $this;
    }

    /**
     * Definit le montant fixe de l'acompte (facture de type 'deposit').
     *
     * Preferer depositPercent() si le montant est exprime en pourcentage.
     */
    public function depositAmount(float $amount): self
    {
        $this->data['deposit_amount'] = $amount;
        return $this;
    }

    /**
     * Definit le pourcentage de l'acompte (facture de type 'deposit').
     *
     * @param float $pct Pourcentage entre 1 et 100 (ex: 30.0 pour 30%)
     */
    public function depositPercent(float $pct): self
    {
        $this->data['deposit_percent'] = $pct;
        return $this;
    }

    /**
     * Libelle personnalise de la ligne d'acompte sur la facture.
     */
    public function depositLabel(string $label): self
    {
        $this->data['deposit_label'] = $label;
        return $this;
    }

    /**
     * Definit le type de la facture standalone (sans devis parent).
     *
     * Utiliser 'deposit' pour une facture d'acompte directe (TVA immediatement
     * exigible, CGI art. 289) ou 'balance' pour une facture de solde qui
     * deduit les acomptes precedents (Factur-X BG-22 code '80').
     *
     * @param 'standard'|'deposit'|'balance' $type
     *
     * Disponible depuis SDK 2.15.0.
     */
    public function invoiceTypeStandalone(string $type): self
    {
        $this->data['invoice_type'] = $type;
        return $this;
    }

    /**
     * UUID d'un groupe d'acomptes existant a rejoindre.
     *
     * Si absent / null : un nouveau groupe est cree (uniquement pertinent
     * avec invoice_type='deposit').
     * Si fourni : lie cette facture au groupe existant. Le UUID doit
     * appartenir au meme tenant/sub-tenant (404 sinon, anti-IDOR).
     *
     * Disponible depuis SDK 2.15.0.
     */
    public function depositGroupId(?string $groupId): self
    {
        $this->data['deposit_group_id'] = $groupId;
        return $this;
    }

    /**
     * Montant total HT du deal commercial.
     *
     * Stocke sur le groupe a la creation ; ignore si un deposit_group_id
     * existant est fourni.
     *
     * Disponible depuis SDK 2.15.0.
     */
    public function depositTotalHt(float $amount): self
    {
        $this->data['deposit_total_ht'] = $amount;
        return $this;
    }

    /**
     * Texte de reference libre pour le groupe d'acomptes
     * (numero de bon de commande, reference contrat, etc.). Max 500 chars.
     *
     * Disponible depuis SDK 2.15.0.
     */
    public function depositReferenceText(string $text): self
    {
        $this->data['deposit_reference_text'] = $text;
        return $this;
    }

    /**
     * Calcule automatiquement les totaux et cree la facture.
     */
    public function create(): Invoice
    {
        // Calculer les totaux si non fournis
        if (!isset($this->data['total_ht'])) {
            $totalHt = array_sum(array_map(fn(InvoiceLine $l) => $l->totalHt, $this->lines));
            $totalTax = array_sum(array_map(fn(InvoiceLine $l) => $l->totalTax, $this->lines));
            $this->data['total_ht'] = round($totalHt, 2);
            $this->data['total_tax'] = round($totalTax, 2);
            $this->data['total_ttc'] = round($totalHt + $totalTax, 2);
        }

        $this->data['lines'] = $this->lines;

        return $this->resource->create($this->data);
    }
}
