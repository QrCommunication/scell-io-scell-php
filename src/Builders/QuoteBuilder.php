<?php

declare(strict_types=1);

namespace Scell\Sdk\Builders;

use DateTimeInterface;
use Scell\Sdk\DTOs\Address;
use Scell\Sdk\DTOs\Quote;
use Scell\Sdk\DTOs\QuoteLine;
use Scell\Sdk\Resources\QuoteResource;

/**
 * Builder fluent pour creer des devis.
 *
 * @example
 * ```php
 * $quote = $api->quotes()->builder()
 *     ->buyer('12345678901234', 'Client SA', new Address('1 rue Test', '75001', 'Paris'))
 *     ->line('Prestation conseil', 1, 2500.00, 20.0)
 *     ->line('Formation', 2, 800.00, 20.0)
 *     ->validUntil(new DateTime('+30 days'))
 *     ->signatureRequired()
 *     ->notes('Valable 30 jours. Devis soumis a acceptation.')
 *     ->create();
 * ```
 */
class QuoteBuilder
{
    private array $data = [];
    private array $lines = [];

    public function __construct(
        private readonly QuoteResource $resource
    ) {}

    /**
     * Configure l'acheteur (B2B par defaut).
     *
     * @param string|null $siret SIRET de l'acheteur (14 chiffres). Null si B2C ou VAT fourni.
     */
    public function buyer(?string $siret, string $name, Address|array $address): self
    {
        if ($siret !== null) {
            $this->data['buyer_siret'] = $siret;
        }
        $this->data['buyer_name'] = $name;
        $this->data['buyer_address'] = $address instanceof Address ? $address->toArray() : $address;
        return $this;
    }

    /**
     * Reference un acheteur existant du registre par son ID.
     */
    public function buyerId(string $id): self
    {
        $this->data['buyer_id'] = $id;
        return $this;
    }

    /**
     * Marque l'acheteur comme particulier (B2C).
     */
    public function buyerIndividual(bool $value = true): self
    {
        $this->data['buyer_is_individual'] = $value;
        return $this;
    }

    /**
     * Configure l'acheteur comme particulier (B2C).
     *
     * En B2C, SIRET/VAT/legal_id ne sont pas obligatoires.
     */
    public function buyerAsIndividual(string $name, Address|array $address): self
    {
        $this->data['buyer_name'] = $name;
        $this->data['buyer_address'] = $address instanceof Address ? $address->toArray() : $address;
        $this->data['buyer_is_individual'] = true;
        return $this;
    }

    /**
     * Adresse de livraison (optionnelle).
     */
    public function shippingAddress(Address|array $address): self
    {
        $this->data['buyer_shipping_address'] = $address instanceof Address ? $address->toArray() : $address;
        return $this;
    }

    /**
     * Ajoute une ligne au devis.
     */
    public function line(
        string $description,
        float $quantity,
        float $unitPrice,
        float $taxRate = 20.0,
        ?string $unit = null,
        ?string $reference = null,
    ): self {
        $this->lines[] = QuoteLine::create($description, $quantity, $unitPrice, $taxRate, $unit, $reference)->toArray();
        return $this;
    }

    /**
     * Ajoute plusieurs lignes en une fois.
     *
     * @param array[] $lines Tableaux de lignes (description, quantity, unit_price, tax_rate)
     */
    public function lines(array $lines): self
    {
        foreach ($lines as $l) {
            $this->lines[] = $l instanceof QuoteLine ? $l->toArray() : $l;
        }
        return $this;
    }

    /**
     * Ajoute une ligne via DTO.
     */
    public function addLineDto(QuoteLine $line): self
    {
        $this->lines[] = $line->toArray();
        return $this;
    }

    /**
     * Date limite de validite du devis.
     */
    public function validUntil(DateTimeInterface|string $date): self
    {
        $this->data['valid_until'] = $date instanceof DateTimeInterface
            ? $date->format('Y-m-d')
            : $date;
        return $this;
    }

    /**
     * Exige une signature electronique du buyer avant acceptation.
     */
    public function signatureRequired(bool $required = true): self
    {
        $this->data['signature_required'] = $required;
        return $this;
    }

    /**
     * Conditions de paiement (mention libre).
     */
    public function paymentTerms(string $terms): self
    {
        $this->data['payment_terms'] = $terms;
        return $this;
    }

    /**
     * Notes internes ou mentions legales visibles sur le devis.
     */
    public function notes(string $notes): self
    {
        $this->data['notes'] = $notes;
        return $this;
    }

    /**
     * Metadonnees supplementaires (cle-valeur libre).
     */
    public function metadata(array $metadata): self
    {
        $this->data['metadata'] = $metadata;
        return $this;
    }

    /**
     * Scoper le devis a un sub-tenant specifique.
     */
    public function subTenantId(?string $id): self
    {
        $this->data['sub_tenant_id'] = $id;
        return $this;
    }

    /**
     * Scoper le devis a une company specifique.
     */
    public function companyId(string $id): self
    {
        $this->data['company_id'] = $id;
        return $this;
    }

    /**
     * ID externe pour le rapprochement metier.
     */
    public function externalId(string $id): self
    {
        $this->data['external_id'] = $id;
        return $this;
    }

    /**
     * URL de callback : redirige le buyer vers cette URL apres acceptation
     * ou refus du devis, avec query string :
     *   ?status=signed|refused&quote_id=...&quote_number=...&reason=...
     *
     * Permet au tenant de capturer le buyer dans son propre flow metier
     * (page de remerciement custom, dashboard client, automation).
     *
     * @param string $url URL absolue (https://...), max 500 chars
     */
    public function callbackUrl(string $url): self
    {
        $this->data['callback_url'] = $url;
        return $this;
    }

    /**
     * Echeancier d'acomptes associe au devis.
     *
     * @param array[] $schedule Tableau d'echeances [{amount, percent, label, due_date}]
     */
    public function depositSchedule(array $schedule): self
    {
        $this->data['deposit_schedule'] = $schedule;
        return $this;
    }

    /**
     * Devise du devis (defaut : EUR).
     */
    public function currency(string $currency): self
    {
        $this->data['currency'] = $currency;
        return $this;
    }

    /**
     * Definit l'echeancier de paiement du devis.
     *
     * Permet d'associer directement un echeancier lors de la creation du devis.
     * Peut aussi etre defini apres creation via QuotePaymentScheduleResource::set().
     *
     * @param array<int, array{
     *     amount_type: 'percent'|'amount',
     *     amount_value: float,
     *     due_date?: string,
     *     milestone_label?: string,
     *     description?: string,
     *     auto_generate?: bool
     * }> $lines
     *
     * @example
     * ```php
     * $quote = $api->quotes()->builder()
     *     ->buyer('12345678901234', 'Client SA', new Address(...))
     *     ->line('Prestation', 10, 500.00, 20.0)
     *     ->withPaymentSchedule([
     *         ['amount_type' => 'percent', 'amount_value' => 30, 'due_date' => '2026-06-01'],
     *         ['amount_type' => 'percent', 'amount_value' => 70, 'milestone_label' => 'Livraison'],
     *     ])
     *     ->create();
     * ```
     */
    public function withPaymentSchedule(array $lines): self
    {
        $this->data['payment_schedule'] = ['lines' => $lines];
        return $this;
    }

    /**
     * Retourne le payload brut sans appel API.
     */
    public function build(): array
    {
        $payload = $this->data;
        $payload['lines'] = $this->lines;
        return $payload;
    }

    /**
     * Calcule les totaux automatiquement et cree le devis via l'API.
     */
    public function create(): Quote
    {
        return $this->resource->create($this->build());
    }
}
