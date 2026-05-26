<?php

declare(strict_types=1);

namespace Scell\Sdk\Builders;

use Scell\Sdk\Enums\VatCategory;

/**
 * Builder fluent pour construire le payload d'une ligne de facture.
 *
 * Gere la correspondance entre une `VatCategory` et les champs
 * `tax_rate`, `metadata.category` et `metadata.exemption_reason`
 * attendus par l'API Scell.io.
 *
 * @example
 * ```php
 * use Scell\Sdk\Builders\InvoiceLineBuilder;
 * use Scell\Sdk\Enums\VatCategory;
 *
 * // Ligne standard FR (20 %)
 * $line = (new InvoiceLineBuilder())
 *     ->withDescription('Prestation de conseil')
 *     ->withQuantity(1)
 *     ->withUnitPrice(2000.00)
 *     ->build();
 *
 * // Ligne autoliquidation (B2B EU avec numero TVA valide)
 * $line = (new InvoiceLineBuilder())
 *     ->withDescription('Logiciel SaaS')
 *     ->withQuantity(1)
 *     ->withUnitPrice(500.00)
 *     ->withCategory(VatCategory::ReverseCharge)
 *     ->build();
 *
 * // Prestation electronique art. 259 A CGI (lieu = pays client)
 * $line = (new InvoiceLineBuilder())
 *     ->withDescription('Acces plateforme streaming')
 *     ->withQuantity(1)
 *     ->withUnitPrice(99.00)
 *     ->withCategory(VatCategory::Standard)
 *     ->withPlaceOfSupply('DE')
 *     ->build();
 * ```
 */
class InvoiceLineBuilder
{
    private string $description = '';
    private float $quantity = 1.0;
    private float $unitPrice = 0.0;
    private float $taxRate;
    private ?VatCategory $category = null;
    private ?string $placeOfSupply = null;
    private ?string $serviceNature = null;
    private ?array $metadata = null;

    public function __construct()
    {
        // taxRate sera derive de la categorie ou doit etre fourni explicitement
        $this->taxRate = VatCategory::Standard->defaultRate();
    }

    public function withDescription(string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;
        return $clone;
    }

    public function withQuantity(float $quantity): self
    {
        $clone = clone $this;
        $clone->quantity = $quantity;
        return $clone;
    }

    public function withUnitPrice(float $unitPrice): self
    {
        $clone = clone $this;
        $clone->unitPrice = $unitPrice;
        return $clone;
    }

    /**
     * Definit le taux de TVA explicitement (en pourcentage, ex: 20.0).
     *
     * Preferer {@see withCategory()} quand possible — il derive le taux
     * automatiquement et renseigne aussi les champs metadata.
     */
    public function withTaxRate(float $rate): self
    {
        $clone = clone $this;
        $clone->taxRate = $rate;
        return $clone;
    }

    /**
     * Definit la categorie TVA de la ligne.
     *
     * Derive automatiquement :
     * - `tax_rate` depuis `VatCategory::defaultRate()`
     * - `metadata.category` (valeur de l'enum)
     * - `metadata.exemption_reason` si taux nul
     *
     * @param VatCategory $category Categorie TVA resolue (via `BuyerResource::vatContext()` ou manuellement)
     */
    public function withCategory(VatCategory $category): self
    {
        $clone = clone $this;
        $clone->category = $category;
        $clone->taxRate = $category->defaultRate();
        return $clone;
    }

    /**
     * Definit le pays de consommation / lieu de prestation (ISO 3166-1 alpha-2).
     *
     * Utilise pour les prestations electroniques art. 259 A CGI ou les
     * services B2B dont le lieu de prestation est fixe contractuellement.
     * Stocke dans `metadata.place_of_supply`.
     */
    public function withPlaceOfSupply(string $countryIso2): self
    {
        $clone = clone $this;
        $clone->placeOfSupply = strtoupper($countryIso2);
        return $clone;
    }

    /**
     * Definit la nature du service (champ libre, stocke dans metadata).
     *
     * Exemples : "conseil", "logiciel", "formation", "prestation medicale".
     */
    public function withServiceNature(string $nature): self
    {
        $clone = clone $this;
        $clone->serviceNature = $nature;
        return $clone;
    }

    /**
     * Fusionne des metadata supplementaires dans le payload.
     *
     * Les cles `category`, `exemption_reason` et `place_of_supply` derivees
     * des helpers `withCategory()` / `withPlaceOfSupply()` ont la priorite
     * et ecrasent les valeurs passees ici.
     *
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = $metadata;
        return $clone;
    }

    /**
     * Construit le tableau payload de la ligne pret pour l'API.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $totalHt = round($this->quantity * $this->unitPrice, 2);
        $totalTax = round($totalHt * ($this->taxRate / 100), 2);
        $totalTtc = round($totalHt + $totalTax, 2);

        $meta = $this->metadata ?? [];

        if ($this->category !== null) {
            $meta['category'] = $this->category->value;
            $reason = $this->category->exemptionReason();
            if ($reason !== null) {
                $meta['exemption_reason'] = $reason;
            }
        }

        if ($this->placeOfSupply !== null) {
            $meta['place_of_supply'] = $this->placeOfSupply;
        }

        if ($this->serviceNature !== null) {
            $meta['service_nature'] = $this->serviceNature;
        }

        $payload = [
            'description' => $this->description,
            'quantity'    => $this->quantity,
            'unit_price'  => $this->unitPrice,
            'tax_rate'    => $this->taxRate,
            'total_ht'    => $totalHt,
            'total_tax'   => $totalTax,
            'total_ttc'   => $totalTtc,
        ];

        if (!empty($meta)) {
            $payload['metadata'] = $meta;
        }

        return $payload;
    }
}
