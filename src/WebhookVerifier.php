<?php

declare(strict_types=1);

namespace Shoper\Sdk;

use Psr\Http\Message\RequestInterface;

/**
 * Verifies Shoper webhook deliveries.
 *
 * Application secrets (appstoreSecret, webhookSecret) are server-side only: whoever holds both
 * can sign webhooks for every shop of the application. Use fromShopSecret() when the per-shop
 * secret is stored at installation time or the webhook was created through the REST API.
 */
final class WebhookVerifier
{
    private const SIGNATURE_PREFIX = 'sha256=';

    private string $appstoreSecret;
    private string $webhookSecret;
    private ?string $shopSecret = null;
    private int $tolerance = 300;
    private bool $legacySha1 = true;

    /** @var callable|null */
    private $replayGuard = null;

    /** @var callable|null */
    private $shopGuard = null;

    /** @var callable|null */
    private $clock = null;

    public function __construct(string $appstoreSecret, string $webhookSecret)
    {
        $this->appstoreSecret = $appstoreSecret;
        $this->webhookSecret = $webhookSecret;
    }

    /**
     * Creates a verifier that uses the given per-shop (or REST webhook) secret directly.
     *
     * @throws \InvalidArgumentException when the secret is empty
     */
    public static function fromShopSecret(string $shopSecret): self
    {
        if ($shopSecret === '') {
            throw new \InvalidArgumentException('Webhook secret must not be empty');
        }
        $verifier = new self('', '');
        $verifier->shopSecret = $shopSecret;
        return $verifier;
    }

    /**
     * Sets the accepted clock skew in seconds for the timestamped signature (default 300).
     */
    public function withTolerance(int $seconds): self
    {
        if ($seconds < 1) {
            throw new \InvalidArgumentException('Tolerance must be at least 1 second');
        }
        $clone = clone $this;
        $clone->tolerance = $seconds;
        return $clone;
    }

    /**
     * Rejects deliveries signed only with the legacy X-Webhook-SHA1 header.
     */
    public function withoutLegacySha1(): self
    {
        $clone = clone $this;
        $clone->legacySha1 = false;
        return $clone;
    }

    /**
     * Registers a guard called as fn(string $deliveryId, int $timestamp): bool after a valid
     * signature; returning false means the delivery was already seen and is rejected.
     */
    public function withReplayGuard(callable $guard): self
    {
        $clone = clone $this;
        $clone->replayGuard = $guard;
        return $clone;
    }

    /**
     * Registers a guard called as fn(string $shopHash): bool; returning false rejects
     * deliveries from shops that are unknown or have uninstalled the application.
     */
    public function withShopGuard(callable $guard): self
    {
        $clone = clone $this;
        $clone->shopGuard = $guard;
        return $clone;
    }

    /**
     * Overrides the time source, fn(): int returning a Unix timestamp.
     */
    public function withClock(callable $clock): self
    {
        $clone = clone $this;
        $clone->clock = $clock;
        return $clone;
    }

    public function deriveShopSecret(string $shopHash): string
    {
        return hash_hmac('sha512', $shopHash . ':' . $this->webhookSecret, $this->appstoreSecret);
    }

    public function verify(string $webhookId, string $shopHash, string $rawBody, string $signature): bool
    {
        if ($signature === '' || $webhookId === '' || !$this->shopAllowed($shopHash)) {
            return false;
        }
        $secret = $this->resolveSecret($shopHash);
        if ($secret === null) {
            return false;
        }
        $expected = sha1($webhookId . ':' . $secret . ':' . $rawBody);
        return hash_equals($expected, $signature);
    }

