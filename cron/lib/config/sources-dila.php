<?php
/**
 * DILA Data Source Configuration
 *
 * Configuration for fetching legislative data from DILA (Direction de l'Information
 * Légale et Administrative) via data.gouv.fr CSV files.
 *
 * Data source: https://www.data.gouv.fr/fr/datasets/application-des-lois/
 * Updated daily by the Assemblée Nationale / LexImpact team
 *
 * @package Constituant
 */

if (!defined('CONSTITUANT_APP')) {
    die('Direct access not allowed');
}

// ============================================================================
// DILA DATA SOURCES
// ============================================================================

define('DILA_CONFIG', [
    'source_name' => 'dila-legifrance',
    'source_display_name' => 'DILA / Légifrance',
    'enabled' => true,
    'priority' => 1,

    // CSV URLs from data.gouv.fr
    'csv_urls' => [
        'laws' => 'https://www.data.gouv.fr/fr/datasets/r/35ca58b9-d02b-48c8-bc92-10c59abdbe9c',
        'measures' => 'https://www.data.gouv.fr/fr/datasets/r/e1938c3f-7a0c-46a6-8b1e-58109d7e6999',
    ],

    // Légifrance URL pattern for full text
    'legifrance_url_pattern' => 'https://www.legifrance.gouv.fr/loda/id/{id_loi}',

    // Assemblée Nationale dossier URL pattern
    'assemblee_url_pattern' => 'https://www.assemblee-nationale.fr/dyn/opendata/DOSSIER/{dossier_an_id}',
]);

// ============================================================================
// CSV COLUMN MAPPING
// ============================================================================

define('DILA_COLUMN_MAP', [
    // CSV header => Internal field name
    'Date de publication' => 'date_publication',
    'Titre de la loi' => 'titre',
    'Identifiant Légifrance' => 'id_loi',
    'Identifiant du dossier de l\'Assemblée nationale' => 'dossier_an_id',
    'Mots-clés associés' => 'mots_cles',
    'Taux d\'application des mesures' => 'taux_application',
    'Nombre de mesures attendues' => 'nb_mesures_attendues',
    'Nombre de mesures prises' => 'nb_mesures_prises',
    'Commission permanente compétente' => 'commission',
    'Législature' => 'legislature',
    'Type de loi' => 'type_loi',
    'Procédure' => 'procedure',
    'Numéro de la loi' => 'numero_loi',
]);

// Required fields for a valid law record
define('DILA_REQUIRED_FIELDS', ['id_loi', 'titre']);

// ============================================================================
// CACHE CONFIGURATION
// ============================================================================

define('DILA_CACHE_CONFIG', [
    // Cache directory (relative to cron/)
    'directory' => __DIR__ . '/../../cache/dila',

    // Cache time-to-live in seconds (24 hours)
    'ttl' => 86400,

    // Maximum age of cache files before cleanup (7 days)
    'max_age' => 604800,

    // Cache file naming pattern
    'filename_pattern' => 'dila_{type}_{date}.csv',
]);

// ============================================================================
// IMPORT SETTINGS
// ============================================================================

define('DILA_IMPORT_CONFIG', [
    // Only import laws from the last N days
    'days_lookback' => 90,

    // Maximum laws to process per run
    'max_laws_per_run' => 50,

    // Skip update if law was updated within N days
    'skip_if_updated_within_days' => 7,

    // Default chamber name
    'default_chamber' => 'Assemblée Nationale',

    // Default level
    'default_level' => 'france',

    // Sort order for laws (newest first)
    'sort_by' => 'date_publication',
    'sort_order' => 'DESC',
]);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get the DILA source configuration
 *
 * @return array DILA configuration
 */
function getDILAConfig(): array
{
    return DILA_CONFIG;
}

/**
 * Get CSV column mapping
 *
 * @return array Column mapping (csv_header => internal_field)
 */
function getDILAColumnMap(): array
{
    return DILA_COLUMN_MAP;
}

/**
 * Get cache configuration
 *
 * @return array Cache configuration
 */
function getDILACacheConfig(): array
{
    return DILA_CACHE_CONFIG;
}

/**
 * Get import configuration
 *
 * @return array Import settings
 */
function getDILAImportConfig(): array
{
    return DILA_IMPORT_CONFIG;
}

/**
 * Build Légifrance URL for a law
 *
 * @param string $idLoi Law identifier
 * @return string Full Légifrance URL
 */
function buildLegifranceUrl(string $idLoi): string
{
    return str_replace('{id_loi}', $idLoi, DILA_CONFIG['legifrance_url_pattern']);
}

/**
 * Build Assemblée Nationale dossier URL
 *
 * @param string $dossierId Dossier identifier
 * @return string|null Full URL or null if no dossier ID
 */
function buildAssembleeUrl(?string $dossierId): ?string
{
    if (empty($dossierId)) {
        return null;
    }
    return str_replace('{dossier_an_id}', $dossierId, DILA_CONFIG['assemblee_url_pattern']);
}

/**
 * Get cache file path for a given type and date
 *
 * @param string $type CSV type (laws, measures)
 * @param string|null $date Date string (Y-m-d) or null for today
 * @return string Full cache file path
 */
function getDILACachePath(string $type, ?string $date = null): string
{
    $config = DILA_CACHE_CONFIG;
    $date = $date ?? date('Y-m-d');

    $filename = str_replace(
        ['{type}', '{date}'],
        [$type, $date],
        $config['filename_pattern']
    );

    return $config['directory'] . '/' . $filename;
}

/**
 * Check if cache file is valid (exists and not expired)
 *
 * @param string $cachePath Path to cache file
 * @return bool True if cache is valid
 */
function isDILACacheValid(string $cachePath): bool
{
    if (!file_exists($cachePath)) {
        return false;
    }

    $fileAge = time() - filemtime($cachePath);
    return $fileAge < DILA_CACHE_CONFIG['ttl'];
}

/**
 * Get the cutoff date for law imports
 *
 * @return string Date string (Y-m-d)
 */
function getDILACutoffDate(): string
{
    $daysLookback = DILA_IMPORT_CONFIG['days_lookback'];
    return date('Y-m-d', strtotime("-{$daysLookback} days"));
}