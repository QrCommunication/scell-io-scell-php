<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\CountryReference;
use Scell\Sdk\Http\HttpClient;

/**
 * Référentiel sociétés par pays — accès authentifié (Sanctum ou clé API sk_/pk_).
 *
 * Expose, par pays, le numéro de TVA, l'identifiant national d'entreprise
 * (registre + format) et les formes juridiques connues, pour adapter
 * dynamiquement un formulaire de saisie acheteur/vendeur au pays sélectionné.
 *
 * @since 2.29.0
 */
class ReferenceResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Tous les pays catalogués.
     *
     * @return list<CountryReference>
     */
    public function countries(): array
    {
        $response = $this->http->get('reference/countries');
        /** @var list<array<string, mixed>> $rows */
        $rows = $response['data'] ?? [];

        return array_map(
            static fn (array $row): CountryReference => CountryReference::fromArray($row),
            $rows,
        );
    }

    /**
     * Référentiel d'un pays par code ISO 3166-1 alpha-2 (ex: FR, DE, BE).
     *
     * Un pays non catalogué renvoie `known=false` et un format permissif
     * (national_id.regex=null) : le client doit alors accepter la saisie libre.
     */
    public function country(string $code): CountryReference
    {
        $response = $this->http->get('reference/countries/'.strtoupper($code));

        return CountryReference::fromArray($response['data']);
    }
}
