<?php
/**
 * Application Configuration - EXAMPLE
 *
 * Copy this file to config.php and update the values.
 * DO NOT commit config.php with real credentials to version control!
 *
 * @package Constituant
 */

// Define application constant to prevent direct file access
define('CONSTITUANT_APP', true);

// Application information
define('SITE_NAME', 'Constituant');
define('SITE_TAGLINE', 'Votre voix sur les lois du jour');
define('SITE_URL', 'https://yourdomain.com'); // UPDATE THIS!
define('SITE_VERSION', '1.0.0');

// Admin credentials
// IMPORTANT: Change this password immediately!
define('ADMIN_PASSWORD', 'constituant2024'); // CHANGE THIS!

// Timezone configuration
define('TIMEZONE', 'Europe/Paris');
date_default_timezone_set(TIMEZONE);

// Security settings
define('CSRF_TOKEN_NAME', 'constituant_csrf_token');
define('SESSION_NAME', 'constituant_session');

// Rate limiting (votes per hour per IP)
define('VOTE_RATE_LIMIT', 10);
define('VOTE_RATE_WINDOW', 3600); // 1 hour in seconds

// API settings
define('API_RESPONSE_JSON', true);
define('API_CORS_ENABLED', false); // Enable if frontend is on different domain

// Error reporting
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
    // Development environment
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    define('ENVIRONMENT', 'development');
} else {
    // Production environment
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    define('ENVIRONMENT', 'production');
}

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', '1');
}

/**
 * Start session securely
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

/**
 * Generate CSRF token
 *
 * @return string CSRF token
 */
function generateCsrfToken(): string
{
    startSession();

    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }

    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 *
 * @param string|null $token Token to verify
 * @return bool True if valid
 */
function verifyCsrfToken(?string $token): bool
{
    startSession();

    return isset($_SESSION[CSRF_TOKEN_NAME]) &&
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token ?? '');
}

/**
 * Send JSON response
 *
 * @param mixed $data Data to send
 * @param int $statusCode HTTP status code
 */
function sendJsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');

    if (API_CORS_ENABLED) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send error response
 *
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 */
function sendErrorResponse(string $message, int $statusCode = 400): void
{
    sendJsonResponse([
        'success' => false,
        'error' => $message
    ], $statusCode);
}

/**
 * Validate admin password
 *
 * @param string|null $password Password to check
 * @return bool True if valid
 */
function validateAdminPassword(?string $password): bool
{
    return !empty($password) && hash_equals(ADMIN_PASSWORD, $password);
}

/**
 * Check if user is logged in as admin
 *
 * @return bool True if logged in
 */
function isAdminLoggedIn(): bool
{
    startSession();
    return !empty($_SESSION['admin_logged_in']);
}

/**
 * Log in admin
 */
function loginAdmin(): void
{
    startSession();
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_login_time'] = time();
}

/**
 * Log out admin
 */
function logoutAdmin(): void
{
    startSession();
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_login_time']);
    session_destroy();
}

/**
 * Format datetime for display
 *
 * @param string $datetime Datetime string
 * @param string $format PHP date format
 * @return string Formatted datetime
 */
function formatDateTime(string $datetime, string $format = 'd M Y, H:i'): string
{
    $dt = new DateTime($datetime, new DateTimeZone(TIMEZONE));
    return $dt->format($format);
}

/**
 * Get time until vote
 *
 * @param string $voteDatetime Vote datetime
 * @return array ['is_soon' => bool, 'label' => string, 'urgency' => string]
 */
function getVoteUrgency(string $voteDatetime): array
{
    $now = new DateTime('now', new DateTimeZone(TIMEZONE));
    $voteTime = new DateTime($voteDatetime, new DateTimeZone(TIMEZONE));
    $diff = $now->diff($voteTime);

    if ($diff->invert) {
        return [
            'is_soon' => false,
            'label' => 'Terminé',
            'urgency' => 'past'
        ];
    }

    $hoursUntil = ($diff->days * 24) + $diff->h;

    if ($hoursUntil < 24) {
        return [
            'is_soon' => true,
            'label' => 'Vote aujourd\'hui',
            'urgency' => 'urgent'
        ];
    }

    if ($diff->days < 7) {
        return [
            'is_soon' => true,
            'label' => 'Vote cette semaine',
            'urgency' => 'soon'
        ];
    }

    return [
        'is_soon' => false,
        'label' => 'Vote prévu',
        'urgency' => 'future'
    ];
}
