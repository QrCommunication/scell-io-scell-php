<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Scell\Sdk\Enums\ApiKeyStatus;
use Scell\Sdk\Enums\CompanyStatus;
use Scell\Sdk\Enums\CreditNoteStatus;
use Scell\Sdk\Enums\CreditNoteType;
use Scell\Sdk\Enums\InvoiceArchiveStatus;
use Scell\Sdk\Enums\InvoiceTemplateKind;
use Scell\Sdk\Enums\InvoiceType;
use Scell\Sdk\Enums\OnboardingSessionStatus;
use Scell\Sdk\Enums\PaymentScheduleLineAmountType;
use Scell\Sdk\Enums\PaymentScheduleLineStatus;
use Scell\Sdk\Enums\QuoteAuditAction;
use Scell\Sdk\Enums\QuoteStatus;
use Scell\Sdk\Enums\SignatureArchiveStatus;
use Scell\Sdk\Enums\TenantInvoiceStatus;
use Scell\Sdk\Enums\TenantKybStatus;
use Scell\Sdk\Enums\TenantTransactionType;

/**
 * Couvre les 16 enums ajoutes en SDK 2.21.0 alignes sur le backend Scell.io.
 *
 * Chaque enum est verifie sur :
 *  - la mappabilite via tryFrom() depuis la valeur API
 *  - la presence de toutes les cases attendues
 *  - les helpers metier specifiques (canEdit, isFinal, label, etc.)
 *
 * Les enums deja presents (AuthMethod, Direction, DisputeType, Environment,
 * InvoiceStatus, OnboardingStatus, OutputFormat, RefundStatus, RejectionCode,
 * SignatureStatus, VatCategory, WebhookEvent) ne sont pas re-testes ici.
 */
class EnumsCoverageTest extends TestCase
{
    #[Test]
    public function invoice_template_kind_exposes_three_values(): void
    {
        $this->assertSame(InvoiceTemplateKind::Invoice, InvoiceTemplateKind::from('invoice'));
        $this->assertSame(InvoiceTemplateKind::Quote, InvoiceTemplateKind::from('quote'));
        $this->assertSame(InvoiceTemplateKind::Both, InvoiceTemplateKind::from('both'));

        $this->assertTrue(InvoiceTemplateKind::Both->matches('invoice'));
        $this->assertTrue(InvoiceTemplateKind::Both->matches('quote'));
        $this->assertTrue(InvoiceTemplateKind::Invoice->matches('invoice'));
        $this->assertFalse(InvoiceTemplateKind::Invoice->matches('quote'));

        $this->assertNotEmpty(InvoiceTemplateKind::Invoice->label());
    }

    #[Test]
    public function invoice_type_factur_x_codes_match_backend(): void
    {
        $this->assertSame(InvoiceType::Standard, InvoiceType::from('standard'));
        $this->assertSame(InvoiceType::Deposit, InvoiceType::from('deposit'));
        $this->assertSame(InvoiceType::Balance, InvoiceType::from('balance'));

        $this->assertSame('380', InvoiceType::Standard->facturXTypeCode());
        $this->assertSame('386', InvoiceType::Deposit->facturXTypeCode());
        $this->assertSame('380', InvoiceType::Balance->facturXTypeCode());

        $this->assertTrue(InvoiceType::Deposit->isDepositOrBalance());
        $this->assertTrue(InvoiceType::Balance->isDepositOrBalance());
        $this->assertFalse(InvoiceType::Standard->isDepositOrBalance());

        $this->assertTrue(InvoiceType::Deposit->omitLatePaymentMentions());
        $this->assertFalse(InvoiceType::Standard->omitLatePaymentMentions());
        $this->assertFalse(InvoiceType::Balance->omitLatePaymentMentions());
    }

    #[Test]
    public function payment_schedule_line_amount_type_computes_ttc(): void
    {
        $this->assertSame(PaymentScheduleLineAmountType::Percent, PaymentScheduleLineAmountType::from('percent'));
        $this->assertSame(PaymentScheduleLineAmountType::Amount, PaymentScheduleLineAmountType::from('amount'));

        // 30% de 1000 EUR = 300 EUR
        $this->assertSame(300.0, PaymentScheduleLineAmountType::Percent->computeTtc(30.0, 1000.0));
        // Montant fixe : 250 EUR
        $this->assertSame(250.0, PaymentScheduleLineAmountType::Amount->computeTtc(250.0, 1000.0));

        $this->assertSame('%', PaymentScheduleLineAmountType::Percent->unit());
    }

