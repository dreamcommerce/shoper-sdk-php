<?php

declare(strict_types=1);

namespace Shoper\Sdk\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Shoper\Sdk\WebhookVerifier;

final class WebhookVerifierTest extends TestCase
{
    private const APPSTORE_SECRET = 'appstore-secret-fixture';
    private const WEBHOOK_SECRET = 'webhook-secret-fixture';
    private const SHOP_HASH = 'shop-hash-fixture';

    private WebhookVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new WebhookVerifier(self::APPSTORE_SECRET, self::WEBHOOK_SECRET);
    }

    protected function tearDown(): void
    {
        foreach (['ID', 'SHA1', 'SIGNATURE', 'TIMESTAMP', 'DELIVERY_ID'] as $suffix) {
            unset($_SERVER['HTTP_X_WEBHOOK_' . $suffix]);
        }
        unset($_SERVER['HTTP_X_SHOP_LICENSE']);
    }

    public function test_deriveShopSecret_is_deterministic(): void
    {
        $a = $this->verifier->deriveShopSecret(self::SHOP_HASH);
        $b = $this->verifier->deriveShopSecret(self::SHOP_HASH);
        self::assertSame($a, $b);
        self::assertNotEmpty($a);
    }

    public function test_verify_returns_true_for_valid_signature(): void
    {
        $webhookId = 'evt-123';
        $body = '{"event":"order.created","data":{"id":1}}';
        $shopSecret = $this->verifier->deriveShopSecret(self::SHOP_HASH);
        $validSig = sha1($webhookId . ':' . $shopSecret . ':' . $body);

        self::assertTrue(
            $this->verifier->verify($webhookId, self::SHOP_HASH, $body, $validSig)
        );
    }

    public function test_verify_returns_false_for_invalid_signature(): void
    {
        self::assertFalse(
            $this->verifier->verify('evt-123', self::SHOP_HASH, 'body', 'bogus-sig')
        );
    }

    public function test_verify_uses_timing_safe_comparison(): void
    {
        // Regression guard: implementacja MUSI używać hash_equals (nie ===).
        // Test pass nawet przy === ale weryfikacja kodu w review.
        $webhookId = 'evt-1';
        $body = 'x';
        $shopSecret = $this->verifier->deriveShopSecret(self::SHOP_HASH);
        $valid = sha1($webhookId . ':' . $shopSecret . ':' . $body);
        self::assertTrue($this->verifier->verify($webhookId, self::SHOP_HASH, $body, $valid));
    }

    public function test_verifyFromGlobals_reads_server_headers(): void
    {
        $webhookId = 'evt-globals';
        $body = '{"test":true}';
        $shopSecret = $this->verifier->deriveShopSecret(self::SHOP_HASH);
        $sig = sha1($webhookId . ':' . $shopSecret . ':' . $body);

        $_SERVER['HTTP_X_WEBHOOK_ID'] = $webhookId;
        $_SERVER['HTTP_X_SHOP_LICENSE'] = self::SHOP_HASH;
        $_SERVER['HTTP_X_WEBHOOK_SHA1'] = $sig;

        self::assertTrue($this->verifier->verifyFromGlobals($body));
    }

    private const TS = '1763550000';
    private const DID = '0b6f0c8e-1111-4222-8333-444455556666';

    private function sig(string $secret, string $id, string $body, string $ts = self::TS, string $did = self::DID): string
    {
        return 'sha256=' . hash_hmac('sha256', $ts . '.' . $did . '.' . $id . ':' . $body, $secret);
    }

    private function clocked(WebhookVerifier $v, int $now = 1763550000): WebhookVerifier
    {
        return $v->withClock(static fn (): int => $now);
    }

    public function test_attack_from_report_wrong_app_secret_or_hash_only_is_rejected(): void
    {
        $v = new WebhookVerifier('appsec', 'whsec');
        $attacker = new WebhookVerifier('guess', 'whsec');
        $body = '{"event":"test"}';
        self::assertFalse($v->verify('evt-1', 'shop-1', $body, sha1('evt-1:' . $attacker->deriveShopSecret('shop-1') . ':' . $body)));
        self::assertFalse($v->verify('evt-1', 'shop-1', $body, sha1('evt-1:shop-1:' . $body)));
        self::assertTrue($v->verify('evt-1', 'shop-1', $body, sha1('evt-1:' . $v->deriveShopSecret('shop-1') . ':' . $body)));
    }

    public function test_empty_key_material_fails_closed(): void
    {
        $body = 'b';
        foreach ([['', 'whsec'], ['appsec', '']] as [$a, $w]) {
            $v = new WebhookVerifier($a, $w);
            self::assertFalse($v->verify('e', 'shop-1', $body, sha1('e:' . $v->deriveShopSecret('shop-1') . ':' . $body)));
        }
        $v = new WebhookVerifier('appsec', 'whsec');
        self::assertFalse($v->verify('e', '', $body, sha1('e:' . $v->deriveShopSecret('') . ':' . $body)));
        $valid = sha1('e:' . $v->deriveShopSecret('s') . ':' . $body);
        self::assertFalse($v->verify('e', 's', $body, ''));
        self::assertFalse($v->verify('', 's', $body, sha1(':' . $v->deriveShopSecret('s') . ':' . $body)));
        self::assertTrue($v->verify('e', 's', $body, $valid));
    }

    public function test_fromShopSecret_mode(): void
    {
        $body = '{"a":1}';
        $v = WebhookVerifier::fromShopSecret('raw-secret');
        self::assertTrue($v->verify('17', 'any-hash', $body, sha1('17:raw-secret:' . $body)));
        $derived = (new WebhookVerifier('appsec', 'whsec'))->deriveShopSecret('any-hash');
        self::assertFalse($v->verify('17', 'any-hash', $body, sha1('17:' . $derived . ':' . $body)));
        $this->expectException(\InvalidArgumentException::class);
        WebhookVerifier::fromShopSecret('');
    }

    public function test_verifySignature_valid_in_both_modes(): void
    {
        $body = '{"a":1}';
        $d = $this->clocked(new WebhookVerifier('appsec', 'whsec'));
        $secret = $d->deriveShopSecret('shop-1');
        self::assertTrue($d->verifySignature('1591', 'shop-1', $body, $this->sig($secret, '1591', $body), self::TS, self::DID));
        $r = $this->clocked(WebhookVerifier::fromShopSecret('raw'));
        self::assertTrue($r->verifySignature('1591', 'shop-1', $body, $this->sig('raw', '1591', $body), self::TS, self::DID));
    }

    public function test_verifySignature_tolerance_window(): void
    {
        $body = '{"a":1}';
        $base = WebhookVerifier::fromShopSecret('raw');
        $sig = $this->sig('raw', '1591', $body);
        self::assertFalse($this->clocked($base, 1763550301)->verifySignature('1591', 's', $body, $sig, self::TS, self::DID));
        self::assertTrue($this->clocked($base, 1763550300)->verifySignature('1591', 's', $body, $sig, self::TS, self::DID));
        self::assertTrue($this->clocked($base->withTolerance(600), 1763550301)->verifySignature('1591', 's', $body, $sig, self::TS, self::DID));
        self::assertFalse($this->clocked($base, 1763549699)->verifySignature('1591', 's', $body, $sig, self::TS, self::DID));
    }

    public function test_verifySignature_rejects_malformed_and_tampered(): void
    {
        $body = '{"a":1}';
        $v = $this->clocked(WebhookVerifier::fromShopSecret('raw'));
        $sig = $this->sig('raw', '1591', $body);
        self::assertFalse($v->verifySignature('1591', 's', $body, $sig, 'abc', self::DID));
        self::assertFalse($v->verifySignature('1591', 's', $body, $sig, '', self::DID));
        self::assertFalse($v->verifySignature('1591', 's', $body, $sig, self::TS, ''));
        self::assertFalse($v->verifySignature('1591', 's', $body, substr($sig, 7), self::TS, self::DID));
        self::assertFalse($v->verifySignature('1591', 's', $body, $sig, '1763550001', self::DID));
        self::assertFalse($v->verifySignature('1591', 's', $body, $sig, self::TS, 'other-id'));
        self::assertFalse($v->verifySignature('1592', 's', $body, $sig, self::TS, self::DID));
        $newlineTs = self::TS . "\n";
        self::assertFalse($v->verifySignature('1591', 's', $body, $this->sig('raw', '1591', $body, $newlineTs), $newlineTs, self::DID));
    }

    public function test_verifySignature_rejects_fields_split_differently(): void
    {
        $body = '{"p":"1.5","q":2}';
        $v = $this->clocked(WebhookVerifier::fromShopSecret('raw'));
        $sig = $this->sig('raw', '1591', $body);
        self::assertTrue($v->verifySignature('1591', 's', $body, $sig, self::TS, self::DID));
        self::assertFalse($v->verifySignature('1591:{"p"', 's', '"1.5","q":2}', $sig, self::TS, self::DID));
        self::assertFalse($v->verifySignature('5","q"', 's', '2}', $sig, self::TS, self::DID . '.1591:{"p":"1'));
    }

    public function test_signature_matches_shop_reference_vector(): void
    {
        $v = WebhookVerifier::fromShopSecret('abc')->withClock(static fn (): int => 1763550000);
        $headers = [
            'X-Webhook-Id' => '1591',
            'X-Webhook-Timestamp' => '1763550000',
            'X-Webhook-Delivery-Id' => '0b6f2d4e-1a3c-4b5d-8e7f-9a0b1c2d3e4f',
            'X-Webhook-Signature' => 'sha256=5d9fbec9f7dae6f988436693004310edc65b3b9fdb3da380a49498a1233b8b84',
            'X-Webhook-SHA1' => 'fb906a42c30971ecdfd1b4564046317e22fe739e',
        ];
        self::assertTrue($v->verifyHeaders($headers, '{"a":1}'));
        self::assertTrue($v->verify('1591', '', '{"a":1}', $headers['X-Webhook-SHA1']));
    }

    public function test_replay_guard(): void
    {
        $body = '{"a":1}';
        $seen = [];
        $v = $this->clocked(WebhookVerifier::fromShopSecret('raw'))->withReplayGuard(
            static function (string $did, int $ts) use (&$seen): bool {
                if (isset($seen[$did])) {
                    return false;
                }
                $seen[$did] = $ts;
                return true;
            }
        );
        $sig = $this->sig('raw', '1591', $body);
        self::assertTrue($v->verifySignature('1591', 's', $body, $sig, self::TS, self::DID));
        self::assertFalse($v->verifySignature('1591', 's', $body, $sig, self::TS, self::DID));
        $calls = 0;
        $v2 = $this->clocked(WebhookVerifier::fromShopSecret('raw'))->withReplayGuard(static function () use (&$calls): bool {
            $calls++;
            return true;
        });
        self::assertFalse($v2->verifySignature('1591', 's', $body, 'sha256=' . str_repeat('0', 64), self::TS, self::DID));
        self::assertSame(0, $calls);
    }

    public function test_shop_guard(): void
    {
        $body = 'x';
        $v = $this->clocked(new WebhookVerifier('appsec', 'whsec'))->withShopGuard(static fn (string $h): bool => $h === 'shop-1');
        foreach (['shop-1' => true, 'shop-2' => false] as $shop => $expected) {
            $secret = $v->deriveShopSecret($shop);
            self::assertSame($expected, $v->verifySignature('1', $shop, $body, $this->sig($secret, '1', $body), self::TS, self::DID));
            self::assertSame($expected, $v->verify('1', $shop, $body, sha1('1:' . $secret . ':' . $body)));
        }
    }

    public function test_verifyHeaders_no_downgrade_and_legacy_switch(): void
    {
        $body = '{"a":1}';
        $v = $this->clocked(WebhookVerifier::fromShopSecret('raw'));
        $good = $this->sig('raw', '1591', $body);
        $sha1 = sha1('1591:raw:' . $body);
        $base = ['x-webhook-id' => '1591', 'X-Shop-License' => 's', 'X-WEBHOOK-TIMESTAMP' => self::TS, 'X-Webhook-Delivery-Id' => self::DID];
        self::assertTrue($v->verifyHeaders($base + ['X-Webhook-Signature' => $good, 'X-Webhook-SHA1' => 'bad'], $body));
        self::assertFalse($v->verifyHeaders($base + ['X-Webhook-Signature' => 'sha256=bad', 'X-Webhook-SHA1' => $sha1], $body));
        $legacy = ['X-Webhook-Id' => '1591', 'X-Shop-License' => 's', 'X-Webhook-SHA1' => $sha1];
        self::assertTrue($v->verifyHeaders($legacy, $body));
        self::assertFalse($v->withoutLegacySha1()->verifyHeaders($legacy, $body));
        self::assertTrue($v->verifyHeaders($legacy, $body));
    }

    public function test_verifyFromGlobals_and_psr7_use_new_signature(): void
    {
        $body = '{"a":1}';
        $v = $this->clocked(WebhookVerifier::fromShopSecret('raw'));
        $_SERVER['HTTP_X_WEBHOOK_ID'] = '1591';
        $_SERVER['HTTP_X_SHOP_LICENSE'] = 's';
        $_SERVER['HTTP_X_WEBHOOK_SHA1'] = 'bad';
        $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] = $this->sig('raw', '1591', $body);
        $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] = self::TS;
        $_SERVER['HTTP_X_WEBHOOK_DELIVERY_ID'] = self::DID;
        self::assertTrue($v->verifyFromGlobals($body));
        $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] = 'sha256=bad';
        $_SERVER['HTTP_X_WEBHOOK_SHA1'] = sha1('1591:raw:' . $body);
        self::assertFalse($v->verifyFromGlobals($body));
        unset($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'], $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'], $_SERVER['HTTP_X_WEBHOOK_DELIVERY_ID']);
        self::assertTrue($v->verifyFromGlobals($body));

        if (!class_exists(\GuzzleHttp\Psr7\Request::class)) {
            self::markTestSkipped('guzzle psr7 not installed');
        }
        $req = new \GuzzleHttp\Psr7\Request('POST', '/h', [
            'X-Webhook-Id' => '1591', 'X-Shop-License' => 's', 'X-Webhook-SHA1' => 'bad',
            'X-Webhook-Signature' => $this->sig('raw', '1591', $body),
            'X-Webhook-Timestamp' => self::TS, 'X-Webhook-Delivery-Id' => self::DID,
        ], $body);
        self::assertTrue($v->verifyFromPsr7($req));
        self::assertFalse($v->verifyFromPsr7($req->withHeader('X-Webhook-Signature', 'sha256=bad')));
    }

    public function test_withers_do_not_mutate_and_validate(): void
    {
        $body = '{"a":1}';
        $v = $this->clocked(WebhookVerifier::fromShopSecret('raw'));
        $v->withoutLegacySha1();
        $v->withShopGuard(static fn (): bool => false);
        $v->withReplayGuard(static fn (): bool => false);
        $v->withTolerance(600);
        $v->withClock(static fn (): int => 1763550301);
        $legacy = ['X-Webhook-Id' => '1591', 'X-Shop-License' => 's', 'X-Webhook-SHA1' => sha1('1591:raw:' . $body)];
        self::assertTrue($v->verifyHeaders($legacy, $body));
        self::assertTrue($v->verifySignature('1591', 's', $body, $this->sig('raw', '1591', $body), self::TS, self::DID));
        self::assertFalse($this->clocked($v, 1763550301)->verifySignature('1591', 's', $body, $this->sig('raw', '1591', $body), self::TS, self::DID));
        $this->expectException(\InvalidArgumentException::class);
        $v->withTolerance(0);
    }
}
