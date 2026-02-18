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
define('MISTRAL_API_KEY', 'GOiylxokRtGnlBvwVKX0e0fDYKxBkSIN');
define('MISTRAL_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL', 'mistral-small-latest');
define('MISTRAL_TIMEOUT', 30);

// PISTE / Légifrance API (https://piste.gouv.fr)
// OAuth2 client credentials for the Légifrance REST API
define('PISTE_CLIENT_ID',     '6b6665e4-bd3f-4e77-ace4-4db610d49110');
define('PISTE_CLIENT_SECRET', 'bfe9bdd0-e7b4-421f-9f2c-f129b87a1170');
define('PISTE_SANDBOX',       false); // Set to false for production
