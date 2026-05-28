<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;
use Scell\Sdk\Enums\Environment;
use Scell\Sdk\Enums\PaymentMeansCode;

/**
 * Represente un avoir (credit note).
 *
 * Un avoir est un document comptable permettant de corriger ou annuler
 * tout ou partie d'une facture emise.
 *
 * @example
 * ```php
 * // Creer depuis une reponse API
 * $creditNote = CreditNote::fromArray($apiResponse['data']);
 *
 * // Acceder aux proprietes
 * echo $creditNote->creditNoteNumber;
 * echo $creditNote->reason;
 * echo "Montant: {$creditNote->totalTtc} EUR";
 *
 * // Verifier le statut
 * if ($creditNote->isDraft()) {
 *     echo "L'avoir peut encore etre modifie";
 * }
 * ```
 */
readonly class CreditNote
{
    /**
     * @param CreditNoteLine[] $lines
     */
    public function __construct(
        public string $id,
        public string $creditNoteNumber,
        public string $invoiceId,
        public string $type,
        public string $reason,
        public string $status,
        public float $totalHt,
        public float $totalTax,
        public float $totalTtc,
        public array $lines,
        public Environment $environment,
        public ?string $invoiceNumber = null,
        public ?string $externalId = null,
        public ?string $sellerSiret = null,
        public ?string $sellerName = null,
        public ?string $buyerSiret = null,
        public ?string $buyerName = null,
        public string $currency = 'EUR',
        public ?array $metadata = null,
        public ?DateTimeImmutable $issueDate = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $sentAt = null,
        /**
         * B2C flag : true si l'acheteur est un particulier.
         * Herite de la facture associee. Determine si les balises
         * BT-46/BT-47/BT-48 sont incluses dans Factur-X / UBL / CII.
         */
        public bool $buyerIsIndividual = false,
        /**
         * Code UN/ECE 4461 du moyen de paiement de l'avoir (BT-81 EN16931 /
         * Factur-X). Herite typiquement de la facture associee. NULL pour les
         * avoirs non encore liquides ou anterieurs a SDK 2.25.0.
         *
         * Disponible depuis SDK 2.25.0 (API 2026-05-28).
         */
        public ?PaymentMeansCode $paymentMeansCode = null,
        /**
         * Libelle libre BT-82 associe au moyen de paiement (compte bancaire
         * source, etc.). Optionnel. Max 100 caracteres.
         *
         * Disponible depuis SDK 2.25.0 (API 2026-05-28).
         */
        public ?string $paymentMeansText = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $lines = array_map(
            fn(array $line) => CreditNoteLine::fromArray($line),
            $data['lines'] ?? $data['items'] ?? []
        );

        return new self(
            id: $data['id'],
            creditNoteNumber: $data['credit_note_number'] ?? $data['number'] ?? '',
            invoiceId: $data['invoice_id'],
            type: $data['type'],
            reason: $data['reason'],
            status: $data['status'],
            totalHt: (float) ($data['total_ht'] ?? 0),
            totalTax: (float) ($data['total_tax'] ?? 0),
            totalTtc: (float) ($data['total_ttc'] ?? 0),
            lines: $lines,
            environment: isset($data['environment']) ? Environment::from($data['environment']) : Environment::Production,
            invoiceNumber: $data['invoice_number'] ?? $data['invoice']['invoice_number'] ?? null,
            externalId: $data['external_id'] ?? null,
            sellerSiret: $data['seller']['siret'] ?? $data['seller_siret'] ?? null,
            sellerName: $data['seller']['name'] ?? $data['seller_name'] ?? null,
            buyerSiret: $data['buyer']['siret'] ?? $data['buyer_siret'] ?? null,
            buyerName: $data['buyer']['name'] ?? $data['buyer_name'] ?? null,
            currency: $data['currency'] ?? 'EUR',
            metadata: $data['metadata'] ?? null,
            issueDate: isset($data['issue_date']) ? new DateTimeImmutable($data['issue_date']) : null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            sentAt: isset($data['sent_at']) ? new DateTimeImmutable($data['sent_at']) : null,
            buyerIsIndividual: (bool) ($data['buyer']['is_individual'] ?? $data['buyer_is_individual'] ?? false),
            paymentMeansCode: isset($data['payment_means_code']) && is_string($data['payment_means_code'])
                ? PaymentMeansCode::tryFrom($data['payment_means_code'])
                : null,
            paymentMeansText: isset($data['payment_means_text']) && is_string($data['payment_means_text'])
                ? $data['payment_means_text']
                : null,
        );
    }

    /**
     * Verifie si l'avoir est B2C (acheteur particulier).
     */
    public function isB2c(): bool
    {
        return $this->buyerIsIndividual;
    }

    /**
     * Verifie si l'avoir est B2B (acheteur entreprise).
     */
    public function isB2b(): bool
    {
        return ! $this->buyerIsIndividual;
    }

    /**
     * Verifie si l'avoir est en brouillon.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Verifie si l'avoir est valide.
     */
    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }

    /**
     * Verifie si l'avoir a ete envoye.
     */
    public function isSent(): bool
    {
        return $this->status === 'sent' || $this->sentAt !== null;
    }

    /**
     * Verifie si l'avoir est en mode sandbox.
     */
    public function isSandbox(): bool
    {
        return $this->environment->isSandbox();
    }

    /**
     * Verifie si c'est un avoir total.
     */
    public function isFullCredit(): bool
    {
        return $this->type === 'full';
    }

    /**
     * Verifie si c'est un avoir partiel.
     */
    public function isPartialCredit(): bool
    {
        return $this->type === 'partial';
    }

    /**
     * Verifie si c'est une remise.
     */
    public function isDiscount(): bool
    {
        return $this->type === 'discount';
    }

    /**
     * Retourne le libelle du type en francais.
     */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'full' => 'Avoir total',
            'partial' => 'Avoir partiel',
            'discount' => 'Remise',
            default => $this->type,
        };
    }

    /**
     * Retourne le libelle du statut en francais.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Brouillon',
            'validated' => 'Valide',
            'sent' => 'Envoye',
            'error' => 'Erreur',
            default => $this->status,
        };
    }
}

/**
 * Represente une ligne d'avoir.
 */
