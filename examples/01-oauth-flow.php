<?php
/**
 * Example 01 — Full AppStore OAuth flow.
 *
 * Pattern for an application-initiated browser flow. AppStore applications normally receive the
 * authorization code during the installation started by the shop; in every case the state returned
 * on the callback must be verified before the code is exchanged.
 *
 * Flow:
 *   1. Partner app builds authorization URL with a random state → redirects user to Shoper.
 *   2. User approves in Shoper admin → Shoper redirects back to app with ?code=...
 *   3. App exchanges code for access_token + refresh_token.
 *   4. App stores tokens and makes API calls.
 *   5. When access_token expires, app uses refresh_token to get a new pair.
 */
require __DIR__ . '/../vendor/autoload.php';

use Shoper\Sdk\OAuthManager;
use Shoper\Sdk\ShoperClient;

$shopDomain = 'https://my-shop.shoper.pl';
$clientId = getenv('SHOPER_CLIENT_ID') ?: 'your_client_id';
$clientSecret = getenv('SHOPER_CLIENT_SECRET') ?: 'your_client_secret';
$redirectUri = 'https://my-app.example.com/callback';

session_start();
$mgr = new OAuthManager();

if (!isset($_GET['code'])) {
    $state = OAuthManager::generateState();
    $_SESSION['shoper_oauth_state'] = $state;
    $authorizeUrl = $mgr->buildAuthorizationUrl($shopDomain, $clientId, $redirectUri, ['read', 'write'], $state);
    echo "Redirect user to: $authorizeUrl\n";
    exit;
}

$expectedState = $_SESSION['shoper_oauth_state'] ?? null;
unset($_SESSION['shoper_oauth_state']);
if (!OAuthManager::verifyState($expectedState, $_GET['state'] ?? null)) {
    http_response_code(400);
    echo "Invalid OAuth state.\n";
    exit;
}

$client = new ShoperClient($shopDomain);
$tokens = $client->authenticateOAuth($clientId, $clientSecret, (string) $_GET['code']);
echo "Got tokens: access_token=" . substr($tokens['access_token'], 0, 10) . "..., expires_in={$tokens['expires_in']}\n";

// Step 4: Make API calls.
$info = $client->restClient(); // use typed Fern REST client
// $products = $client->products()->list(['limit' => 10]);

// Step 5: Refresh when expired.
$newTokens = $client->refreshToken($clientId, $clientSecret, $tokens['refresh_token']);
echo "Refreshed. New access_token=" . substr($newTokens['access_token'], 0, 10) . "...\n";
