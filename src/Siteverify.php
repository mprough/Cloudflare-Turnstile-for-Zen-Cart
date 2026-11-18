<?php

declare(strict_types=1);

namespace ProWebs\ZenCart\Turnstile;

final class Siteverify
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Verify one submission against the configured hostname and form action.
     */
    public function verify(
        string $secret,
        string $hostname,
        string $action,
        mixed $token
    ): bool {
        if ($secret === '' || $hostname === '' || $action === '') {
            return false;
        }

        if (!is_string($token) || $token === '' || strlen($token) > 2048) {
            return false;
        }

        if (!function_exists('curl_init')) {
            return false;
        }

        $handle = curl_init(self::ENDPOINT);
        if ($handle === false) {
            return false;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => $secret,
                'response' => $token,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 1500,
            CURLOPT_TIMEOUT_MS => 3500,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        unset($handle);

        if ($status !== 200 || !is_string($body)) {
            return false;
        }

        $result = json_decode($body, true);
        return is_array($result)
            && ($result['success'] ?? null) === true
            && is_string($result['hostname'] ?? null)
            && strcasecmp($result['hostname'], $hostname) === 0
            && ($result['action'] ?? null) === $action;
    }
}
