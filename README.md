# Cloudflare Turnstile for Zen Cart

Development for Zen Cart 2.0.0, 2.0.1, 2.1.0, and 2.2.2 starts with the 2.1.0 storefront. This branch contains the PHP Siteverify client. It requires PHP 8 and cURL. Storefront wiring and Admin settings are pending.

## Default storefront route presets

| Preset | Zen Cart 2.1.0 integration point |
| --- | --- |
| Login | `NOTIFY_HEADER_START_LOGIN` before handling `action=process`. |
| Create account | `NOTIFY_HEADER_START_CREATE_ACCOUNT` before the account creation module is loaded. Test both direct registration and the registration form on the login page. |
| Coupon lookup | `discount_coupon` reads the `lookup_discount_coupon` POST field. Enforce verification before the lookup query. |
| Order status | Protect guest lookup submissions to `order_status` with `action=status`. Signed-in customers are redirected to Account History. |
| Account history | Offer a page-visit challenge for `account_history`, before its order list is rendered. |
| Order details | Offer the same page-visit protection for `account_history_info`, which has its own route. |

The Admin design calls for per-route enablement and custom form entries. Form entries need the page, submission trigger, form selector, and a distinct Turnstile action. Page-visit entries need the page and a Cloudflare edge rule scoped to that route. List the presets by default and let store owners opt in to enforcement. Match the configured hostname and action during server-side verification. Keep the secret server-side. OPC 2.7.0 needs separate mappings for its AJAX handlers.

## Temporary product protection

Use a Cloudflare WAF Managed Challenge scoped to a selected product URL when traffic must be challenged before it reaches the store. This uses the site's Cloudflare zone. Temporary edge controls and expiry are planned separately from storefront validation.

[Cloudflare Siteverify documentation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)
