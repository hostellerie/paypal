# PayPal for Geeklog

PayPal is a Geeklog shopping/catalog plugin with products, subscriptions,
downloads, stock, shipping and legacy PayPal IPN payment handling.

## PayPal 1.7.0 development baseline

The 1.7.0 stabilization branch targets:

- Geeklog 2.1.1 through 2.2.2
- PHP 5.6 through PHP 8.1

The release preserves the existing payment model while modernizing runtime
compatibility, upgrade safety, security and Geeklog ecosystem interoperability.

## Interoperability

PayPal exposes its permission-aware public product catalog through shared
Geeklog contracts so consumers do not need to query plugin tables directly.

Supported provider capabilities include:

- `content.read`
- `content.collection`
- `content.search`
- `content.popular`
- `content.url.resolve`
- `content.lifecycle`
- `dashboard.summary`

Agent and Hub can consume normalized product Item Info and lifecycle events.
Eclipse can consume the admin-authorized `dashboard_summary` service.

Purchases, customer details, IPN payloads and payment credentials are not
exposed as generic content resources.

## Documentation

- [Release notes](RELEASE_NOTES.md)
- [Changelog](CHANGELOG.md)
- [Roadmap](ROADMAP.md)
- [Issues and feature requests](https://github.com/Geeklog-Plugins/paypal/issues)

The shared modernization conventions used by this work are maintained in the
[Geeklog Plugins Memorandum](https://github.com/Geeklog-Plugins/memorandum).

## Contributing

1. Fork the repository.
2. Create a feature branch.
3. Commit and test the change.
4. Push the branch.
5. Open a pull request.

## Integrated extended features

PayPal 1.7.0 includes the functionality that was historically distributed as a separate extended features. Product attributes, attribute types, manual subscriptions, subscription expiration notifications, recurring-payment helpers, and sales statistics are bundled with the plugin. No separate Pro package is required.

## PayPal compatibility and security (2026)

- Supports PayPal Sandbox and Live through the maintained legacy IPN and NVP/SOAP endpoints.
- IPN verification uses `ipnpb.sandbox.paypal.com` and `ipnpb.paypal.com` with TLS verification enabled.
- NVP calls use `api-3t.sandbox.paypal.com/nvp` and `api-3t.paypal.com/nvp` with TLS verification and HTTP status checks.
- API credentials can be supplied through `PAYPAL_API_USERNAME`, `PAYPAL_API_PASSWORD`, and `PAYPAL_API_SIGNATURE` environment variables; these take precedence over Geeklog configuration values.
- Server-side IPN validation checks receiver identity, transaction uniqueness, currency, product prices, enabled attributes, and allowed shipping amounts before fulfillment.
- Refund and reversal IPNs revoke related purchases/subscriptions and group access.
- Payment/order tables use InnoDB on new installs and are migrated to InnoDB during the 1.7.0 upgrade.
- PayPal classifies IPN, Website Payments Standard, and NVP/SOAP as legacy integrations. A future major release should migrate checkout to the current PayPal Orders REST API / JavaScript SDK while preserving an upgrade path for existing sites.
