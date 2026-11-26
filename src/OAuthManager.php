<?php

declare(strict_types=1);

namespace Shoper\Sdk;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use Shoper\Sdk\Exception\InvalidAuthCodeException;
use Shoper\Sdk\Exception\InvalidRefreshTokenException;

final class OAuthManager
{
    private HttpClient $http;

    public function __construct(?HttpClient $http = null)
    {
        $this->http = $http ?? new HttpClient(['http_errors' => false]);
    }

    /**
     * Generates an unguessable value for the OAuth "state" parameter (64 hex characters).
     * Store it in the user session before redirecting and check it with verifyState() on the callback.
     */
    public static function generateState(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Compares the state kept in the session with the one returned on the callback.
     * Fail-closed: null or empty values never match. Call it before exchangeCodeForToken() or
     * ShoperClient::authenticateOAuth(), and remove the state from the session afterwards so it is single-use.
     */
    public static function verifyState(?string $expected, ?string $received): bool
    {
        if ($expected === null || $expected === '' || $received === null || $received === '') {
            return false;
        }
        return hash_equals($expected, $received);
    }

    /**
     * @param array<int, string> $scopes
     * @param string|null $state Value from generateState(); appended as the "state" query parameter when not empty.
     * @deprecated Shoper shops do not expose /admin/oauth/authorize. AppStore applications receive the
     *             authorization code during an installation started by the shop. Will be removed in 1.0.
     */
    public function buildAuthorizationUrl(
        string $shopDomain,
        string $clientId,
        string $redirectUri,
        array $scopes = [],
        ?string $state = null
    ): string {
        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ];
        if (!empty($scopes)) {
            $params['scope'] = implode(' ', $scopes);
        }
        if ($state !== null && $state !== '') {
            $params['state'] = $state;
        }
        return rtrim($shopDomain, '/') . '/admin/oauth/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * An application that starts the browser flow itself must call OAuthManager::verifyState() before this method.
     *
     * @return array<string, mixed>
     * @throws InvalidAuthCodeException
     */
    public function exchangeCodeForToken(
        string $shopDomain,
        string $clientId,
        string $clientSecret,
        string $authorizationCode
    ): array {
        return $this->tokenRequest($shopDomain, $clientId, $clientSecret, [
            'grant_type' => 'authorization_code',
            'code' => $authorizationCode,
        ], InvalidAuthCodeException::class);
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidRefreshTokenException
     */
    public function refreshAccessToken(
        string $shopDomain,
        string $clientId,
        string $clientSecret,
        string $refreshToken
    ): array {
        return $this->tokenRequest($shopDomain, $clientId, $clientSecret, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ], InvalidRefreshTokenException::class);
    }

    /**
     * @param array<string, string> $formParams
     * @param class-string<\Shoper\Sdk\Exception\AuthenticationException> $errorClass
     * @return array<string, mixed>
     */
    private function tokenRequest(
        string $shopDomain,
        string $clientId,
        string $clientSecret,
        array $formParams,
        string $errorClass
    ): array {
        $response = $this->http->post(rtrim($shopDomain, '/') . '/webapi/rest/oauth/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => $formParams,
        ]);
        $body = json_decode((string) $response->getBody(), true) ?: [];
        if ($response->getStatusCode() !== 200 || !isset($body['access_token'])) {
            $msg = $body['error_description'] ?? $body['error'] ?? 'OAuth token request failed';
            throw new $errorClass((string) $msg, $body);
        }
        return $body;
    }
}
