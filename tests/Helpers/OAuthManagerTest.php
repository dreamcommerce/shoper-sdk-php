<?php

declare(strict_types=1);

namespace Shoper\Sdk\Tests\Helpers;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shoper\Sdk\Exception\InvalidAuthCodeException;
use Shoper\Sdk\Exception\InvalidRefreshTokenException;
use Shoper\Sdk\OAuthManager;

final class OAuthManagerTest extends TestCase
{
    public function test_buildAuthorizationUrl_produces_correct_format(): void
    {
        $mgr = new OAuthManager();
        $url = $mgr->buildAuthorizationUrl(
            'https://shop.example.com',
            'client-id-abc',
            'https://app.example.com/callback',
            ['read', 'write']
        );
        self::assertStringStartsWith('https://shop.example.com/admin/oauth/authorize?', $url);
        self::assertStringContainsString('client_id=client-id-abc', $url);
        self::assertStringContainsString('redirect_uri=' . urlencode('https://app.example.com/callback'), $url);
        self::assertStringContainsString('scope=read%20write', $url);
        self::assertStringContainsString('response_type=code', $url);
    }

    public function test_exchangeCodeForToken_returns_tokens_on_success(): void
    {
        $mock = new MockHandler([new Response(200, [], json_encode([
            'access_token' => 'at-123',
            'refresh_token' => 'rt-456',
            'expires_in' => 3600,
            'scope' => 'read write',
        ]))]);
        $http = new HttpClient(['handler' => HandlerStack::create($mock)]);
        $mgr = new OAuthManager($http);

        $tokens = $mgr->exchangeCodeForToken(
            'https://shop.example.com', 'client-id', 'client-secret', 'auth-code'
        );
        self::assertSame('at-123', $tokens['access_token']);
        self::assertSame('rt-456', $tokens['refresh_token']);
    }

    public function test_exchangeCodeForToken_throws_on_invalid_code(): void
    {
        $mock = new MockHandler([new Response(400, [], json_encode([
            'error' => 'invalid_grant',
            'error_description' => 'The authorization code is invalid or expired.',
        ]))]);
        $http = new HttpClient(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        $mgr = new OAuthManager($http);

        $this->expectException(InvalidAuthCodeException::class);
        $mgr->exchangeCodeForToken('https://shop.example.com', 'cid', 'cs', 'bad-code');
    }

    public function test_refreshAccessToken_returns_new_tokens(): void
    {
        $mock = new MockHandler([new Response(200, [], json_encode([
            'access_token' => 'at-new',
            'refresh_token' => 'rt-new',
            'expires_in' => 3600,
        ]))]);
        $http = new HttpClient(['handler' => HandlerStack::create($mock)]);
        $mgr = new OAuthManager($http);

        $tokens = $mgr->refreshAccessToken('https://shop.example.com', 'cid', 'cs', 'rt-old');
        self::assertSame('at-new', $tokens['access_token']);
    }

    public function test_refreshAccessToken_throws_on_invalid_refresh(): void
    {
        $mock = new MockHandler([new Response(400, [], json_encode(['error' => 'invalid_grant']))]);
        $http = new HttpClient(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        $mgr = new OAuthManager($http);

        $this->expectException(InvalidRefreshTokenException::class);
        $mgr->refreshAccessToken('https://shop.example.com', 'cid', 'cs', 'bad-rt');
    }

    public function test_buildAuthorizationUrl_without_state_is_unchanged(): void
    {
        $url = (new OAuthManager())->buildAuthorizationUrl(
            'https://shop.example.com',
            'client-id-abc',
            'https://app.example.com/callback',
            ['read', 'write']
        );
        self::assertSame(
            'https://shop.example.com/admin/oauth/authorize?response_type=code&client_id=client-id-abc'
            . '&redirect_uri=https%3A%2F%2Fapp.example.com%2Fcallback&scope=read%20write',
            $url
        );
    }

    public function test_buildAuthorizationUrl_appends_state_after_scope(): void
    {
        $url = (new OAuthManager())->buildAuthorizationUrl(
            'https://shop.example.com', 'cid', 'https://app.example.com/cb', ['read', 'write'], 'abc123'
        );
        self::assertStringEndsWith('&scope=read%20write&state=abc123', $url);
    }

    public function test_buildAuthorizationUrl_encodes_state_rfc3986(): void
    {
        $url = (new OAuthManager())->buildAuthorizationUrl(
            'https://shop.example.com', 'cid', 'https://app.example.com/cb', [], 'a b&c'
        );
        self::assertStringContainsString('state=a%20b%26c', $url);
    }

    public function test_buildAuthorizationUrl_ignores_empty_state(): void
    {
        $mgr = new OAuthManager();
        $without = $mgr->buildAuthorizationUrl('https://shop.example.com', 'cid', 'https://app.example.com/cb');
        self::assertSame($without, $mgr->buildAuthorizationUrl('https://shop.example.com', 'cid', 'https://app.example.com/cb', [], ''));
        self::assertStringNotContainsString('state=', $without);
    }

    public function test_generateState_returns_64_hex_chars_and_is_unique(): void
    {
        $a = OAuthManager::generateState();
        $b = OAuthManager::generateState();
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a);
        self::assertNotSame($a, $b);
    }

    /**
     * @dataProvider verifyStateProvider
     */
    public function test_verifyState_matrix(?string $expected, ?string $received, bool $result): void
    {
        self::assertSame($result, OAuthManager::verifyState($expected, $received));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: bool}>
     */
    public static function verifyStateProvider(): array
    {
        return [
            'equal' => ['x', 'x', true],
            'different' => ['x', 'y', false],
            'expected null' => [null, 'x', false],
            'received null' => ['x', null, false],
            'both empty' => ['', '', false],
            'both null' => [null, null, false],
            'expected empty' => ['', 'x', false],
            'received empty' => ['x', '', false],
        ];
    }

    public function test_forged_callback_never_reaches_token_endpoint(): void
    {
        $mock = new MockHandler([]);
        $mgr = new OAuthManager(new HttpClient(['handler' => HandlerStack::create($mock)]));
        $sessionState = OAuthManager::generateState();

        foreach (['attacker-state', null, ''] as $received) {
            if (OAuthManager::verifyState($sessionState, $received)) {
                $mgr->exchangeCodeForToken('https://shop.example.com', 'cid', 'cs', 'attacker-code');
            }
        }
        self::assertCount(0, $mock);
    }

    public function test_legitimate_callback_exchanges_code(): void
    {
        $mock = new MockHandler([new Response(200, [], json_encode(['access_token' => 'at-1', 'refresh_token' => 'rt-1']))]);
        $mgr = new OAuthManager(new HttpClient(['handler' => HandlerStack::create($mock)]));
        $state = OAuthManager::generateState();

        self::assertTrue(OAuthManager::verifyState($state, $state));
        $tokens = $mgr->exchangeCodeForToken('https://shop.example.com', 'cid', 'cs', 'code');
        self::assertSame('at-1', $tokens['access_token']);
    }
}
