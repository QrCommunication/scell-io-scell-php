<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Represente une ligne de devis.
 */
readonly class QuoteLine
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
        public ?string $unit = null,
        public ?string $reference = null,
        public ?string $discount = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     */
    public static function fromArray(array $data): self
    {
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $quantity  = (float) ($data['quantity'] ?? 1);
        $taxRate   = (float) ($data['tax_rate'] ?? 20);
        $totalHt   = (float) ($data['total_ht'] ?? round($unitPrice * $quantity, 2));
        $totalTax  = (float) ($data['total_tax'] ?? round($totalHt * $taxRate / 100, 2));

        return new self(
            description: $data['description'] ?? '',
            quantity: $quantity,
            unitPrice: $unitPrice,
            taxRate: $taxRate,
            totalHt: $totalHt,
            totalTax: $totalTax,
            totalTtc: (float) ($data['total_ttc'] ?? round($totalHt + $totalTax, 2)),
            id: $data['id'] ?? null,
            unit: $data['unit'] ?? null,
            reference: $data['reference'] ?? null,
            discount: $data['discount'] ?? null,
        );
    }

    /**
     * Cree une ligne de devis avec calcul automatique des totaux.
     */
    public static function create(
        string $description,
        float $quantity,
        float $unitPrice,
        float $taxRate = 20.0,
        ?string $unit = null,
        ?string $reference = null,
    ): self {
        $totalHt  = round($unitPrice * $quantity, 2);
        $totalTax = round($totalHt * $taxRate / 100, 2);

        return new self(
            description: $description,
            quantity: $quantity,
            unitPrice: $unitPrice,
            taxRate: $taxRate,
            totalHt: $totalHt,
            totalTax: $totalTax,
            totalTtc: round($totalHt + $totalTax, 2),
            unit: $unit,
            reference: $reference,
        );
    }

    /**
     * Convertit en tableau pour l'API.
     */
    public function toArray(): array
    {
        return array_filter([
            'description' => $this->description,
            'quantity'    => $this->quantity,
            'unit_price'  => $this->unitPrice,
            'tax_rate'    => $this->taxRate,
            'unit'        => $this->unit,
            'reference'   => $this->reference,
            'discount'    => $this->discount,
        ], fn($v) => $v !== null);
    }
}
