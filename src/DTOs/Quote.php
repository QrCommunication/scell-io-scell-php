<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;
use Scell\Sdk\Enums\Environment;

/**
 * Represente un devis (quote).
 */
readonly class Quote
{
    /**
     * @param QuoteLine[] $lines
     */
    public function __construct(
        public string $id,
        public string $quoteNumber,
        public string $status,
        public DateTimeImmutable $issueDate,
        public float $totalHt,
        public float $totalTax,
        public float $totalTtc,
        public string $sellerName,
        public Address $sellerAddress,
        public string $buyerName,
        public Address $buyerAddress,
        public array $lines,
        public Environment $environment,
        public bool $signatureRequired = false,
        public ?string $sellerSiret = null,
        public ?string $buyerSiret = null,
        public ?string $sellerVatNumber = null,
        public ?string $buyerVatNumber = null,
        public string $currency = 'EUR',
        public ?DateTimeImmutable $validUntil = null,
        public ?string $externalId = null,
        public ?string $notes = null,
        public ?string $paymentTerms = null,
        public ?array $metadata = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?DateTimeImmutable $sentAt = null,
        public ?DateTimeImmutable $acceptedAt = null,
        public ?DateTimeImmutable $refusedAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?DateTimeImmutable $convertedAt = null,
        public ?string $buyerId = null,
        public bool $buyerIsIndividual = false,
        public ?Address $buyerShippingAddress = null,
        public ?string $subTenantId = null,
        public ?string $companyId = null,
        public ?string $parentQuoteId = null,
        /** URL publique de consultation/signature par le buyer. */
        public ?string $publicUrl = null,
        public ?DateTimeImmutable $publicUrlExpiresAt = null,
        /** Informations de signature si signatureRequired=true. */
        public ?QuoteSignature $signature = null,
        /** Acomptes/echeances de paiement lies au devis (si planifies). */
        public ?array $depositSchedule = null,
        /** ID de la facture d'acompte convertie (si convertToDeposit appele). */
        public ?string $depositInvoiceId = null,
        /** ID de la facture de solde convertie (si convertToBalance appele). */
        public ?string $balanceInvoiceId = null,
        /**
         * URL de callback fournie a la creation. Le viewer public redirige
         * le buyer vers cette URL apres accept/refuse avec status + IDs +
         * reason en query string. Null = comportement par defaut (page
         * confirmation Scell.io).
         */
        public ?string $callbackUrl = null,
        /**
         * Scellement du devis signe : signature PAdES + ancrage Bitcoin
         * (OpenTimestamps). Present uniquement une fois le devis scelle.
         */
        public ?QuoteSealing $sealing = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        $lines = array_map(
            fn(array $line) => QuoteLine::fromArray($line),
            $data['lines'] ?? []
        );

        $signature = null;
        if (isset($data['signature']) && is_array($data['signature'])) {
            $signature = QuoteSignature::fromArray($data['signature']);
        }

        $sealing = null;
        if (isset($data['sealing']) && is_array($data['sealing'])) {
            $sealing = QuoteSealing::fromArray($data['sealing']);
        }

        $buyerShipping = null;
        if (isset($data['buyer']['shipping_address']) && is_array($data['buyer']['shipping_address'])) {
            $buyerShipping = Address::fromArray($data['buyer']['shipping_address']);
        } elseif (isset($data['buyer_shipping_address']) && is_array($data['buyer_shipping_address'])) {
            $buyerShipping = Address::fromArray($data['buyer_shipping_address']);
        }

        return new self(
            id: $data['id'],
            quoteNumber: $data['quote_number'],
            status: $data['status'],
            issueDate: new DateTimeImmutable($data['issue_date']),
            totalHt: (float) $data['total_ht'],
            totalTax: (float) $data['total_tax'],
            totalTtc: (float) $data['total_ttc'],
            sellerName: $data['seller']['name'] ?? $data['seller_name'] ?? '',
            sellerAddress: Address::fromArray($data['seller']['address'] ?? $data['seller_address'] ?? []),
            buyerName: $data['buyer']['name'] ?? $data['buyer_name'] ?? '',
            buyerAddress: Address::fromArray($data['buyer']['address'] ?? $data['buyer_address'] ?? []),
            lines: $lines,
            environment: Environment::from($data['environment']),
            signatureRequired: (bool) ($data['signature_required'] ?? false),
            sellerSiret: $data['seller']['siret'] ?? $data['seller_siret'] ?? null,
            buyerSiret: $data['buyer']['siret'] ?? $data['buyer_siret'] ?? null,
            sellerVatNumber: $data['seller_vat_number'] ?? null,
            buyerVatNumber: $data['buyer_vat_number'] ?? null,
            currency: $data['currency'] ?? 'EUR',
            validUntil: isset($data['valid_until']) ? new DateTimeImmutable($data['valid_until']) : null,
            externalId: $data['external_id'] ?? null,
            notes: $data['notes'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
            metadata: $data['metadata'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
            sentAt: isset($data['sent_at']) ? new DateTimeImmutable($data['sent_at']) : null,
            acceptedAt: isset($data['accepted_at']) ? new DateTimeImmutable($data['accepted_at']) : null,
            refusedAt: isset($data['refused_at']) ? new DateTimeImmutable($data['refused_at']) : null,
            cancelledAt: isset($data['cancelled_at']) ? new DateTimeImmutable($data['cancelled_at']) : null,
            convertedAt: isset($data['converted_at']) ? new DateTimeImmutable($data['converted_at']) : null,
            buyerId: $data['buyer']['id'] ?? $data['buyer_id'] ?? null,
            buyerIsIndividual: (bool) ($data['buyer']['is_individual'] ?? $data['buyer_is_individual'] ?? false),
            buyerShippingAddress: $buyerShipping,
            subTenantId: $data['sub_tenant_id'] ?? null,
            companyId: $data['company_id'] ?? null,
            parentQuoteId: $data['parent_quote_id'] ?? null,
            publicUrl: $data['public_url'] ?? null,
            publicUrlExpiresAt: isset($data['public_url_expires_at']) ? new DateTimeImmutable($data['public_url_expires_at']) : null,
            signature: $signature,
            depositSchedule: $data['deposit_schedule'] ?? null,
            depositInvoiceId: $data['deposit_invoice_id'] ?? null,
            balanceInvoiceId: $data['balance_invoice_id'] ?? null,
            callbackUrl: $data['callback_url'] ?? null,
            sealing: $sealing,
        );
    }

    /**
     * Indique si le devis est en brouillon.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Indique si le devis a ete envoye au buyer.
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Indique si le devis a ete accepte.
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Indique si le devis a ete refuse.
     */
    public function isRefused(): bool
    {
        return $this->status === 'refused';
    }

    /**
     * Indique si le devis a ete annule.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Indique si le devis a ete converti en facture.
     */
    public function isConverted(): bool
    {
        return $this->status === 'converted' || $this->convertedAt !== null;
    }

    /**
     * Indique si le devis est expire (validUntil depasse et non converti).
     */
    public function isExpired(): bool
    {
        return $this->validUntil !== null
            && $this->validUntil < new DateTimeImmutable()
            && ! $this->isConverted()
            && ! $this->isCancelled();
    }

    /**
     * Indique si le devis est en mode sandbox.
     */
    public function isSandbox(): bool
    {
        return $this->environment->isSandbox();
    }

    /**
     * Indique si le devis a une signature electronique completee.
     */
    public function isSigned(): bool
    {
        return $this->signature !== null && $this->signature->isSigned();
    }

    /**
     * Indique si le devis a ete scelle (PDF signe PAdES + ancrage Bitcoin).
     */
    public function isSealed(): bool
    {
        return $this->sealing !== null && $this->sealing->isSealed;
    }

    /**
     * Indique si le devis est B2C (acheteur particulier).
     */
    public function isB2c(): bool
    {
        return $this->buyerIsIndividual;
    }

    /**
     * Indique si le devis est convertible en facture.
     * Un devis accepte ou envoye peut etre converti.
     */
    public function isConvertible(): bool
    {
        return in_array($this->status, ['accepted', 'sent'], true);
    }
}
