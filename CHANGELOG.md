# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
