<?php
/**
 * PISTE API Client - Légifrance OAuth2 + HTTP wrapper
 *
 * Handles authentication and API calls to the Légifrance REST API
 * via the PISTE platform (piste.gouv.fr).
 *
 * Docs: https://piste.gouv.fr
 * API version: 2.4.2
 *
 * @package Constituant
 */

if (!defined('CONSTITUANT_APP')) {
    die('Direct access not allowed');
}

/**
 * Get an OAuth2 access token via client credentials flow.
 *
 * @return string Bearer access token
 * @throws RuntimeException on auth failure
 */
function getPisteAccessToken(): string
{
    if (!defined('PISTE_CLIENT_ID') || PISTE_CLIENT_ID === 'your-piste-oauth-client-id') {
        throw new RuntimeException(
            "PISTE credentials not configured. Set PISTE_CLIENT_ID and PISTE_CLIENT_SECRET in api-keys.php"
        );
    }

    $tokenUrl = (defined('PISTE_SANDBOX') && PISTE_SANDBOX)
        ? 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token'
        : 'https://oauth.piste.gouv.fr/api/oauth/token';

    // Debug: show which credentials and endpoint are in use
    $clientIdPreview = defined('PISTE_CLIENT_ID') ? substr(PISTE_CLIENT_ID, 0, 8) . '...' : 'NOT DEFINED';
    $sandboxMode     = (defined('PISTE_SANDBOX') && PISTE_SANDBOX) ? 'SANDBOX' : 'PRODUCTION';
    echo "  [DEBUG] PISTE mode: $sandboxMode | client_id: $clientIdPreview | endpoint: $tokenUrl\n";

    logMessage("PISTE: Requesting token from $tokenUrl (mode=$sandboxMode, client=$clientIdPreview)");

    $result = fetchUrl($tokenUrl, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => PISTE_CLIENT_ID,
            'client_secret' => PISTE_CLIENT_SECRET,
            'scope'         => 'openid',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT    => 15,
    ]);

    if (!$result['success']) {
        $detail = !empty($result['data']) ? ' — ' . substr($result['data'], 0, 500) : '';
        throw new RuntimeException("PISTE OAuth request failed: " . $result['error'] . $detail);
    }

    $payload = json_decode($result['data'], true);

    if (empty($payload['access_token'])) {
        throw new RuntimeException(
            "PISTE OAuth: no access_token in response. Got: " . substr($result['data'], 0, 300)
        );
    }

    $expiresIn = $payload['expires_in'] ?? '?';
    logMessage("PISTE: Token obtained, expires in {$expiresIn}s");

    return $payload['access_token'];
}

/**
 * Call a Légifrance API endpoint (POST with JSON body).
 *
 * @param string $endpoint  Path relative to base, e.g. "search" or "consult/lastNJo"
 * @param array  $body      Request body (will be JSON-encoded)
 * @param string $token     Bearer access token from getPisteAccessToken()
 * @return array            Decoded JSON response
 * @throws RuntimeException on HTTP or JSON error
 */
function callLegifranceApi(string $endpoint, array $body, string $token): array
{
    $base = (defined('PISTE_SANDBOX') && PISTE_SANDBOX)
        ? 'https://sandbox-api.piste.gouv.fr/dila/legifrance/lf-engine-app'
        : 'https://api.piste.gouv.fr/dila/legifrance/lf-engine-app';

    $url  = $base . '/' . ltrim($endpoint, '/');
    $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    logMessage("PISTE: POST $url (" . strlen($json) . " bytes)");

    $result = fetchUrl($url, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    if (!$result['success']) {
        // Include the response body — it usually contains the API's validation error message
        $detail = !empty($result['data']) ? ' — ' . substr($result['data'], 0, 500) : '';
        throw new RuntimeException("Légifrance API [$endpoint] failed: " . $result['error'] . $detail);
    }

    $data = json_decode($result['data'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException(
            "Légifrance API [$endpoint] returned invalid JSON: " . substr($result['data'], 0, 300)
        );
    }

    return $data;
}

/**
 * Ping the Légifrance API to verify connectivity.
 *
 * @param string $token  Bearer access token
 * @return bool
 */
function pingLegifranceApi(string $token): bool
{
    $base = (defined('PISTE_SANDBOX') && PISTE_SANDBOX)
        ? 'https://sandbox-api.piste.gouv.fr/dila/legifrance/lf-engine-app'
        : 'https://api.piste.gouv.fr/dila/legifrance/lf-engine-app';

    $result = fetchUrl($base . '/search/ping', [
        CURLOPT_HTTPGET    => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    return $result['success'] && trim($result['data']) === 'pong';
}