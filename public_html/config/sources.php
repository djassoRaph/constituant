<?php
/**
 * Configuration for External Bill Data Sources
 *
 * Defines API endpoints, rate limits, and source-specific settings
 * for automated bill imports.
 *
 * @package Constituant
 */

// Prevent direct access
if (!defined('CONSTITUANT_APP')) {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Data source configurations
const BILL_SOURCES = [
    'nosdeputes' => [
        'name' => 'NosDéputés.fr',
        'enabled' => true,
        'priority' => 1, // Higher priority = fetch first
        'level' => 'france',
        'base_url' => 'https://www.nosdeputes.fr',
        'endpoints' => [
            'dossiers' => '/dossiers/date/json',
            'scrutins' => '/17/scrutins/json', // 17 = current legislature
            'search' => '/recherche/projets?format=json',
        ],
        'rate_limit' => [
            'requests_per_minute' => 30,
            'delay_seconds' => 2, // Delay between requests
        ],
        'timeout' => 30, // HTTP timeout in seconds
        'attribution' => 'Données issues de NosDéputés.fr (Licence ODbL)',
    ],

    'lafabrique' => [
        'name' => 'La Fabrique de la Loi',
        'enabled' => true,
        'priority' => 2,
        'level' => 'france',
        'base_url' => 'https://www.lafabriquedelaloi.fr',
        'endpoints' => [
            'dossiers' => '/api/dossiers.csv',
        ],
        'rate_limit' => [
            'requests_per_minute' => 20,
            'delay_seconds' => 3,
        ],
        'timeout' => 30,
        'attribution' => 'Source: La Fabrique de la Loi',
    ],

    'eu-parliament' => [
        'name' => 'European Parliament - Legislative Observatory',
        'enabled' => true,
        'priority' => 3,
        'level' => 'eu',
        'base_url' => 'https://data.europarl.europa.eu',
        'endpoints' => [
            'api' => '/api/v2/documents',
            'oeil_rss' => 'https://oeil.secure.europarl.europa.eu/oeil/rss/search.do',
        ],
        'rate_limit' => [
            'requests_per_minute' => 60,
            'delay_seconds' => 1,
        ],
        'timeout' => 30,
        'attribution' => 'Source: European Parliament Open Data Portal',
    ],

    'epdb' => [
        'name' => 'European Parliament Database (EPDB)',
        'enabled' => false, // Start disabled, enable after testing
        'priority' => 4,
        'level' => 'eu',
        'base_url' => 'http://api.epdb.eu',
        'endpoints' => [
            'documents' => '/ep/plenary/documents',
        ],
        'rate_limit' => [
            'requests_per_minute' => 30,
            'delay_seconds' => 2,
        ],
        'timeout' => 30,
        'attribution' => 'Source: EPDB.eu',
    ],
];

// Import settings
const IMPORT_SETTINGS = [
    // How many days back to fetch bills
    'fetch_days_back' => 90,

    // Maximum bills to import per run (prevent overload)
    'max_bills_per_source' => 50,

    // Automatically approve bills from trusted sources?
    'auto_approve' => false,

    // Time zone for date parsing
    'timezone' => 'Europe/Paris',

    // Log file location (relative to project root)
    'log_file' => '../logs/bill-imports.log',

    // Email notifications
    'notify_admin' => false, // Set to true to enable
    'admin_email' => 'admin@constituant.fr', // Update with your email

    // Default chamber names when not specified
    'default_chambers' => [
        'france' => 'Assemblée Nationale',
        'eu' => 'European Parliament',
    ],

    // Status update: automatically mark bills as completed after vote date?
    'auto_update_status' => true,
];

/**
 * Get enabled bill sources sorted by priority
 *
 * @return array Enabled sources
 */
function getEnabledSources(): array
{
    $enabled = array_filter(BILL_SOURCES, fn($source) => $source['enabled']);
    uasort($enabled, fn($a, $b) => $a['priority'] <=> $b['priority']);
    return $enabled;
}

/**
 * Get source configuration by key
 *
 * @param string $sourceKey Source identifier
 * @return array|null Source config or null if not found
 */
function getSourceConfig(string $sourceKey): ?array
{
    return BILL_SOURCES[$sourceKey] ?? null;
}

/**
 * Check if a source is enabled
 *
 * @param string $sourceKey Source identifier
 * @return bool True if enabled
 */
function isSourceEnabled(string $sourceKey): bool
{
    return BILL_SOURCES[$sourceKey]['enabled'] ?? false;
}
