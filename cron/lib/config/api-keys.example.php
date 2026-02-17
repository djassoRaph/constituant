<?php
/**
 * API Keys Configuration (Example)
 *
 * IMPORTANT: Copy this file to api-keys.php and add your real API keys
 * DO NOT commit api-keys.php to version control!
 *
 * @package Constituant
 */

// Prevent direct access
if (!defined('CONSTITUANT_APP')) {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Mistral AI Configuration
define('MISTRAL_API_KEY', 'your-mistral-api-key-here');
define('MISTRAL_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL', 'mistral-small-latest');
define('MISTRAL_TIMEOUT', 30);

// Légifrance API (PISTE/DILA) - OAuth 2.0 Client Credentials
// Register at https://piste.gouv.fr to get your credentials
define('LEGIFRANCE_CLIENT_ID',     getenv('LEGIFRANCE_CLIENT_ID')     ?: 'your-piste-client-id-here');
define('LEGIFRANCE_CLIENT_SECRET', getenv('LEGIFRANCE_CLIENT_SECRET') ?: 'your-piste-client-secret-here');
define('LEGIFRANCE_ENV',           getenv('LEGIFRANCE_ENV')           ?: 'sandbox'); // 'sandbox' or 'production'

// Légifrance API URLs (do not change these)
define('LEGIFRANCE_OAUTH_URL_PROD',    'https://oauth.piste.gouv.fr/api/oauth/token');
define('LEGIFRANCE_OAUTH_URL_SANDBOX', 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token');
define('LEGIFRANCE_API_URL_PROD',      'https://api.piste.gouv.fr/dila/legifrance/lf-engine-app');
define('LEGIFRANCE_API_URL_SANDBOX',   'https://sandbox-api.piste.gouv.fr/dila/legifrance/lf-engine-app');
