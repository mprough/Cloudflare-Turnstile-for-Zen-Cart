<?php

declare(strict_types=1);

namespace ProWebs\EdgeChallenge;

final class Cloudflare
{
    private const PHASE = 'http_request_firewall_custom';
    private const API = 'https://api.cloudflare.com/client/v4/zones/';

    public function __construct(private string $zone, private string $token)
    {
        if (!self::isId($zone) || !preg_match('/\A[A-Za-z0-9_.-]{20,255}\z/D', $token)) {
            throw new \InvalidArgumentException('Configure the Cloudflare zone ID and API token first.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Enable the PHP cURL extension.');
        }
    }

    public function rules(string $prefix): array
    {
        $entry = $this->entry();
        return array_values(array_filter($entry['rules'] ?? [], static fn (array $rule): bool =>
            ($rule['action'] ?? '') === 'managed_challenge'
            && str_starts_with($rule['ref'] ?? '', $prefix)
            && self::isId($rule['id'] ?? '')
        ));
    }

    public function save(array $rule): void
    {
        $entry = $this->entry();
        if ($entry === null) {
            $this->request('POST', '/rulesets', [
                'name' => 'Zone custom rules',
                'kind' => 'zone',
                'phase' => self::PHASE,
                'rules' => [$rule],
            ]);
            return;
        }

        $path = '/rulesets/' . $entry['id'] . '/rules';
        foreach ($entry['rules'] ?? [] as $existing) {
            if (($existing['ref'] ?? '') !== $rule['ref']) {
                continue;
            }
            if (($existing['action'] ?? '') !== 'managed_challenge' || !self::isId($existing['id'] ?? '')) {
                throw new \RuntimeException('Cloudflare returned a conflicting rule reference.');
            }
            $this->request('PATCH', $path . '/' . $existing['id'], $rule);
            return;
        }
        $this->request('POST', $path, $rule);
    }

    public function remove(string $id, string $prefix): void
    {
        $entry = $this->entry();
        foreach ($entry['rules'] ?? [] as $rule) {
            if (self::isId($id) && ($rule['id'] ?? '') === $id
                && ($rule['action'] ?? '') === 'managed_challenge'
                && str_starts_with($rule['ref'] ?? '', $prefix)
            ) {
                $this->request('DELETE', '/rulesets/' . $entry['id'] . '/rules/' . $id);
                return;
            }
        }
        throw new \RuntimeException('This rule is no longer listed for this storefront.');
    }

    private function entry(): ?array
    {
        $result = $this->request('GET', '/rulesets/phases/' . self::PHASE . '/entrypoint', null, true);
        if ($result !== null && !self::isId($result['id'] ?? '')) {
            throw new \RuntimeException('Cloudflare returned an invalid ruleset ID.');
        }
        return $result;
    }

    private static function isId(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{32}\z/D', $value) === 1;
    }

    private function request(string $method, string $path, ?array $data = null, bool $allowMissing = false): ?array
    {
        $handle = curl_init(self::API . $this->zone . $path);
        if ($handle === false) {
            throw new \RuntimeException('Could not initialize the Cloudflare connection.');
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
        ]);
        if ($data !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
        }
        $body = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        unset($handle);

        $response = is_string($body) ? json_decode($body, true) : null;
        if ($allowMissing && $status === 404 && is_array($response)) {
            return null;
        }
        if ($status < 200 || $status >= 300 || ($response['success'] ?? false) !== true) {
            $code = $response['errors'][0]['code'] ?? null;
            $detail = is_int($code) ? ' (Cloudflare code ' . $code . ')' : '';
            throw new \RuntimeException($status === 0
                ? 'Could not reach Cloudflare. Check the connection and refresh the rule list.'
                : 'Cloudflare rejected the request (HTTP ' . $status . ')' . $detail . '.');
        }
        return $response['result'] ?? [];
    }
}
