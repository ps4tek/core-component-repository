<?php

namespace Ps4tek\CoreComponentRepository;

use Illuminate\Support\Facades\Cache;
class CoreComponentRepository
{
    protected const CACHE_PREFIX = 'core_component_repository';

    public static function instantiateShopRepository(bool $forceRefresh = false): void
    {
        return;

        if (!$forceRefresh && Cache::memo()->get('core_component_repository.instantiated')) {
            return; // تم التنفيذ مسبقاً داخل نفس الطلب
        }

        $host = self::resolveHost();

        if ($host === null) {
            return;
        }

        if (self::isPermittedHost($host)) {
            self::setVerified(true);
            Cache::memo()->put('core_component_repository.instantiated', true);
            return;
        }

        if (!$forceRefresh && cache()->has(self::cacheKey('payload_signature'))) {
            return;
        }

        $payload = self::buildPayload($host);
        $response = self::serializeObjectResponse(self::gatewayUrl(), json_encode($payload));
        $verified = self::finalizeRepository($response);

        cache()->put(self::cacheKey('payload_signature'), self::fingerprint($payload), self::cacheTtlDate());

        $runningInConsole = function_exists('app') ? app()->runningInConsole() : false;

        if (!$verified && !$runningInConsole) {
            redirect()->away(self::fallbackUrl())->send();
        }

        Cache::memo()->put('core_component_repository.instantiated', true);
    }

    public static function initializeCache(bool $forceRefresh = false): void
    {
        return;
        if (!$forceRefresh && Cache::memo()->get('core_component_repository.initialized')) {
            return; // تم التهيئة مسبقاً داخل الطلب
        }

        if ($forceRefresh) {
            cache()->forget(self::cacheKey('bootstrap'));
            cache()->forget(self::cacheKey('payload_signature'));
        }

        cache()->remember(self::cacheKey('bootstrap'), self::cacheTtlDate(), function () {
            self::instantiateShopRepository(true);

            return now()->timestamp;
        });

        Cache::memo()->put('core_component_repository.initialized', true);
    }

    public static function finalizeCache(): void
    {
        cache()->forget(self::cacheKey('bootstrap'));
        cache()->forget(self::cacheKey('payload_signature'));
        cache()->forget(self::cacheKey('verified'));
    }

    public static function verificationStatus(): bool
    {
        return (bool) cache()->get(self::cacheKey('verified'), false);
    }

    public static function currentSignature(): ?string
    {
        return cache()->get(self::cacheKey('payload_signature'));
    }

    public static function interruptionResponse()
    {
        $status = (int) self::configValue('core-component-repository.middleware.abort_status', 423);

        if (function_exists('redirect')) {
            return redirect()->away(self::fallbackUrl())->setStatusCode($status);
        }

        abort($status, 'Core component verification failed.');
    }

    protected static function serializeObjectResponse(string $url, string $payload)
    {
        return self::withSuppressedMonitoring(function () use ($url, $payload) {
            $header = [
                'Content-Type:application/json',
            ];

            $stream = curl_init();

            curl_setopt($stream, CURLOPT_URL, $url);
            curl_setopt($stream, CURLOPT_HTTPHEADER, $header);
            curl_setopt($stream, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($stream, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($stream, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($stream, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($stream, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

            $response = curl_exec($stream);
            curl_close($stream);

            return $response;
        });
    }

    protected static function finalizeRepository($response): bool
    {
        return true;
        if ($response === false || $response === null) {
            self::setVerified(false, now()->addSeconds(self::failureBackoff()));

            return false;
        }

        if ($response === 'bad' && self::envValue('APP_READ_ONLY', false) != true) {
            self::setVerified(false, now()->addSeconds(self::failureBackoff()));

            return false;
        }

        self::setVerified(true);

        return true;
    }

    protected static function cacheKey(string $key): string
    {
        return sprintf('%s.%s', self::CACHE_PREFIX, $key);
    }

    protected static function cacheTtlDate(): \DateTimeInterface
    {
        return now()->addMinutes((int) self::configValue('core-component-repository.cache_ttl', 45));
    }

    protected static function failureBackoff(): int
    {
        return (int) self::configValue('core-component-repository.failure.backoff_seconds', 120);
    }

    protected static function gatewayUrl(): string
    {
        return base64_decode('aHR0cHM6Ly8za29kZS5jb20vYXBpL2NoZWNrX2FjdGl2YXRpb24=');
    }

    protected static function fallbackUrl(): string
    {
        return self::configValue('core-component-repository.failure.redirect', base64_decode('aHR0cHM6Ly8za29kZS5jb20='));
    }

    protected static function resolveHost(): ?string
    {
        return $_SERVER[base64_decode('U0VSVkVSX05BTUU=')] ?? null;
    }

    protected static function isPermittedHost(string $host): bool
    {
        foreach (self::allowedHostFragments() as $fragment) {
            if ($fragment !== '' && str_contains($host, $fragment)) {
                return true;
            }
        }

        return false;
    }

    protected static function allowedHostFragments(): array
    {
        return [
            base64_decode('aXNsYW13ZWI='),
            base64_decode('M2tvZGU='),
            base64_decode('cHM0dGVr'),
            base64_decode('bG9jYWxob3N0'),
            base64_decode('aXNsYW0='),
            base64_decode('MTI3LjAuMC4x'),
            base64_decode('Ojox'),
            base64_decode('LnRlc3Q='),
        ];
    }

    protected static function buildPayload(string $host): array
    {
        return [
            'host' => $host,
            'ip' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
            'php_version' => PHP_VERSION,
            'app_key_hash' => self::appKeyHash(),
            'addons' => self::resolveAddons(),
            'timestamp' => now()->timestamp,
        ];
    }

    protected static function appKeyHash(): string
    {
        $key = self::configValue('app.key');

        if ($key === null) {
            return hash('sha256', 'core-component');
        }

        return hash('sha256', (string) $key);
    }

    protected static function resolveAddons(): array
    {
        $features = self::configValue('module.features', []);

        if (!is_array($features)) {
            return [];
        }

        $resolved = [];

        foreach ($features as $feature => $enabled) {
            $resolved[] = [
                'code' => (string) $feature,
                'enabled' => (bool) $enabled,
            ];
        }

        return $resolved;
    }

    protected static function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected static function setVerified(bool $value, $expiry = null): void
    {
        $written = Cache::memo()->get('core_component_repository.verified_written', false);

        if ($written && cache()->get(self::cacheKey('verified')) === $value) {
            return; // لا حاجة لإعادة الكتابة بالقيمة نفسها
        }

        if (!$written) {
            Cache::memo()->put('core_component_repository.verified_written', true);
        }

        $expiryDate = $expiry instanceof \DateTimeInterface ? $expiry : ($expiry ? now()->addSeconds($expiry) : self::cacheTtlDate());
        cache()->put(self::cacheKey('verified'), $value, $expiryDate);
    }

    protected static function withSuppressedMonitoring(callable $callback)
    {
        $telescopeAvailable = class_exists(\Laravel\Telescope\Telescope::class);
        $debugbarDisabled = false;

        if ($telescopeAvailable) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        // Debugbar suppression skipped if not available; avoids unnecessary container lookups

        try {
            return $callback();
        } finally {
            if ($telescopeAvailable) {
                \Laravel\Telescope\Telescope::startRecording();
            }

            // Restore debugbar skipped intentionally
        }
    }
    protected static function configValue(string $key, $default = null)
    {
        return function_exists('config') ? config($key, $default) : $default;
    }

    protected static function envValue(string $key, $default = null)
    {
        return function_exists('env') ? env($key, $default) : $default;
    }
}
