<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Represente un pack de credits achetable (one-shot prepaid avec bonus).
 *
 * Les packs sont configures cote admin Scell.io et exposes au public via
 * `GET /api/v1/packs/public`. Chaque pack a un prix `amount_eur` (cents) et
 * credite `credits_eur` (cents) sur la balance, avec bonus = credits - amount.
 *
 * Achat via `BillingResource::checkoutPack($slug)` :
 *  - Sandbox : credit direct, pas d'appel Stripe ni Factur-X
 *  - Production : retourne un `client_secret` Stripe a confirmer cote front
 *
 * Exemple de payload backend :
 * ```json
 * {
 *   "id": "019d-uuid",
 *   "slug": "pro",
 *   "name": "Pack Pro",
 *   "description": "Bonus 10%",
 *   "amount_eur": 10000,
 *   "amount_euros": 100.00,
 *   "credits_eur": 11000,
 *   "credits_euros": 110.00,
 *   "bonus_eur": 1000,
 *   "bonus_euros": 10.00,
 *   "bonus_percent": 10.0,
 *   "currency": "EUR",
 *   "position": 2,
 *   "is_recommended": true
 * }
 * ```
 *
 * @since 2.24.0
 */
readonly class CreditPack
{
    public function __construct(
        /** Identifiant unique UUID du pack. */
        public string $id,
        /** Slug stable (`starter`, `pro`, `business`) — utilise pour le checkout. */
        public string $slug,
        /** Nom commercial du pack. */
        public string $name,
        /** Montant facture (en centimes d'euros). */
        public int $amountEur,
        /** Montant facture en EUR (float). */
        public float $amountEuros,
        /** Credits effectifs ajoutes a la balance (en centimes, bonus inclus). */
        public int $creditsEur,
        /** Credits effectifs en EUR (float). */
        public float $creditsEuros,
        /** Bonus offert (centimes) — = creditsEur - amountEur. */
        public int $bonusEur,
        /** Pourcentage de bonus (ex: 10.0 pour +10%). */
        public float $bonusPercent,
        /** Devise (toujours `EUR` aujourd'hui). */
        public string $currency = 'EUR',
        /** Position d'affichage croissante (1, 2, 3, ...). */
        public int $position = 0,
        /** True si le pack est le `recommended` (typiquement `pro`). */
        public bool $isRecommended = false,
        /** Description marketing (peut etre null cote API). */
        public ?string $description = null,
        /** Bonus en EUR (float, si fourni par l'API). */
        public ?float $bonusEuros = null,
    ) {}

    /**
     * Cree une instance a partir de la reponse API.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            slug: (string) $data['slug'],
            name: (string) $data['name'],
            amountEur: (int) $data['amount_eur'],
            amountEuros: (float) ($data['amount_euros'] ?? ($data['amount_eur'] / 100)),
            creditsEur: (int) $data['credits_eur'],
            creditsEuros: (float) ($data['credits_euros'] ?? ($data['credits_eur'] / 100)),
            bonusEur: (int) ($data['bonus_eur'] ?? ($data['credits_eur'] - $data['amount_eur'])),
            bonusPercent: (float) ($data['bonus_percent'] ?? 0.0),
            currency: (string) ($data['currency'] ?? 'EUR'),
            position: (int) ($data['position'] ?? 0),
            isRecommended: (bool) ($data['is_recommended'] ?? false),
            description: isset($data['description']) ? (string) $data['description'] : null,
            bonusEuros: isset($data['bonus_euros']) ? (float) $data['bonus_euros'] : null,
        );
    }

    /**
     * True si le pack offre un bonus (creditsEur > amountEur).
     */
    public function hasBonus(): bool
    {
        return $this->bonusEur > 0;
    }

    /**
     * Bonus en EUR (calcule a partir des centimes si non fourni par l'API).
     */
    public function bonusInEuros(): float
    {
        return $this->bonusEuros ?? round($this->bonusEur / 100, 2);
    }
}
