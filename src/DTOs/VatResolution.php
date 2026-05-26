<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use Scell\Sdk\Enums\VatCategory;

/**
 * Resolution TVA retournee par `BuyerResource::vatContext()`.
 *
 * Contient la categorie determinee, le taux applicable, le code EN16931
 * pour Factur-X/UBL, ainsi qu'une justification textuelle et la regle
 * d'inference utilisee par le moteur backend.
 */
readonly class VatResolution
{
    public function __construct(
        /** Taux de TVA applicable (ex: 0.0 pour autoliquidation, 20.0 pour standard). */
        public float $rate,

        /** Categorie TVA resolue. */
        public VatCategory $category,

        /** Code EN16931 / UNTDID 5305 (ex: "AE", "S", "O"). */
        public string $en16931Code,

        /** Raison d'exoneration si taux nul (ex: "reverse_charge"). Null si taux positif. */
        public ?string $exemptionReason,

        /** Justification textuelle lisible (ex: "TVA non applicable, art. 259-1 du CGI"). */
        public ?string $justification,

        /** True si le contexte a ete resolu automatiquement par le moteur de regles. */
        public bool $isAutoResolved,

        /** Identifiant de la regle d'inference appliquee (ex: "R2_eu_b2b_vat_valid"). */
        public ?string $rule,
    ) {}

    /**
     * Cree une instance depuis le tableau de reponse API.
     *
     * @param array<string, mixed> $data Tableau `resolution` de la reponse JSON
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rate: (float) ($data['rate'] ?? 0.0),
            category: VatCategory::from($data['category']),
            en16931Code: $data['en16931_code'] ?? 'S',
            exemptionReason: $data['exemption_reason'] ?? null,
            justification: $data['justification'] ?? null,
            isAutoResolved: (bool) ($data['is_auto_resolved'] ?? true),
            rule: $data['rule'] ?? null,
        );
    }

    /**
     * Serialise en tableau (utile pour l'inspection ou les logs).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rate'             => $this->rate,
            'category'         => $this->category->value,
            'en16931_code'     => $this->en16931Code,
            'exemption_reason' => $this->exemptionReason,
            'justification'    => $this->justification,
            'is_auto_resolved' => $this->isAutoResolved,
            'rule'             => $this->rule,
        ];
    }
}
