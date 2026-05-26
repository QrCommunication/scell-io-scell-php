<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use Scell\Sdk\Enums\VatCategory;

/**
 * Contexte d'une ligne de facture pour la resolution TVA.
 *
 * Represente les parametres d'une ligne transmis a `BuyerResource::vatContext()`
 * afin de determiner la categorie TVA applicable. Tous les champs sont
 * optionnels — le backend applique `STANDARD` par defaut.
 */
readonly class LineVatContext
{
    public function __construct(
        /**
         * Categorie TVA demandee pour la ligne.
         * Defaut : STANDARD si non fourni.
         */
        public ?VatCategory $category = null,

        /**
         * Pays de consommation / lieu de prestation (ISO 3166-1 alpha-2).
         * Permet de forcer l'application de l'art. 259 A CGI (prestation
         * electronique / numerique liee au lieu de consommation du client).
         * Laisser null pour que le moteur utilise la localisation buyer.
         */
        public ?string $placeOfSupply = null,

        /**
         * Nature du service (champ libre informatif).
         * Exemple : "conseil", "logiciel", "formation", "prestation medicale".
         * Non utilise par le moteur de regles actuel, conserve pour audit.
         */
        public ?string $serviceNature = null,
    ) {}

    /**
     * Cree une instance depuis un tableau.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            category: isset($data['category']) ? VatCategory::from($data['category']) : null,
            placeOfSupply: $data['place_of_supply'] ?? null,
            serviceNature: $data['service_nature'] ?? null,
        );
    }

    /**
     * Serialise en tableau pour l'API (exclut les valeurs null).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [];
        if ($this->category !== null) {
            $out['category'] = $this->category->value;
        }
        if ($this->placeOfSupply !== null) {
            $out['place_of_supply'] = $this->placeOfSupply;
        }
        if ($this->serviceNature !== null) {
            $out['service_nature'] = $this->serviceNature;
        }
        return $out;
    }
}
