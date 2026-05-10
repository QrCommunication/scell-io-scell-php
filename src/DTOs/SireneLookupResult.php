<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Reponse de `OnboardingResource::lookupSirene()`.
 *
 * Trois cas possibles :
 *
 * 1. **Sirene a trouve la societe** :
 *    - `$data` rempli (CompanyData complete avec name, address, etc.)
 *    - `$sireneLookupSucceeded = true`
 *    - `$manualEntryRequired = false`
 *    - `$code = null`
 *
 * 2. **Sirene + INSEE indisponibles (manual_entry fallback)** :
 *    - `$data` partiel (siret + siren + vat_number + country uniquement,
 *      `name` et adresse vides)
 *    - `$sireneLookupSucceeded = false`
 *    - `$manualEntryRequired = true`
 *    - `$code = 'SIRENE_MANUAL_ENTRY_REQUIRED'`
 *
 * 3. **SIRET introuvable (les 2 providers ont repondu mais sans match)** :
 *    - `$data = null`
 *    - `$sireneLookupSucceeded = true`
 *    - `$manualEntryRequired = false`
 *    - `$code = 'SIRENE_NOT_FOUND'` (HTTP 404)
 *
 * @see https://api.scell.io/api/v1/widget/onboarding/sirene/lookup
 */
readonly class SireneLookupResult
{
    public function __construct(
        public ?CompanyData $data,
        public bool $sireneLookupSucceeded,
        public bool $manualEntryRequired = false,
        public ?string $code = null,
    ) {}

    /**
     * Construit le DTO a partir du JSON renvoye par l'API.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $code = isset($payload['code']) ? (string) $payload['code'] : null;
        $rawData = is_array($payload['data'] ?? null) ? $payload['data'] : null;

        // L'API utilise `data.sirene_lookup_failed` (negation) — on l'inverse
        // pour exposer un flag positif `sireneLookupSucceeded` cote SDK.
        $sireneFailed = (bool) ($rawData['sirene_lookup_failed'] ?? false);
        $manualEntryRequired = $sireneFailed || $code === 'SIRENE_MANUAL_ENTRY_REQUIRED';

        $companyData = null;
        if ($rawData !== null && ! $sireneFailed) {
            // Cas 1 : payload complet -> hydrate CompanyData
            $companyData = CompanyData::fromArray($rawData);
        }

        return new self(
            data: $companyData,
            sireneLookupSucceeded: ! $manualEntryRequired,
            manualEntryRequired: $manualEntryRequired,
            code: $code,
        );
    }
}
