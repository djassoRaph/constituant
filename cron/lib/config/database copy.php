<?php
/**
 * Database Connection Configuration - EXAMPLE
 *
 * Copy this file to database.php and fill in your credentials.
 * DO NOT commit database.php to version control!
 *
 * @package Constituant
 */

// Prevent direct access
if (!defined('CONSTITUANT_APP')) {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Database configuration
// Update these values with your actual database credentials
const DB_HOST = 'localhost';        // Database host (usually localhost)
const DB_NAME = 'rera8347_constituant';      // Database name
const DB_USER = 'rera8347_c0ns717u4nt_Admin_1337';     // Database username
const DB_PASS = 'K7Ub;}_d;;Day2.'; // Database password
const DB_CHARSET = 'utf8mb4';       // Character set (do not change)

/**
 * Get database connection
 *
 * @return PDO Database connection
 * @throws PDOException If connection fails
 */
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            throw new PDOException('Unable to connect to database. Please try again later.');
        }
    }

    return $pdo;
}

/**
 * Execute a prepared statement and return results
 *
 * @param string $query SQL query with placeholders
 * @param array $params Parameters to bind
 * @return PDOStatement Executed statement
 */
function dbQuery(string $query, array $params = []): PDOStatement
{
    $pdo = getDbConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Get user's IP address (handles proxies and load balancers)
 *
 * @return string IP address
 */
function getUserIP(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];

            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Sanitize output to prevent XSS attacks
 *
 * @param mixed $data Data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeOutput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }

    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}