    #[Test]
    public function payment_schedule_line_status_lifecycle(): void
    {
        $this->assertSame(PaymentScheduleLineStatus::Pending, PaymentScheduleLineStatus::from('pending'));
        $this->assertSame(PaymentScheduleLineStatus::Invoiced, PaymentScheduleLineStatus::from('invoiced'));
        $this->assertSame(PaymentScheduleLineStatus::Cancelled, PaymentScheduleLineStatus::from('cancelled'));

        $this->assertTrue(PaymentScheduleLineStatus::Pending->canInvoice());
        $this->assertFalse(PaymentScheduleLineStatus::Invoiced->canInvoice());
        $this->assertFalse(PaymentScheduleLineStatus::Cancelled->canInvoice());

        $this->assertTrue(PaymentScheduleLineStatus::Cancelled->isTerminal());
        $this->assertFalse(PaymentScheduleLineStatus::Pending->isTerminal());
    }

    #[Test]
    public function quote_audit_action_covers_all_backend_cases(): void
    {
        $expected = [
            'created', 'updated', 'line_added', 'line_removed', 'line_updated',
            'buyer_changed', 'sent', 'resent', 'viewed', 'signed', 'accepted',
            'refused', 'cancelled', 'expired', 'converted', 'public_link_regenerated',
            'public_link_revoked', 'duplicated', 'deposit_generated_from_schedule',
            'schedule_updated', 'schedule_deleted',
        ];

        foreach ($expected as $value) {
            $action = QuoteAuditAction::tryFrom($value);
            $this->assertNotNull($action, "QuoteAuditAction missing case for '{$value}'");
            $this->assertNotEmpty($action->label());
        }

        $this->assertFalse(QuoteAuditAction::Viewed->isMutation());
        $this->assertTrue(QuoteAuditAction::Updated->isMutation());

        $this->assertTrue(QuoteAuditAction::Accepted->isStateTransition());
        $this->assertTrue(QuoteAuditAction::LineAdded->isModification());
    }

    #[Test]
    public function quote_status_lifecycle_helpers(): void
    {
        $values = ['draft', 'sent', 'viewed', 'accepted', 'refused', 'expired', 'converted', 'cancelled'];
        foreach ($values as $v) {
            $this->assertNotNull(QuoteStatus::tryFrom($v), "QuoteStatus missing case for '{$v}'");
        }

        $this->assertTrue(QuoteStatus::Draft->canEdit());
        $this->assertFalse(QuoteStatus::Sent->canEdit());

        $this->assertTrue(QuoteStatus::Accepted->canConvert());
        $this->assertFalse(QuoteStatus::Draft->canConvert());

        $this->assertTrue(QuoteStatus::Sent->canCancel());
        $this->assertFalse(QuoteStatus::Converted->canCancel());

        $this->assertTrue(QuoteStatus::Converted->isTerminal());
        $this->assertFalse(QuoteStatus::Sent->isTerminal());
    }

    #[Test]
    public function credit_note_status_and_type(): void
    {
        $this->assertSame(CreditNoteStatus::Draft, CreditNoteStatus::from('draft'));
        $this->assertSame(CreditNoteStatus::Sent, CreditNoteStatus::from('sent'));
        $this->assertTrue(CreditNoteStatus::Draft->canEdit());
        $this->assertFalse(CreditNoteStatus::Sent->canEdit());

        $this->assertSame(CreditNoteType::Partial, CreditNoteType::from('partial'));
        $this->assertSame(CreditNoteType::Total, CreditNoteType::from('total'));
        $this->assertTrue(CreditNoteType::Total->isTotal());
        $this->assertFalse(CreditNoteType::Partial->isTotal());
    }

