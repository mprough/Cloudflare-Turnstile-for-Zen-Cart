<?php

declare(strict_types=1);

namespace ProWebs\EdgeChallenge;

final class Targets
{
    public const PRESETS = [
        'login' => 'Login',
        'create_account' => 'Create account',
        'order_status' => 'Order status',
        'account_history' => 'Account history',
        'account_history_info' => 'Order details',
        'discount_coupon' => 'Coupon lookup',
    ];

    public static function rule(
        string $host,
        string $catalog,
        string $admin,
        string $type,
        string $value,
        int $minutes
    ): array {
        $host = strtolower($host);
        if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new \InvalidArgumentException('Set a valid storefront hostname.');
        }

        $catalog = rtrim($catalog, '/') . '/';
        if (!self::validPath($catalog) || !in_array($minutes, [0, 15, 30, 60, 120], true)) {
            throw new \InvalidArgumentException('Check the storefront path and challenge duration.');
        }

        $index = 'http.request.uri.path in {' . self::quote($catalog) . ' ' . self::quote($catalog . 'index.php') . '}';
        switch ($type) {
            case 'route':
                if (!preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $value)) {
                    throw new \InvalidArgumentException('Enter a Zen Cart page name, such as account_history.');
                }
                $target = $index
                    . ' and any(http.request.uri.args["main_page"][*] eq ' . self::quote($value) . ')';
                $label = self::PRESETS[$value] ?? $value;
                break;

            case 'product':
                if (!preg_match('/\A[1-9][0-9]{0,9}\z/D', $value)) {
                    throw new \InvalidArgumentException('Enter a numeric product ID.');
                }
                $target = $index
                    . ' and any(http.request.uri.args["main_page"][*] eq "product_info")'
                    . ' and any(http.request.uri.args["products_id"][*] eq ' . self::quote($value) . ')';
                $label = 'Product ' . $value;
                break;

            case 'path':
                $admin = rtrim($admin, '/') . '/';
                if (!self::validPath($value) || !str_starts_with($value, $catalog)
                    || $value === $catalog || $value === $catalog . 'index.php'
                    || $value === rtrim($admin, '/') || str_starts_with($value, $admin)
                ) {
                    throw new \InvalidArgumentException('Enter one public storefront path, such as /cart/my-product.html.');
                }
                $target = 'http.request.uri.path eq ' . self::quote($value);
                $label = $value;
                break;

            default:
                throw new \InvalidArgumentException('Choose a protection target.');
        }

        $expression = '(http.host eq ' . self::quote($host)
            . ' and http.request.method in {"GET" "HEAD"} and ' . $target;
        if ($minutes > 0) {
            $expression .= ' and http.request.timestamp.sec lt ' . (time() + $minutes * 60);
        }

        return [
            'ref' => self::prefix($host, $catalog) . substr(hash('sha256', $target), 0, 24),
            'action' => 'managed_challenge',
            'description' => 'PRO-Webs: ' . $label,
            'expression' => $expression . ')',
            'enabled' => true,
        ];
    }

    public static function prefix(string $host, string $catalog): string
    {
        return 'prowebs_' . substr(hash('sha256', strtolower($host) . '|' . rtrim($catalog, '/')), 0, 16) . '_';
    }

    private static function validPath(string $path): bool
    {
        return preg_match('~\A/(?:[a-zA-Z0-9_./\~-]{0,150})\z~D', $path) === 1
            && !str_contains($path, '//') && !preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path);
    }

    private static function quote(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
