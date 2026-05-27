<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;
use Scell\Sdk\Enums\Direction;
use Scell\Sdk\Enums\Environment;
use Scell\Sdk\Enums\InvoiceStatus;
use Scell\Sdk\Enums\OutputFormat;
use Scell\Sdk\Enums\RefundStatus;

/**
 * Represente une facture electronique.
 */
readonly class Invoice
{
    /**
     * @param InvoiceLine[] $lines
     */
    public function __construct(
        public string $id,
        public string $invoiceNumber,
        public Direction $direction,
        public OutputFormat $outputFormat,
        public DateTimeImmutable $issueDate,
        public float $totalHt,
        public float $totalTax,
        public float $totalTtc,
        public string $sellerName,
        public Address $sellerAddress,
        public string $buyerName,
        public Address $buyerAddress,
        public array $lines,
        public InvoiceStatus $status,
        public Environment $environment,
        public ?string $sellerSiret = null,
        public ?string $buyerSiret = null,
        public ?string $sellerVatNumber = null,
        public ?string $buyerVatNumber = null,
        public string $sellerCountry = 'FR',
        public string $buyerCountry = 'FR',
        public ?string $sellerLegalId = null,
        public ?string $sellerLegalIdScheme = null,
        public ?string $buyerLegalId = null,
        public ?string $buyerLegalIdScheme = null,
        public ?string $externalId = null,
        public ?DateTimeImmutable $dueDate = null,
        public string $currency = 'EUR',
        public ?string $statusMessage = null,
        public bool $archiveEnabled = false,
        public ?float $amountCharged = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $validatedAt = null,
        public ?DateTimeImmutable $transmittedAt = null,
        public ?DateTimeImmutable $completedAt = null,
        public ?DateTimeImmutable $paidAt = null,
        public ?string $paymentReference = null,
        public ?string $paymentNote = null,
        /**
         * B2C flag : true si l'acheteur est un particulier (B2C).
         * En B2C, SIRET / SIREN / VAT / legal_id sont optionnels et
         * la generation Factur-X / UBL / CII omet BT-46/BT-47/BT-48
         * (conforme BR-CO-26 EN16931).
         */
        public bool $buyerIsIndividual = false,
        /**
         * Nombre d'avoirs (credit notes) emis lies a cette facture.
         * Inclut les avoirs partiels et totaux. 0 si pas d'avoir.
         * Disponible depuis SDK 1.15.0 (API 2026-05-04).
         */
        public int $creditNotesCount = 0,
        /**
         * Montant total avoire (somme des avoirs valides/envoyes/transmis).
         * Permet de detecter qu'une facture est totalement avoiree
         * (creditedAmount >= totalTtc) ou partiellement.
         * Disponible depuis SDK 1.15.0.
         */
        public float $creditedAmount = 0.0,
        /**
         * Soft link vers le registre Buyer (si la facture a ete creee
         * apres l'introduction du registre, fin avril 2026). NULL pour
         * les factures historiques. Le snapshot (buyer_*) reste la
         * source de verite (ISCA immutability).
         */
        public ?string $buyerId = null,
        /**
         * Adresse de livraison (Factur-X BG-13 / BT-71..80). NULL si
         * identique a l'adresse de facturation (presomption EN16931).
         */
        public ?Address $buyerShippingAddress = null,
        /**
         * Type de facture dans le cycle de vie devis-facture.
         * Valeurs : 'standard' (facture classique), 'deposit' (acompte),
         * 'balance' (solde, deduit les acomptes). NULL pour les factures
         * historiques anterieures au module devis.
         */
        public ?string $invoiceType = null,
        /**
         * ID du devis dont cette facture est issue (si convertie depuis un devis).
         * NULL pour les factures crees directement.
         */
        public ?string $parentQuoteId = null,
        /**
         * IDs des factures d'acompte liees a cette facture de solde.
         * NULL ou vide si la facture n'est pas de type 'balance'.
         *
         * @var string[]|null
         */
        public ?array $parentInvoiceIds = null,
        /**
         * UUID du groupe d'acomptes standalone auquel appartient cette facture.
         * Renseigne sur les factures d'acompte directes (sans devis parent),
         * crees avec invoice_type='deposit'. Toutes les factures du groupe
         * partagent le meme deposit_group_id.
         * Disponible depuis SDK 2.15.0 (API 2026-05-24).
         */
        public ?string $depositGroupId = null,
        /**
         * Montant total HT du deal commercial, stocke a la creation du groupe.
         * Sert a suivre la progression des acomptes emis.
         * Disponible depuis SDK 2.15.0.
         */
        public ?float $depositTotalHt = null,
        /**
         * Texte de reference libre pour le groupe d'acomptes
         * (numero de bon de commande, reference contrat...).
         * Disponible depuis SDK 2.15.0.
         */
        public ?string $depositReferenceText = null,
        /**
         * Progression du groupe d'acomptes (present uniquement sur le detail,
         * pas sur les listes). NULL si la facture n'appartient pas a un groupe.
         * Structure : deposit_total_ht, sum_deposits_ht, remaining_ht,
         * progress_percent (0-100), has_balance, invoices_count, invoices[].
         * Disponible depuis SDK 2.15.0.
         *
         * @var array{
         *     deposit_total_ht: float,
         *     sum_deposits_ht: float,
         *     remaining_ht: float,
         *     progress_percent: float,
         *     has_balance: bool,
         *     invoices_count: int,
         *     invoices: array<array{id: string, invoice_type: string, total_ht: float, invoice_number: string}>
         * }|null
         */
        public ?array $depositGroupProgress = null,
        /**
         * Statut de remboursement derive du statut Invoice par le backend.
         * Toujours present sur les reponses API (`refund_status` field).
         * Par defaut `None` si le champ est absent (compat. anciennes API).
         * Disponible depuis SDK 2.20.0 (API 2026-05-27).
         */
        public RefundStatus $refundStatus = RefundStatus::None,
        /**
         * Somme des avoirs valides rattaches a la facture (en EUR).
         * Calcule par le backend via SUM(credit_notes.total) sur les statuts
         * validated/completed/sent/transmitted/accepted. 0.0 si aucun avoir.
         * Equivalent fonctionnel du champ legacy `creditedAmount`, mais aligne
         * sur le nouveau contrat backend (`total_refunded`).
         * Disponible depuis SDK 2.20.0.
         */
        public float $totalRefunded = 0.0,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        $lines = array_map(
            fn(array $line) => InvoiceLine::fromArray($line),
            $data['lines'] ?? []
        );

        return new self(
            id: $data['id'],
            invoiceNumber: $data['invoice_number'],
            direction: Direction::from($data['direction']),
            outputFormat: OutputFormat::from($data['output_format']),
            issueDate: new DateTimeImmutable($data['issue_date']),
            totalHt: (float) $data['total_ht'],
            totalTax: (float) $data['total_tax'],
            totalTtc: (float) $data['total_ttc'],
            sellerName: $data['seller']['name'],
            sellerAddress: Address::fromArray($data['seller']['address']),
            buyerName: $data['buyer']['name'],
            buyerAddress: Address::fromArray($data['buyer']['address']),
            lines: $lines,
            status: InvoiceStatus::from($data['status']),
            environment: Environment::from($data['environment']),
            sellerSiret: $data['seller']['siret'] ?? null,
            buyerSiret: $data['buyer']['siret'] ?? null,
            sellerVatNumber: $data['seller_vat_number'] ?? null,
            buyerVatNumber: $data['buyer_vat_number'] ?? null,
            sellerCountry: $data['seller_country'] ?? 'FR',
            buyerCountry: $data['buyer_country'] ?? 'FR',
            sellerLegalId: $data['seller_legal_id'] ?? null,
            sellerLegalIdScheme: $data['seller_legal_id_scheme'] ?? null,
            buyerLegalId: $data['buyer_legal_id'] ?? null,
            buyerLegalIdScheme: $data['buyer_legal_id_scheme'] ?? null,
            externalId: $data['external_id'] ?? null,
            dueDate: isset($data['due_date']) ? new DateTimeImmutable($data['due_date']) : null,
            currency: $data['currency'] ?? 'EUR',
            statusMessage: $data['status_message'] ?? null,
            archiveEnabled: $data['archive_enabled'] ?? false,
            amountCharged: isset($data['amount_charged']) ? (float) $data['amount_charged'] : null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            validatedAt: isset($data['validated_at']) ? new DateTimeImmutable($data['validated_at']) : null,
            transmittedAt: isset($data['transmitted_at']) ? new DateTimeImmutable($data['transmitted_at']) : null,
            completedAt: isset($data['completed_at']) ? new DateTimeImmutable($data['completed_at']) : null,
            paidAt: isset($data['paid_at']) ? new DateTimeImmutable($data['paid_at']) : null,
            paymentReference: $data['payment_reference'] ?? null,
            paymentNote: $data['payment_note'] ?? null,
            buyerIsIndividual: (bool) ($data['buyer']['is_individual'] ?? $data['buyer_is_individual'] ?? false),
            creditNotesCount: (int) ($data['credit_notes_count'] ?? 0),
            creditedAmount: (float) ($data['credited_amount'] ?? 0),
            buyerId: $data['buyer']['id'] ?? $data['buyer_id'] ?? null,
            buyerShippingAddress: isset($data['buyer']['shipping_address']) && is_array($data['buyer']['shipping_address'])
                ? Address::fromArray($data['buyer']['shipping_address'])
                : (isset($data['buyer_shipping_address']) && is_array($data['buyer_shipping_address'])
                    ? Address::fromArray($data['buyer_shipping_address'])
                    : null),
            invoiceType: $data['invoice_type'] ?? null,
            parentQuoteId: $data['parent_quote_id'] ?? null,
            parentInvoiceIds: isset($data['parent_invoice_ids']) && is_array($data['parent_invoice_ids'])
                ? $data['parent_invoice_ids']
                : null,
            depositGroupId: $data['deposit_group_id'] ?? null,
            depositTotalHt: isset($data['deposit_total_ht']) ? (float) $data['deposit_total_ht'] : null,
            depositReferenceText: $data['deposit_reference_text'] ?? null,
            depositGroupProgress: isset($data['deposit_group_progress']) && is_array($data['deposit_group_progress'])
                ? $data['deposit_group_progress']
                : null,
            refundStatus: isset($data['refund_status']) && is_string($data['refund_status'])
                ? (RefundStatus::tryFrom($data['refund_status']) ?? RefundStatus::None)
                : RefundStatus::None,
            totalRefunded: isset($data['total_refunded'])
                ? (float) $data['total_refunded']
                : 0.0,
        );
    }

    /**
     * Indique si la facture a au moins un avoir (partiel ou total).
     * Disponible depuis SDK 1.15.0.
     */
    public function hasCreditNotes(): bool
    {
        return $this->creditNotesCount > 0;
    }

    /**
     * Indique si la facture est totalement avoiree (avoirs >= totalTtc).
     * Disponible depuis SDK 1.15.0.
     */
    public function isFullyCredited(): bool
    {
        return $this->creditedAmount > 0
            && $this->creditedAmount + 0.001 >= $this->totalTtc;
    }

    /**
     * Verifie si la facture est B2C (acheteur particulier).
     */
    public function isB2c(): bool
    {
        return $this->buyerIsIndividual;
    }

    /**
     * Verifie si la facture est B2B (acheteur entreprise).
     */
    public function isB2b(): bool
    {
        return ! $this->buyerIsIndividual;
    }

    /**
     * Verifie si la facture est en mode sandbox.
     */
    public function isSandbox(): bool
    {
        return $this->environment->isSandbox();
    }

    /**
     * Verifie si la facture est une facture de vente.
     */
    public function isOutgoing(): bool
    {
        return $this->direction === Direction::Outgoing;
    }

    /**
     * Verifie si le statut est final.
     */
    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Verifie si la facture a ete payee.
     */
    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid || $this->paidAt !== null;
    }

    /**
     * Verifie si la facture est une facture entrante (fournisseur).
     */
    public function isIncoming(): bool
    {
        return $this->direction === Direction::Incoming;
    }

    /**
     * Indique si la facture a au moins un avoir (partiel ou total).
     *
     * Equivalent fonctionnel de `$this->refundStatus->hasRefund()`. Disponible
     * depuis SDK 2.20.0 — preferer cette methode a `hasCreditNotes()` qui
     * regarde le compteur d'objets credit_notes (incluant les drafts) plutot
     * que le statut metier propage par CreditNoteObserver.
     */
    public function isRefunded(): bool
    {
        return $this->refundStatus->hasRefund() || $this->status->isRefunded();
    }

    /**
     * Indique si la facture est partiellement avoiree.
     *
     * Disponible depuis SDK 2.20.0.
     */
    public function isPartiallyRefunded(): bool
    {
        return $this->refundStatus === RefundStatus::Partial
            || $this->status === InvoiceStatus::PartiallyRefunded;
    }

    /**
     * Indique si la facture est totalement avoiree.
     *
     * Disponible depuis SDK 2.20.0. Preferer cette methode a
     * `isFullyCredited()` (basee sur la somme float `creditedAmount`) — le
     * backend pose le statut de facon idempotente via observer, ce qui evite
     * les ecarts d'arrondi.
     */
    public function isFullyRefunded(): bool
    {
        return $this->refundStatus === RefundStatus::Full
            || $this->status === InvoiceStatus::Refunded;
    }
}
