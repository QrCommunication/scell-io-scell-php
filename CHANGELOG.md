# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2026-02-08

### Added

- **Fiscal Compliance** (LF 2026): `FiscalResource` with 22 methods covering compliance dashboard, integrity checks, closings, FEC export, attestation, ledger entries, kill switch, anchors, rules, and forensic export
- **Billing**: `BillingResource` with invoices, usage, top-up, and transactions
- **Stats**: `StatsResource` with overview, monthly, and sub-tenant overview
- **API Keys**: `ApiKeyResource` with CRUD operations
- **Bulk Operations**: `bulkCreate()`, `bulkSubmit()`, `bulkStatus()` on `TenantDirectInvoiceResource`
- New DTOs: `FiscalCompliance`, `FiscalIntegrityReport`, `FiscalClosingSummary`, `FiscalEntry`, `FiscalKillSwitchStatus`, `FiscalRule`, `FiscalAnchor`, `FiscalAttestation`, `BillingInvoice`, `BillingUsage`, `BillingTransaction`, `StatsOverview`, `StatsMonthly`, `ApiKey`

## [1.2.0] - 2026-01-24

### Added

- **Mark Paid Support**: Mark incoming invoices as paid (mandatory status in French e-invoicing lifecycle)
  - `$client->invoices()->markPaid($id, $data)` - Mark invoice as paid with optional payment reference

- **Download Invoice Files**: Download original invoice files as binary content
  - `$client->invoices()->downloadContent($id)` - Download PDF (Factur-X)
  - `$client->invoices()->downloadContent($id, 'xml')` - Download XML (UBL/CII)

- **New Invoice Fields**:
  - `paid_at` - Payment timestamp
  - `payment_reference` - Bank transfer ID, check number, etc.
  - `payment_note` - Optional payment note

- **New Invoice Status**:
  - `InvoiceStatus::Paid` - Invoice has been marked as paid

- **New Webhook Event**:
  - `invoice.incoming.paid` - Triggered when incoming invoice is marked as paid

- **New Helper Methods**:
  - `Invoice::isPaid()` - Check if invoice has been paid
  - `Invoice::isIncoming()` - Check if invoice is from a supplier

- **HttpClient Enhancement**:
  - `getRaw()` method for downloading binary content (PDF, XML files)

## [1.1.0] - 2026-01-24

### Added
- Incoming invoices support (supplier invoices)
  - `incoming()` - List incoming invoices with filtering
  - `accept()` - Accept an incoming invoice
  - `reject()` - Reject an incoming invoice with reason code
  - `dispute()` - Dispute an incoming invoice
- New enums for incoming invoice workflows
  - `RejectionCode` - Rejection codes (incorrect_amount, duplicate, unknown_order, incorrect_vat, other)
  - `DisputeType` - Dispute types (amount_dispute, quality_dispute, delivery_dispute, other)
- New webhook events for incoming invoices
  - `invoice.incoming.received` - Incoming invoice received
  - `invoice.incoming.accepted` - Incoming invoice accepted
  - `invoice.incoming.rejected` - Incoming invoice rejected
  - `invoice.incoming.disputed` - Incoming invoice disputed

## [1.0.0] - 2026-01-24

### Added
- Initial release of Scell.io PHP SDK
- Support for electronic invoicing (Factur-X, UBL, CII)
- Support for electronic signatures (eIDAS EU-SES)
- Laravel 11/12 integration with auto-discovery
- Fluent builders for invoices and signatures
- Webhook verification with HMAC-SHA256
- Retry middleware with exponential backoff
- Comprehensive DTOs and Enums
- Full error handling with typed exceptions

### Features
- `ScellClient` for Bearer token authentication (dashboard)
- `ScellApiClient` for API Key authentication (server-to-server)
- Resources: Invoices, Signatures, Companies, Balance, Webhooks
- Laravel Facades: `Scell`, `ScellApi`, `ScellWebhook`

### Requirements
- PHP 8.2+
- Guzzle 7.0+
- Laravel 11/12 (optional)
