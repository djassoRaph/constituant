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

// PISTE / Légifrance API (https://piste.gouv.fr)
// OAuth2 client credentials for the Légifrance REST API
define('PISTE_CLIENT_ID',     'your-piste-oauth-client-id');
define('PISTE_CLIENT_SECRET', 'your-piste-oauth-client-secret');
define('PISTE_SANDBOX',       true); // Set to false for production
