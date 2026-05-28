<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Represente un preset de plan de paiement (modele preconfigure).
 *
 * Retourne par `GET /api/v1/payment-schedule-presets`. Cinq presets natifs
 * (depuis SDK 2.24.0) :
 *
 *  - `full_upfront`           : 100 % a la signature
 *  - `deposit_50_balance_50`  : 50 % / 50 % (delai 30 jours pour le solde)
 *  - `deposit_30_balance_70`  : 30 % / 70 % (delai 30 jours pour le solde)
 *  - `thirds_30_30_40`        : 30 / 30 / 40 % (delais 0 / 30 / 60 jours)
 *  - `quarterly`              : 4 versements egaux de 25 % a 30/60/90/120 jours
 *
 * Les presets sont definis cote backend (en code, pas en DB), donc stables
 * et consultables sans contexte tenant. Une fois choisi, le caller construit
 * un payload pour `POST /quotes/{quote}/payment-schedule` en mappant
 * `percentage -> amount_value` et `due_offset_days -> due_date` (calcule
 * depuis la date d'acceptation).
 *
 * Structure d'une ligne `lines[]` :
 *  - `percentage`      : entier 0-100 (somme = 100 sur toutes les lignes)
 *  - `label`           : libelle de la tranche (FR)
 *  - `due_offset_days` : null = a la signature, 0 = jour de la facture,
 *                        N > 0 = N jours apres signature/facture
 *
 * @since 2.24.0
 */
readonly class PaymentSchedulePreset
{
    /**
     * @param array<int, array{percentage: int, label: string, due_offset_days: int|null}> $lines
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public array $lines,
    ) {}

    /**
     * Cree une instance depuis la reponse API.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) $data['key'],
            label: (string) $data['label'],
            description: (string) $data['description'],
            lines: array_map(
                static fn(array $line): array => [
                    'percentage' => (int) $line['percentage'],
                    'label' => (string) $line['label'],
                    'due_offset_days' => isset($line['due_offset_days'])
                        ? (int) $line['due_offset_days']
                        : null,
                ],
                $data['lines'] ?? [],
            ),
        );
    }

    /**
     * Nombre de tranches (toujours >= 1).
     */
    public function lineCount(): int
    {
        return count($this->lines);
    }

    /**
     * Verifie que la somme des pourcentages fait bien 100.
     *
     * Devrait toujours retourner true (verification cote backend), mais
     * disponible comme garde-fou cote client.
     */
    public function isValid(): bool
    {
        $sum = array_sum(array_column($this->lines, 'percentage'));
        return $sum === 100;
    }

    /**
     * Convertit en payload `lines` pour `POST /quotes/{id}/payment-schedule`.
     *
     * Le pre-fix `amount_type` est `percent` ; les `due_date` doivent etre
     * calculees par le caller en ajoutant `due_offset_days` a la date
     * d'acceptation du devis.
     *
     * @return array<int, array{amount_type: string, amount_value: int, milestone_label: string, due_date_offset_days: int|null}>
     */
    public function toScheduleLines(): array
    {
        return array_map(
            static fn(array $line): array => [
                'amount_type' => 'percent',
                'amount_value' => $line['percentage'],
                'milestone_label' => $line['label'],
                'due_date_offset_days' => $line['due_offset_days'],
            ],
            $this->lines,
        );
    }
}
