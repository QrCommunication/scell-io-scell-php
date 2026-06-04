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
 * // Autoliquidation SERVICES intra-UE B2B (numero TVA valide) -> AE, art. 283-2
 * $line = (new InvoiceLineBuilder())
 *     ->withDescription('Logiciel SaaS')
 *     ->withQuantity(1)
 *     ->withUnitPrice(500.00)
 *     ->withCategory(VatCategory::ReverseCharge)
 *     ->withSupplyType('services')
 *     ->build();
 *
 * // Livraison intracommunautaire de BIENS -> K, art. 262 ter
 * $line = (new InvoiceLineBuilder())
 *     ->withDescription('Materiel informatique')
 *     ->withQuantity(10)
 *     ->withUnitPrice(200.00)
 *     ->withCategory(VatCategory::IntracomGoods)
 *     ->withSupplyType('goods')
 *     ->build();
 *
 * // Forcer un taux divergent en l'assumant (evite le 409 VAT_CORRECTION_REQUIRED)
 * $line = (new InvoiceLineBuilder())
 *     ->withDescription('Prestation specifique')
 *     ->withUnitPrice(1000.00)
 *     ->withTaxRate(20.0)
 *     ->withOverrideReason('Client sans numero TVA valide a la date d\'emission')
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
    private ?string $supplyType = null;
    private ?string $placeOfSupply = null;
    private ?string $overrideReason = null;
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
     * Definit la nature de la fourniture : bien (`'goods'`) ou prestation de
     * services (`'services'`).
     *
     * DISCRIMINANT pour l'exoneration intracommunautaire / export resolue par le
     * serveur (champ top-level `supply_type`) :
     *  - BIENS    -> INTRACOM_GOODS (K, art. 262 ter) intra-UE / EXPORT (G, art. 262 I) hors-UE
     *  - SERVICES -> REVERSE_CHARGE (AE, art. 283-2) intra-UE / OUT_OF_SCOPE (O, art. 259-1) hors-UE
     *
     * Sans cette information, le serveur traite la ligne comme un service
     * (cas dominant). A renseigner pour toute vente de biens transfrontaliere.
     *
     * @param string $type 'goods' ou 'services'
     */
    public function withSupplyType(string $type): self
    {
        $clone = clone $this;
        $normalized = strtolower(trim($type));
        $clone->supplyType = in_array($normalized, ['goods', 'services'], true) ? $normalized : null;
        return $clone;
    }

    /**
     * Definit le pays de consommation / lieu de prestation (ISO 3166-1 alpha-2).
     *
     * Utilise pour les prestations art. 259 A CGI ou les services B2B dont le
     * lieu de prestation est fixe contractuellement (immobilier, manifestation
     * sur place, restauration, transport...). Envoye en champ top-level
     * `place_of_supply` (consomme par le resolveur TVA serveur).
     */
    public function withPlaceOfSupply(string $countryIso2): self
    {
        $clone = clone $this;
        $clone->placeOfSupply = strtoupper($countryIso2);
        return $clone;
    }

    /**
     * Assume explicitement un taux/categorie de TVA divergent de la resolution
     * serveur, en fournissant une raison tracable (champ top-level
     * `vat_override_reason`).
     *
     * Sans cette raison, si le taux fourni est incoherent avec le contexte
     * (ex: 20 % sur une vente intra-UE B2B avec numero TVA valide), l'API
     * renvoie un **409 VAT_CORRECTION_REQUIRED** avec la facture corrigee
     * proposee. Renseigner cette raison conserve VOTRE taux et trace le choix
     * pour l'audit fiscal (responsabilite assumee).
     *
     * @param string $reason Justification metier (max 500 caracteres)
     */
    public function withOverrideReason(string $reason): self
    {
        $clone = clone $this;
        $reason = trim($reason);
        $clone->overrideReason = $reason !== '' ? mb_substr($reason, 0, 500) : null;
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

        $payload = [
            'description' => $this->description,
            'quantity'    => $this->quantity,
            'unit_price'  => $this->unitPrice,
            'tax_rate'    => $this->taxRate,
            'total_ht'    => $totalHt,
            'total_tax'   => $totalTax,
            'total_ttc'   => $totalTtc,
        ];

        // Champs de pilotage TVA — TOP-LEVEL (consommes par la resolution
        // autoritaire serveur, qui les lit puis les replie dans metadata.*).
        if ($this->category !== null) {
            $payload['vat_category'] = $this->category->value;
        }
        if ($this->supplyType !== null) {
            $payload['supply_type'] = $this->supplyType;
        }
        if ($this->placeOfSupply !== null) {
            $payload['place_of_supply'] = $this->placeOfSupply;
        }
        if ($this->overrideReason !== null) {
            $payload['vat_override_reason'] = $this->overrideReason;
        }

        // metadata : donnees libres + serviceNature informatif (audit trail).
        $meta = $this->metadata ?? [];
        if ($this->serviceNature !== null) {
            $meta['service_nature'] = $this->serviceNature;
        }
        if (!empty($meta)) {
            $payload['metadata'] = $meta;
        }

        return $payload;
    }
}
