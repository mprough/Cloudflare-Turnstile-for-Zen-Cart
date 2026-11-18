# Cloudflare Turnstile for Zen Cart

Development for Zen Cart 2.0.0, 2.0.1, 2.1.0, and 2.2.2 starts with the 2.0.1 storefront. This branch contains the PHP Siteverify client. It requires PHP 8 and cURL. Storefront wiring and Admin settings are pending.

## Storefront route presets

| Preset | Zen Cart 2.0.1 integration point |
| --- | --- |
| Login | `NOTIFY_HEADER_START_LOGIN` before handling `action=process`. |
| Create account | `NOTIFY_HEADER_START_CREATE_ACCOUNT` before the account creation module is loaded. Test both direct registration and the registration form on the login page. |
| Coupon lookup | `discount_coupon` reads the `lookup_discount_coupon` POST field. Enforce verification before the lookup query. |
| Order status | The stock `order_status` route redirects to Account History. Stores with a guest lookup need a route entry for the installed lookup handler. |

The Admin design calls for per-route enablement and custom form entries. Each entry needs the page, the submission trigger, the form selector, and a distinct Turnstile action. Match the configured hostname and action during server-side verification. Keep the secret server-side. OPC 2.7.0 needs separate mappings for its AJAX handlers.

## Temporary product protection

Use a Cloudflare WAF Managed Challenge scoped to a selected product URL when traffic must be challenged before it reaches the store. This uses the site's Cloudflare zone. Temporary edge controls and expiry are planned separately from storefront validation.

[Cloudflare Siteverify documentation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)
