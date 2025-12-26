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

// Add other API keys here as needed
// define('OTHER_API_KEY', 'your-other-api-key-here');