readonly class CreditNoteLine
{
    public function __construct(
        public string $description,
        public float $quantity,
        public float $unitPrice,
        public float $taxRate,
        public float $totalHt,
        public float $totalTax,
        public float $totalTtc,
        public ?string $id = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $quantity = (float) ($data['quantity'] ?? 1);
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $totalHt = $data['total_ht'] ?? ($quantity * $unitPrice);
        $totalTax = $data['total_tax'] ?? ($totalHt * $taxRate / 100);
        $totalTtc = $data['total_ttc'] ?? ($totalHt + $totalTax);

        return new self(
            description: $data['description'],
            quantity: $quantity,
            unitPrice: $unitPrice,
            taxRate: $taxRate,
            totalHt: (float) $totalHt,
            totalTax: (float) $totalTax,
            totalTtc: (float) $totalTtc,
            id: $data['id'] ?? null,
        );
    }

    /**
     * Cree une nouvelle ligne.
     */
    public static function create(string $description, float $quantity, float $unitPrice, float $taxRate): self
    {
        $totalHt = $quantity * $unitPrice;
        $totalTax = $totalHt * $taxRate / 100;
        $totalTtc = $totalHt + $totalTax;

        return new self(
            description: $description,
            quantity: $quantity,
            unitPrice: $unitPrice,
            taxRate: $taxRate,
            totalHt: round($totalHt, 2),
            totalTax: round($totalTax, 2),
            totalTtc: round($totalTtc, 2),
        );
    }

    /**
     * Convertit en tableau pour l'API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'tax_rate' => $this->taxRate,
            'total_ht' => $this->totalHt,
            'total_tax' => $this->totalTax,
            'total_ttc' => $this->totalTtc,
        ];
    }
}
