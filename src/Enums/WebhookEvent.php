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
    case InvoiceIncomingValidated = 'invoice.incoming.validated';
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

    // Evenements onboarding
    case OnboardingStarted = 'onboarding.started';
    case OnboardingStepCompleted = 'onboarding.step_completed';
    case OnboardingCompleted = 'onboarding.completed';
    case OnboardingFailed = 'onboarding.failed';

    // Evenements factures recurrentes
    case RecurringInvoiceUpcoming = 'recurring_invoice.upcoming';
    case RecurringInvoiceEmitted = 'recurring_invoice.emitted';
    case RecurringInvoiceCompleted = 'recurring_invoice.completed';
    case RecurringInvoiceFailed = 'recurring_invoice.failed';

    // Evenements seuils sous-tenant
    case SubtenantThresholdWarning = 'subtenant.threshold.warning';
    case SubtenantThresholdVatBaseExceeded = 'subtenant.threshold.vat_base_exceeded';
    case SubtenantThresholdVatMajoredExceeded = 'subtenant.threshold.vat_majored_exceeded';
    case SubtenantThresholdMicroExceeded = 'subtenant.threshold.micro_exceeded';

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
            self::InvoiceIncomingValidated => 'Facture entrante validee',
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
            self::OnboardingStarted => 'Onboarding demarre',
            self::OnboardingStepCompleted => 'Etape d\'onboarding terminee',
            self::OnboardingCompleted => 'Onboarding termine',
            self::OnboardingFailed => 'Echec de l\'onboarding',
            self::RecurringInvoiceUpcoming => 'Facture recurrente a venir',
            self::RecurringInvoiceEmitted => 'Facture recurrente emise',
            self::RecurringInvoiceCompleted => 'Facture recurrente terminee',
            self::RecurringInvoiceFailed => 'Echec de la facture recurrente',
            self::SubtenantThresholdWarning => 'Seuil sous-tenant : alerte',
            self::SubtenantThresholdVatBaseExceeded => 'Seuil sous-tenant : franchise TVA depassee',
            self::SubtenantThresholdVatMajoredExceeded => 'Seuil sous-tenant : franchise TVA majoree depassee',
            self::SubtenantThresholdMicroExceeded => 'Seuil sous-tenant : plafond micro depasse',
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
            str_starts_with($this->value, 'onboarding.') => 'onboarding',
            str_starts_with($this->value, 'recurring_invoice.') => 'recurring_invoice',
            str_starts_with($this->value, 'subtenant.') => 'subtenant',
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
