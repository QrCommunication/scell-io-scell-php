<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Référentiel sociétés d'un pays, servi par
 * `GET /api/v1/reference/countries[/{code}]` (endpoint public, sans auth).
 *
 * Permet de construire dynamiquement un formulaire de saisie acheteur/vendeur
 * adapté au pays : libellé + exemple + regex de format de l'identifiant
 * national et de la TVA, et liste des formes juridiques connues.
 *
 * Les `regex` sont des motifs ancrés JS-compatibles (utilisables via
 * `new RegExp(regex)` ou `preg_match("#{$regex}#", ...)`), `null` si le format
 * n'est pas vérifié pour ce pays. `null` sur `national_id.regex` ⇒ saisie libre.
 *
 * Exemple de payload backend :
 * ```json
 * {
 *   "code": "FR",
 *   "name": "France",
 *   "known": true,
 *   "is_eu": true,
 *   "currency": "EUR",
 *   "vat": {
 *     "label": "Numéro de TVA intracommunautaire",
 *     "example": "FR12345678901",
 *     "regex": "^FR[A-Z0-9]{2}\\d{9}$",
 *     "vies_checkable": true
 *   },
 *   "national_id": {
 *     "label": "SIREN / SIRET",
 *     "scheme": "0002",
 *     "example": "12345678901234",
 *     "regex": "^(\\d{9}|\\d{14})$",
 *     "required_for_b2b": true
 *   },
 *   "legal_forms": [
 *     { "code": "SAS", "label": "SAS — Société par actions simplifiée" }
 *   ]
 * }
 * ```
 *
 * @since 2.29.0
 */
readonly class CountryReference
{
    /**
     * @param  array{label: string, example: ?string, regex: ?string, vies_checkable: bool}  $vat
     * @param  array{label: string, scheme: ?string, example: ?string, regex: ?string, required_for_b2b: bool}  $nationalId
     * @param  list<LegalForm>  $legalForms
     */
    public function __construct(
        public string $code,
        public ?string $name,
        public bool $known,
        public bool $isEu,
        public ?string $currency,
        public array $vat,
        public array $nationalId,
        public array $legalForms,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array{label?: string, example?: ?string, regex?: ?string, vies_checkable?: bool} $vat */
        $vat = $data['vat'] ?? [];
        /** @var array{label?: string, scheme?: ?string, example?: ?string, regex?: ?string, required_for_b2b?: bool} $nationalId */
        $nationalId = $data['national_id'] ?? [];
        /** @var list<array{code: string, label: string}> $forms */
        $forms = $data['legal_forms'] ?? [];

        return new self(
            code: (string) ($data['code'] ?? ''),
            name: $data['name'] ?? null,
            known: (bool) ($data['known'] ?? false),
            isEu: (bool) ($data['is_eu'] ?? false),
            currency: $data['currency'] ?? null,
            vat: [
                'label' => (string) ($vat['label'] ?? ''),
                'example' => $vat['example'] ?? null,
                'regex' => $vat['regex'] ?? null,
                'vies_checkable' => (bool) ($vat['vies_checkable'] ?? false),
            ],
            nationalId: [
                'label' => (string) ($nationalId['label'] ?? ''),
                'scheme' => $nationalId['scheme'] ?? null,
                'example' => $nationalId['example'] ?? null,
                'regex' => $nationalId['regex'] ?? null,
                'required_for_b2b' => (bool) ($nationalId['required_for_b2b'] ?? false),
            ],
            legalForms: array_map(
                static fn (array $f): LegalForm => LegalForm::fromArray($f),
                $forms,
            ),
        );
    }
}
