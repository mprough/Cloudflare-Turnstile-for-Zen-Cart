# Cloudflare Turnstile for Zen Cart

## Dev build 0.1.0

This build manages Cloudflare WAF Managed Challenges for storefront page visits from Zen Cart Admin. Cloudflare evaluates matching GET and HEAD requests before forwarding them to the store. The storefront forms, including any hardwired reCAPTCHA, keep their existing validation. This build uses the WAF and needs no Turnstile widget keys.

### Install on a Zen Cart 2.1.0 dev store

1. Confirm the storefront hostname is proxied through Cloudflare. In Cloudflare, make an API token with **Zone / WAF / Edit**, scoped to that one zone, and copy the zone ID.
2. Upload the contents of the ZIP's `admin/` folder into your store's renamed Admin directory. Preserve the folder structure.
3. In that Admin directory, copy `includes/prowebs_edge/config.example.php` to `includes/prowebs_edge/config.local.php` and enter the zone ID and token in the copy. Keep the copy private. Alternatively, set `PROWEBS_CF_ZONE_ID` and `PROWEBS_CF_API_TOKEN` in the Admin PHP environment. Set the optional `host` in the local config if shoppers use a different hostname from the Zen Cart HTTPS configuration. This build scopes rules to one exact hostname.
4. Open **Tools > Cloudflare page challenges** as a super admin. The plugin registers this menu on the first eligible Admin load. Grant access through Admin Profiles if needed.
5. Enable a preset or custom target and verify the rule under Cloudflare's custom WAF rules. Test with a fresh browser session on the chosen page. PHP cURL is required for Admin to talk to Cloudflare.

### Targets

- Presets include Login, Create account, Order status, Account history, Order details and Coupon lookup. Each preset starts inactive and uses the standard `main_page` URL.
- Product ID protects the standard `product_info` URL for one product. For an SEO-rewritten product URL, choose **Exact public URL path** and enter the path you actually use, with its store directory if applicable. Use a separate target if both URLs need protection. Custom Zen Cart page names are also supported.
- Each target uses one custom rule slot. If Cloudflare accepts a timed rule on your plan, it stops matching at the displayed UTC time. Remove expired rules to free rule slots. If Cloudflare rejects a timed expression, choose **Until removed** and remove it from Admin when ready.
- Rules cover page visits. Guest order lookup and coupon lookup POSTs, storefront form validation and OPC AJAX submissions are outside this build's coverage. The storefront must send matching traffic through Cloudflare for the rules to apply.

### Remove

Remove the plugin's active rules from its Admin screen or Cloudflare before removing the files. To remove the Admin menu registration, remove page key `prowebsEdgeChallenge` using **Admin Page Registration**, after removing the plugin files.

[Cloudflare custom WAF rules](https://developers.cloudflare.com/waf/custom-rules/)