    #[Test]
    public function archive_statuses_match(): void
    {
        $values = ['pending', 'archived', 'glacier', 'error'];
        foreach ($values as $v) {
            $this->assertNotNull(SignatureArchiveStatus::tryFrom($v));
            $this->assertNotNull(InvoiceArchiveStatus::tryFrom($v));
        }

        $this->assertTrue(SignatureArchiveStatus::Archived->isArchived());
        $this->assertTrue(SignatureArchiveStatus::Glacier->isArchived());
        $this->assertFalse(SignatureArchiveStatus::Pending->isArchived());
        $this->assertFalse(SignatureArchiveStatus::Error->isArchived());

        $this->assertTrue(InvoiceArchiveStatus::Archived->isArchived());
        $this->assertFalse(InvoiceArchiveStatus::Error->isArchived());
    }

    #[Test]
    public function tenant_kyb_status_lifecycle(): void
    {
        $values = ['pending', 'documents_submitted', 'under_review', 'verified', 'rejected'];
        foreach ($values as $v) {
            $this->assertNotNull(TenantKybStatus::tryFrom($v));
        }

        $this->assertTrue(TenantKybStatus::Verified->canIssueInvoices());
        $this->assertFalse(TenantKybStatus::Pending->canIssueInvoices());
        $this->assertTrue(TenantKybStatus::Verified->isFinal());
        $this->assertTrue(TenantKybStatus::Rejected->isFinal());
        $this->assertFalse(TenantKybStatus::UnderReview->isFinal());
    }

    #[Test]
    public function company_status(): void
    {
        $this->assertSame(CompanyStatus::PendingKyc, CompanyStatus::from('pending_kyc'));
        $this->assertSame(CompanyStatus::Active, CompanyStatus::from('active'));
        $this->assertSame(CompanyStatus::Suspended, CompanyStatus::from('suspended'));

        $this->assertTrue(CompanyStatus::Active->canIssueInvoices());
        $this->assertFalse(CompanyStatus::PendingKyc->canIssueInvoices());
        $this->assertFalse(CompanyStatus::Suspended->canIssueInvoices());
    }

    #[Test]
    public function api_key_status(): void
    {
        $this->assertSame(ApiKeyStatus::Active, ApiKeyStatus::from('active'));
        $this->assertSame(ApiKeyStatus::Revoked, ApiKeyStatus::from('revoked'));

        $this->assertTrue(ApiKeyStatus::Active->isUsable());
        $this->assertFalse(ApiKeyStatus::Revoked->isUsable());
    }

    #[Test]
    public function tenant_invoice_status_payable(): void
    {
        $values = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];
        foreach ($values as $v) {
            $this->assertNotNull(TenantInvoiceStatus::tryFrom($v));
        }

        $this->assertTrue(TenantInvoiceStatus::Sent->isPayable());
        $this->assertTrue(TenantInvoiceStatus::Overdue->isPayable());
        $this->assertFalse(TenantInvoiceStatus::Draft->isPayable());
        $this->assertFalse(TenantInvoiceStatus::Paid->isPayable());

        $this->assertTrue(TenantInvoiceStatus::Paid->isFinal());
        $this->assertTrue(TenantInvoiceStatus::Cancelled->isFinal());
        $this->assertFalse(TenantInvoiceStatus::Overdue->isFinal());
    }

    #[Test]
    public function tenant_transaction_type_signs(): void
    {
        $this->assertSame(TenantTransactionType::Debit, TenantTransactionType::from('debit'));
        $this->assertSame(TenantTransactionType::Credit, TenantTransactionType::from('credit'));

        $this->assertSame(1, TenantTransactionType::Credit->sign());
        $this->assertSame(-1, TenantTransactionType::Debit->sign());
    }

    #[Test]
    public function onboarding_session_status_covers_all_steps(): void
    {
        $values = [
            'initiated', 'siret_verified', 'vat_verified', 'documents_pending',
            'documents_submitted', 'under_review', 'completed', 'failed', 'expired',
        ];
        foreach ($values as $v) {
            $this->assertNotNull(OnboardingSessionStatus::tryFrom($v), "Missing case '{$v}'");
        }

        $this->assertTrue(OnboardingSessionStatus::Completed->isFinal());
        $this->assertTrue(OnboardingSessionStatus::Failed->isFinal());
        $this->assertTrue(OnboardingSessionStatus::Expired->isFinal());
        $this->assertFalse(OnboardingSessionStatus::Initiated->isFinal());

        $this->assertTrue(OnboardingSessionStatus::Initiated->isActive());
        $this->assertFalse(OnboardingSessionStatus::Completed->isActive());
    }
}
