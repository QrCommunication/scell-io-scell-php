<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Represente une ligne de facture.
 */
readonly class InvoiceLine
{
    public function __construct(
        public string $description,
        public float $quantity,
        public float $unitPrice,
        public float $taxRate,
        public float $totalHt,
        public float $totalTax,
        public float $totalTtc,
        public ?int $lineNumber = null,
    ) {}

    /**
     * Cree une instance a partir d'un tableau.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            description: $data['description'],
            quantity: (float) $data['quantity'],
            unitPrice: (float) ($data['unit_price'] ?? $data['unitPrice']),
            taxRate: (float) ($data['tax_rate'] ?? $data['taxRate']),
            totalHt: (float) ($data['total_ht'] ?? $data['totalHt']),
            totalTax: (float) ($data['total_tax'] ?? $data['totalTax']),
            totalTtc: (float) ($data['total_ttc'] ?? $data['totalTtc']),
            lineNumber: isset($data['line_number']) ? (int) $data['line_number'] : null,
        );
    }

    /**
     * Cree une ligne avec calcul automatique des totaux.
     */
    public static function create(
        string $description,
        float $quantity,
        float $unitPrice,
        float $taxRate
    ): self {
        $totalHt = $quantity * $unitPrice;
        $totalTax = $totalHt * ($taxRate / 100);
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