    /**
     * Verifies the X-Webhook-Signature header (HMAC-SHA256 over "timestamp.deliveryId.id:body").
     */
    public function verifySignature(
        string $webhookId,
        string $shopHash,
        string $rawBody,
        string $signature,
        string $timestamp,
        string $deliveryId
    ): bool {
        if ($webhookId === '' || $deliveryId === '' || !preg_match('/^\d{1,12}\z/', $timestamp)) {
            return false;
        }
        if (strncmp($signature, self::SIGNATURE_PREFIX, strlen(self::SIGNATURE_PREFIX)) !== 0) {
            return false;
        }
        if (!$this->shopAllowed($shopHash)) {
            return false;
        }
        $secret = $this->resolveSecret($shopHash);
        if ($secret === null) {
            return false;
        }
        $now = $this->clock !== null ? (int) ($this->clock)() : time();
        if (abs($now - (int) $timestamp) > $this->tolerance) {
            return false;
        }
        $expected = self::SIGNATURE_PREFIX . hash_hmac('sha256', $timestamp . '.' . $deliveryId . '.' . $webhookId . ':' . $rawBody, $secret);
        if (!hash_equals($expected, $signature)) {
            return false;
        }
        if ($this->replayGuard !== null && !($this->replayGuard)($deliveryId, (int) $timestamp)) {
            return false;
        }
        return true;
    }

    /**
     * Verifies a delivery from a header map (names are case-insensitive). A present
     * X-Webhook-Signature is always verified on its own, without falling back to SHA1.
     *
     * @param array<string, string|string[]> $headers
     */
    public function verifyHeaders(array $headers, string $rawBody): bool
    {
        $map = [];
        foreach ($headers as $name => $value) {
            $map[strtolower((string) $name)] = is_array($value) ? (string) reset($value) : (string) $value;
        }
        $webhookId = $map['x-webhook-id'] ?? '';
        $shopHash = $map['x-shop-license'] ?? '';
        if (($map['x-webhook-signature'] ?? '') !== '') {
            return $this->verifySignature(
                $webhookId,
                $shopHash,
                $rawBody,
                $map['x-webhook-signature'],
                $map['x-webhook-timestamp'] ?? '',
                $map['x-webhook-delivery-id'] ?? ''
            );
        }
        if (!$this->legacySha1) {
            return false;
        }
        return $this->verify($webhookId, $shopHash, $rawBody, $map['x-webhook-sha1'] ?? '');
    }

    public function verifyFromGlobals(string $rawBody): bool
    {
        $headers = [];
        $keys = [
            'HTTP_X_WEBHOOK_ID' => 'X-Webhook-Id',
            'HTTP_X_SHOP_LICENSE' => 'X-Shop-License',
            'HTTP_X_WEBHOOK_SHA1' => 'X-Webhook-SHA1',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'X-Webhook-Signature',
            'HTTP_X_WEBHOOK_TIMESTAMP' => 'X-Webhook-Timestamp',
            'HTTP_X_WEBHOOK_DELIVERY_ID' => 'X-Webhook-Delivery-Id',
        ];
        foreach ($keys as $key => $name) {
            $headers[$name] = (string) ($_SERVER[$key] ?? '');
        }
        return $this->verifyHeaders($headers, $rawBody);
    }

    public function verifyFromPsr7(RequestInterface $request): bool
    {
        $headers = [];
        foreach (['X-Webhook-Id', 'X-Shop-License', 'X-Webhook-SHA1', 'X-Webhook-Signature', 'X-Webhook-Timestamp', 'X-Webhook-Delivery-Id'] as $name) {
            $headers[$name] = $request->getHeaderLine($name);
        }
        return $this->verifyHeaders($headers, (string) $request->getBody());
    }

    private function shopAllowed(string $shopHash): bool
    {
        return $this->shopGuard === null || (bool) ($this->shopGuard)($shopHash);
    }

    private function resolveSecret(string $shopHash): ?string
    {
        if ($this->shopSecret !== null) {
            return $this->shopSecret;
        }
        if ($this->appstoreSecret === '' || $this->webhookSecret === '' || $shopHash === '') {
            return null;
        }
        return $this->deriveShopSecret($shopHash);
    }
}
