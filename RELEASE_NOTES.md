# PayPal 1.7.0 release notes

- Integrated the extended feature set directly into the standard plugin: product attributes, attribute types, manual subscriptions, expiration notifications, recurring-payment NVP helpers, and sales statistics.

PayPal 1.7.0 is a stabilization and interoperability release for the Geeklog
PayPal plugin.

The release focuses on making the existing catalog and payment integration safer
to operate on the current Geeklog transition baseline while preserving the
legacy payment flow.

## Highlights

- Geeklog 2.1.1+ / PHP 5.6+ maintained baseline.
- Fix for the PHP 7/8 fatal error tracked as issue #3.
- Removal of legacy `preg_replace /e` execution from the touched transaction
  handling paths.
- Safer upgrades for shared-file/multisite installations.
- No install/upgrade telemetry email.
- CSRF protection for product mutations and manual pending-order validation.
- Product interoperability for Agent and Hub through normalized Item Info,
  collections, URL resolution and lifecycle events.
- Generic Eclipse administration integration through
  `dashboard.summary`.
- Dedicated cart/checkout flow: the catalog no longer embeds a full cart,
  add-to-cart works with AJAX and has a non-JavaScript fallback to checkout.
- Configuration audit completed for menu visibility/label, block layout,
  anonymous/user purchase emails and Buy Now.
- Historical `enable_pay_by_ckeck` configuration is migrated to
  `enable_pay_by_check`.
- IPN administration no longer relies on `preg_replace /e`, includes CSRF
  protection for repair/reprocess actions, and safely handles incomplete
  serialized payloads.
- IPN processing now rejects incomplete cart notifications cleanly and validates
  configured receiver, currency, current product prices, enabled attributes and
  allowed shipping before fulfillment.
- Legacy transaction/history views now guard optional IPN fields to avoid PHP 8
  warnings.
- Implements upstream issue #1 with `[paypal:count]`, which renders the current
  visitor cart item count while preserving the historical `[paypal:id]` and
  `[paypal_product:id]` autotags.
- Technical admin lists no longer apply content permission SQL to tables that
  do not contain Geeklog permission columns.

## Interoperability contract

PayPal 1.7.0 exposes its public, permission-aware product catalog as content.
Consumers do not need to query PayPal tables directly.

Advertised capabilities:

- `content.read`
- `content.collection`
- `content.search`
- `content.popular`
- `content.url.resolve`
- `content.lifecycle`
- `dashboard.summary`

The dashboard service is restricted to users with `paypal.admin`.

Payment transactions, IPN payloads, customer records and payment credentials are
intentionally not exposed through the generic content contract.

## Upgrade behavior

The database plugin version is updated normally. Existing
`enable_pay_by_ckeck` values are migrated to `enable_pay_by_check`, and
`enable_buy_now` is persisted for existing installations. A runtime-safe
migration also repairs pre-release installations already marked 1.7.0.

1.7.0 no longer renames or deletes the plugin's shared public directory during
an individual site upgrade.
This is important for installations where several Geeklog sites share one plugin
codebase.

## Known modernization work

The legacy plugin still contains areas that should be modernized in follow-up
work, notably remaining admin AJAX/CRUD CSRF coverage, PHP 8 warning cleanup,
TimThumb, jqPlot, jCart, MyISAM storage and the legacy PayPal IPN integration.
These items are tracked in `ROADMAP.md`.

Issue #1 (cart-count autotag) is implemented in 1.7.0. Issue #2 still needs
clearer behavior and acceptance criteria before implementation.

## PayPal compatibility and security (2026)

- Supports PayPal Sandbox and Live through the maintained legacy IPN and NVP/SOAP endpoints.
- IPN verification uses `ipnpb.sandbox.paypal.com` and `ipnpb.paypal.com` with TLS verification enabled.
- NVP calls use `api-3t.sandbox.paypal.com/nvp` and `api-3t.paypal.com/nvp` with TLS verification and HTTP status checks.
- API credentials can be supplied through `PAYPAL_API_USERNAME`, `PAYPAL_API_PASSWORD`, and `PAYPAL_API_SIGNATURE` environment variables; these take precedence over Geeklog configuration values.
- Server-side IPN validation checks receiver identity, transaction uniqueness, currency, product prices, enabled attributes, and allowed shipping amounts before fulfillment.
- Refund and reversal IPNs revoke related purchases/subscriptions and group access.
- Payment/order tables use InnoDB on new installs and are migrated to InnoDB during the 1.7.0 upgrade.
- PayPal classifies IPN, Website Payments Standard, and NVP/SOAP as legacy integrations. A future major release should migrate checkout to the current PayPal Orders REST API / JavaScript SDK while preserving an upgrade path for existing sites.
