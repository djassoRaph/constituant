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
    // =========================================================================
    // PRIMARY SOURCE: Légifrance (DILA/PISTE) - Official French government API
    // Replaced NosDéputés, La Fabrique, and EU Parliament in February 2026
    // =========================================================================
    'legifrance' => [
        'name' => 'Légifrance (DILA/PISTE)',
        'enabled' => true,
        'priority' => 1,
        'level' => 'france',
        'token_cache_file' => __DIR__ . '/../../cache/legifrance_token.json',
        'rate_limit' => [
            'requests_per_minute' => 30,
            'delay_seconds' => 1,
        ],
        'timeout' => 30,
        'max_bills_per_run' => 50,
        'search_days_back' => 90,  // Search for laws published in last 90 days
        'attribution' => 'Source: Légifrance (DILA) - Licence Ouverte 2.0',
    ],

    // =========================================================================
    // DEPRECATED SOURCES - Replaced by Légifrance API (February 2026)
    // Kept for reference, do not re-enable
    // =========================================================================
    'nosdeputes' => [
        'name' => 'NosDéputés.fr',
        'enabled' => false, // DEPRECATED: Replaced by Légifrance API (2026)
        'priority' => 10,
        'level' => 'france',
        'base_url' => 'https://www.nosdeputes.fr',
        'endpoints' => [
            'dossiers' => '/dossiers/date/json',
            'scrutins' => '/16/scrutins/json',
            'search' => '/recherche/projets?format=json',
        ],
        'rate_limit' => [
            'requests_per_minute' => 30,
            'delay_seconds' => 2,
        ],
        'timeout' => 30,
        'attribution' => 'Données issues de NosDéputés.fr (Licence ODbL)',
    ],

    'lafabrique' => [
        'name' => 'La Fabrique de la Loi',
        'enabled' => false, // DEPRECATED: Replaced by Légifrance API (2026)
        'priority' => 11,
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
        'enabled' => false, // DEPRECATED: API broken (406 errors)
        'priority' => 12,
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
        'enabled' => false, // DEPRECATED
        'priority' => 13,
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
    'log_file' => 'logs/bill-imports.log',

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
