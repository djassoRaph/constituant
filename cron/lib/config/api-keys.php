<?php
/**
 * API Keys Configuration
 *
 * IMPORTANT: This file contains sensitive API keys and should NOT be committed to version control
 * Make sure api-keys.php is in .gitignore
 *
 * @package Constituant
 */

// Prevent direct access
if (!defined('CONSTITUANT_APP')) {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Mistral AI Configuration
define('MISTRAL_API_KEY', '');
define('MISTRAL_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL', 'mistral-small-latest');
define('MISTRAL_TIMEOUT', 30);

// Add other API keys here as needed
// define('OTHER_API_KEY', 'your-other-api-key-here');
