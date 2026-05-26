<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs\Vat;

/**
 * Contexte acheteur inline pour la resolution TVA.
 *
 * Utilise dans `BuyerResource::vatContext()` quand aucun `buyer_id` n'est
 * fourni : permet de passer directement les caracteristiques fiscales du
 * client sans qu'il soit enregistre dans le registre Scell.io.
 *
 * **Distinct du DTO `Scell\Sdk\DTOs\Buyer`** qui represente un acheteur
 * enregistre avec toute son identite (adresse, SIRET, etc.). Ce DTO ne
 * porte que les champs TVA pertinents pour la resolution.
 */
readonly class BuyerContext
{
    public function __construct(
        /**
         * Pays de l'acheteur (ISO 3166-1 alpha-2, ex: "FR", "DE", "US").
         * Utilise pour determiner le flux TVA intracommunautaire / export.
         */
        public string $country,

        /**
         * True si l'acheteur est un particulier (B2C).
         * Impacte le regime TVA applicable (OSS vs autoliquidation).
         */
        public bool $isIndividual = false,

        /**
         * Numero de TVA intracommunautaire de l'acheteur.
         * Requis pour le regime autoliquidation EU B2B.
         */
        public ?string $vatNumber = null,

        /**
         * True si le numero TVA a ete verifie (VIES ou regex).
         * Quand non fourni, le serveur applique une validation regex.
         * Un numero verifie est requis pour qualifier le regime
         * autoliquidation (REVERSE_CHARGE).
         */
        public ?bool $vatNumberValid = null,
    ) {}

    /**
     * Cree une instance depuis un tableau.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            country: $data['country'],
            isIndividual: (bool) ($data['is_individual'] ?? false),
            vatNumber: $data['vat_number'] ?? null,
            vatNumberValid: isset($data['vat_number_valid']) ? (bool) $data['vat_number_valid'] : null,
        );
    }

    /**
     * Serialise en tableau pour l'API (exclut les valeurs null).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'country'      => $this->country,
            'is_individual' => $this->isIndividual,
        ];
        if ($this->vatNumber !== null) {
            $out['vat_number'] = $this->vatNumber;
        }
        if ($this->vatNumberValid !== null) {
            $out['vat_number_valid'] = $this->vatNumberValid;
        }
        return $out;
    }
}
