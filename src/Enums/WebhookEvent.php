<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Evenements disponibles pour les webhooks.
 */
enum WebhookEvent: string
{
    // Evenements factures sortantes
    case InvoiceCreated = 'invoice.created';
    case InvoiceValidated = 'invoice.validated';
    case InvoiceTransmitted = 'invoice.transmitted';
    case InvoiceAccepted = 'invoice.accepted';
    case InvoiceRejected = 'invoice.rejected';
    case InvoiceError = 'invoice.error';

    // Evenements factures entrantes
    case InvoiceIncomingReceived = 'invoice.incoming.received';
    case InvoiceIncomingAccepted = 'invoice.incoming.accepted';
    case InvoiceIncomingRejected = 'invoice.incoming.rejected';
    case InvoiceIncomingDisputed = 'invoice.incoming.disputed';
    case InvoiceIncomingPaid = 'invoice.incoming.paid';

    // Evenements signatures
    case SignatureCreated = 'signature.created';
    case SignatureWaiting = 'signature.waiting';
    case SignatureSigned = 'signature.signed';
    case SignatureCompleted = 'signature.completed';
    case SignatureRefused = 'signature.refused';
    case SignatureExpired = 'signature.expired';
    case SignatureError = 'signature.error';

    // Evenements solde
    case BalanceLow = 'balance.low';
    case BalanceCritical = 'balance.critical';

    /**
     * Retourne le libelle en francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::InvoiceCreated => 'Facture creee',
            self::InvoiceValidated => 'Facture validee',
            self::InvoiceTransmitted => 'Facture transmise',
            self::InvoiceAccepted => 'Facture acceptee',
            self::InvoiceRejected => 'Facture refusee',
            self::InvoiceError => 'Erreur facture',
            self::InvoiceIncomingReceived => 'Facture entrante recue',
            self::InvoiceIncomingAccepted => 'Facture entrante acceptee',
            self::InvoiceIncomingRejected => 'Facture entrante rejetee',
            self::InvoiceIncomingDisputed => 'Facture entrante contestee',
            self::InvoiceIncomingPaid => 'Facture entrante payee',
            self::SignatureCreated => 'Signature creee',
            self::SignatureWaiting => 'Signature en attente',
            self::SignatureSigned => 'Document signe',
            self::SignatureCompleted => 'Signature terminee',
            self::SignatureRefused => 'Signature refusee',
            self::SignatureExpired => 'Signature expiree',
            self::SignatureError => 'Erreur signature',
            self::BalanceLow => 'Solde bas',
            self::BalanceCritical => 'Solde critique',
        };
    }

    /**
     * Retourne le domaine de l'evenement.
     */
    public function domain(): string
    {
        return match (true) {
            str_starts_with($this->value, 'invoice.') => 'invoice',
            str_starts_with($this->value, 'signature.') => 'signature',
            str_starts_with($this->value, 'balance.') => 'balance',
        };
    }

    /**
     * Retourne tous les evenements d'un domaine.
     *
     * @return WebhookEvent[]
     */
    public static function forDomain(string $domain): array
    {
        return array_filter(
            self::cases(),
            fn(self $event) => $event->domain() === $domain
        );
    }

    /**
     * Retourne tous les evenements sous forme de tableau.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn(self $event) => $event->value, self::cases());
    }
}
