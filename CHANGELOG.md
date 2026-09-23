# Changelog

- Integrated the extended feature set directly into the standard plugin: product attributes, attribute types, manual subscriptions, expiration notifications, recurring-payment NVP helpers, and sales statistics.

## 1.7.0 — in development

### Compatibility and stability

- Set the maintained compatibility baseline to Geeklog 2.1.1+ and PHP 5.6+.
- Fixed the PHP 7/8 fatal error caused by `break` outside a loop/switch in the
  manual purchase validation path (Geeklog-Plugins/paypal issue #3).
- Replaced legacy `preg_replace()` expressions using the removed `/e`
  modifier in touched purchase/upgrade paths.
- Fixed jCart item-count expressions that are invalid or unsafe on modern PHP.
- Initialized plugin header output deterministically.
- Added safer global binding for Geeklog 2.2.x plugin loading.

### Install and upgrade safety

- Removed install and upgrade telemetry email.
- Removed automatic public-directory rename/delete operations from the upgrade
  path so shared plugin files are not mutated by one site's database upgrade.
- Resolve Logged-in Users and All Users by group name instead of hard-coded
  numeric IDs.
- Download-log creation no longer aborts installation if the log cannot be
  created.
- Updated static plugin metadata for discovery by Monitor and ecosystem tools.

### Security

- Added Geeklog CSRF tokens to product save/delete operations.
- Replaced manual pending-order validation by a CSRF-protected POST operation.
- Added CSRF enforcement to active PayPal admin AJAX mutations and IPN
  repair/reprocess actions.
- Escaped transaction identifiers used by manual validation and IPN admin paths.
- Replaced the remaining admin `preg_replace /e` serialized-IPN repair path
  with PHP 7/8-compatible callback handling.
- Hardened IPN cart parsing so incomplete item sets are rejected without PHP
  warnings.
- Kept server-side validation of receiver identity, currency, current prices,
  enabled attributes and allowed shipping amounts before fulfillment.

### Interoperability

- Added a provider-owned capability declaration.
- Added permission-aware normalized product Item Info.
- Added product collection retrieval with `since`, `limit` and ordering,
  including `hits-desc`.
- Added product ID-to-URL resolution.
- Added product lifecycle notifications using `PLG_itemSaved()` and
  `PLG_itemDeleted()`.
- Added the bounded, read-only `dashboard_summary` service for Eclipse.
- Kept purchases, IPN payloads, customer details and credentials outside the
  generic Agent/Hub content surface.

### Configuration and storefront

- Audited configured settings and wired previously ineffective menu label,
  menu visibility and block-layout options.
- Corrected anonymous vs authenticated purchase-email behavior and attachment
  handling.
- Added persisted `enable_buy_now` configuration.
- Migrated the misspelled `enable_pay_by_ckeck` key to
  `enable_pay_by_check` while preserving existing values.
- Removed the duplicated inline cart from the product catalog and made
  add-to-cart resilient with and without JavaScript.
- Simplified and normalized the PayPal templates and form heading hierarchy.
- Implemented upstream issue #1 with `[paypal:count]` while preserving the
  historical product autotags.

### Documentation

- Added a modernization roadmap covering remaining security, PHP 8,
  administration, cart, image and payment modernization work.
- Documented the status of upstream issues #1, #2 and #3.

### Upgrade note

Version 1.7.0 is designed as a stabilization release. It does not migrate the
payment model to PayPal REST/Checkout and does not intentionally change the
existing IPN payment semantics.

## PayPal compatibility and security (2026)

- Supports PayPal Sandbox and Live through the maintained legacy IPN and NVP/SOAP endpoints.
- IPN verification uses `ipnpb.sandbox.paypal.com` and `ipnpb.paypal.com` with TLS verification enabled.
- NVP calls use `api-3t.sandbox.paypal.com/nvp` and `api-3t.paypal.com/nvp` with TLS verification and HTTP status checks.
- API credentials can be supplied through `PAYPAL_API_USERNAME`, `PAYPAL_API_PASSWORD`, and `PAYPAL_API_SIGNATURE` environment variables; these take precedence over Geeklog configuration values.
- Server-side IPN validation checks receiver identity, transaction uniqueness, currency, product prices, enabled attributes, and allowed shipping amounts before fulfillment.
- Refund and reversal IPNs revoke related purchases/subscriptions and group access.
- Payment/order tables use InnoDB on new installs and are migrated to InnoDB during the 1.7.0 upgrade.
- PayPal classifies IPN, Website Payments Standard, and NVP/SOAP as legacy integrations. A future major release should migrate checkout to the current PayPal Orders REST API / JavaScript SDK while preserving an upgrade path for existing sites.
