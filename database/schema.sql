-- ============================================================================
-- Constituant - Complete Database Schema
-- Version: 0.2.0
-- MySQL 5.7+ / MariaDB 10.2+
-- ============================================================================
--
-- This file creates the complete database structure for the Constituant
-- platform, including all tables, indexes, and initial data.
--
-- Execute this file to set up a fresh database installation:
--   mysql -u username -p database_name < database/schema.sql
--
-- ============================================================================

-- Create database (uncomment if needed)
-- CREATE DATABASE IF NOT EXISTS constituant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE constituant;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================================================
-- Table: bills
-- Stores approved legislative bills available for public voting
-- ============================================================================
CREATE TABLE IF NOT EXISTS `bills` (
    `id` VARCHAR(50) PRIMARY KEY COMMENT 'Unique identifier (e.g., eu-dsa-2024)',
    `title` VARCHAR(500) NOT NULL COMMENT 'Bill title',
    `summary` TEXT NOT NULL COMMENT 'Brief description of the bill',
    `full_text_url` VARCHAR(500) COMMENT 'Link to official legislation text',
    `level` ENUM('eu', 'france') NOT NULL COMMENT 'Legislative level',
    `chamber` VARCHAR(100) COMMENT 'Chamber name (e.g., European Parliament, Assemblée Nationale)',
    `theme` VARCHAR(100) DEFAULT 'Sans catégorie' COMMENT 'Legislative category from AI classification',
    `ai_summary` TEXT NULL COMMENT 'Plain-language explanation from Mistral AI',
    `ai_processed_at` TIMESTAMP NULL COMMENT 'Timestamp when AI analysis was performed',
    `vote_datetime` DATETIME NOT NULL COMMENT 'When the official vote takes place',
    `status` ENUM('upcoming', 'voting_now', 'completed') DEFAULT 'upcoming' COMMENT 'Current status',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_level` (`level`),
    INDEX `idx_status` (`status`),
    INDEX `idx_theme` (`theme`),
    INDEX `idx_vote_date` (`vote_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Published legislative bills available for voting';

-- ============================================================================
-- Table: votes
-- Stores citizen votes on bills (one vote per IP per bill)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `votes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bill_id` VARCHAR(50) NOT NULL COMMENT 'Reference to bill',
    `vote_type` ENUM('for', 'against', 'abstain') NOT NULL COMMENT 'Type of vote cast',
    `voter_ip` VARCHAR(45) NOT NULL COMMENT 'Voter IP address (IPv4 or IPv6)',
    `user_agent` VARCHAR(255) COMMENT 'Browser user agent for analytics',
    `voted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_vote` (`bill_id`, `voter_ip`) COMMENT 'Prevent double voting',
    INDEX `idx_bill` (`bill_id`),
    INDEX `idx_voted_at` (`voted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Citizen votes on bills';

-- ============================================================================
-- Table: pending_bills
-- Stores bills fetched from external APIs awaiting admin approval
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pending_bills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `external_id` VARCHAR(100) NOT NULL COMMENT 'ID from external source (e.g., dossier number)',
    `source` VARCHAR(50) NOT NULL COMMENT 'Data source (nosdeputes, lafabrique, eu-parliament)',
    `title` VARCHAR(500) NOT NULL COMMENT 'Bill title',
    `summary` TEXT COMMENT 'Bill description/summary',
    `full_text_url` VARCHAR(500) COMMENT 'Link to official text',
    `level` ENUM('eu', 'france') NOT NULL COMMENT 'Legislative level',
    `chamber` VARCHAR(100) COMMENT 'Chamber name',
    `theme` VARCHAR(100) DEFAULT 'Sans catégorie' COMMENT 'Legislative category from AI classification',
    `ai_summary` TEXT NULL COMMENT 'Plain-language explanation from Mistral AI',
    `ai_processed_at` TIMESTAMP NULL COMMENT 'Timestamp when AI analysis was performed',
    `vote_datetime` DATETIME COMMENT 'Official vote date/time',
    `raw_data` JSON COMMENT 'Original API response for debugging',
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'Review status',
    `fetched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When bill was fetched',
    `reviewed_at` TIMESTAMP NULL COMMENT 'When admin reviewed',
    `reviewed_by` VARCHAR(100) COMMENT 'Admin username who reviewed',
    `notes` TEXT COMMENT 'Admin notes',

    UNIQUE KEY `unique_external` (`source`, `external_id`) COMMENT 'Prevent duplicate imports',
    INDEX `idx_source` (`source`),
    INDEX `idx_status` (`status`),
    INDEX `idx_level` (`level`),
    INDEX `idx_theme` (`theme`),
    INDEX `idx_fetched_at` (`fetched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pending bills from automated imports';

-- ============================================================================
-- Table: import_logs
-- Tracks automated import operations for monitoring and debugging
-- ============================================================================
CREATE TABLE IF NOT EXISTS `import_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `source` VARCHAR(50) NOT NULL COMMENT 'Data source name (nosdeputes, lafabrique, eu-parliament)',
    `status` ENUM('success', 'partial', 'failed') NOT NULL COMMENT 'Import result',
    `bills_fetched` INT DEFAULT 0 COMMENT 'Number of bills retrieved from source',
    `bills_new` INT DEFAULT 0 COMMENT 'Number of new bills added to pending_bills',
    `bills_updated` INT DEFAULT 0 COMMENT 'Number of existing bills updated',
    `errors` TEXT COMMENT 'Error messages if any occurred',
    `execution_time` FLOAT COMMENT 'Time taken in seconds',
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,

    INDEX `idx_source` (`source`),
    INDEX `idx_status` (`status`),
    INDEX `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Import operation audit logs';

-- ============================================================================
-- Sample Data for Testing
-- ============================================================================

-- Sample bills with AI classification
INSERT INTO `bills` (`id`, `title`, `summary`, `ai_summary`, `theme`, `level`, `chamber`, `vote_datetime`, `status`, `full_text_url`, `ai_processed_at`) VALUES
(
    'eu-dsa-2024',
    'Digital Services Act - Amendment 247',
    'Requires online platforms to remove illegal content within 24 hours or face fines up to 6% of global revenue. Includes provisions for appeal mechanisms and transparency reporting.',
    'Cette loi impose aux grandes plateformes en ligne de retirer les contenus illégaux sous 24 heures sous peine d''amendes pouvant atteindre 6% de leur chiffre d''affaires mondial. Elle introduit aussi des mécanismes de recours pour les utilisateurs et des obligations de transparence.',
    'Numérique',
    'eu',
    'European Parliament',
    '2024-12-15 14:00:00',
    'upcoming',
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:52020PC0825',
    CURRENT_TIMESTAMP
),
(
    'eu-carbon-2024',
    'Carbon Border Adjustment Mechanism',
    'Imposes carbon tax on imports from countries with weaker climate policies. Aims to prevent carbon leakage and level the playing field for EU businesses.',
    'Ce mécanisme instaure une taxe carbone aux frontières de l''UE sur les importations en provenance de pays ayant des politiques climatiques moins strictes. L''objectif est d''éviter les fuites de carbone et de garantir une concurrence équitable pour les entreprises européennes.',
    'Environnement & Énergie',
    'eu',
    'European Parliament',
    '2024-12-16 10:00:00',
    'upcoming',
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:52021PC0564',
    CURRENT_TIMESTAMP
),
(
    'fr-retirement-2024',
    'Réforme des retraites - Article 7',
    'Indexation des pensions sur l''inflation avec un plancher de 1.1% par an. Concerne 14 millions de retraités.',
    'Cet article garantit que les pensions de retraite seront revalorisées chaque année au minimum de 1,1%, même si l''inflation est inférieure. Cette mesure protège le pouvoir d''achat des 14 millions de retraités français.',
    'Affaires sociales',
    'france',
    'Assemblée Nationale',
    '2024-12-15 16:00:00',
    'upcoming',
    'https://www.assemblee-nationale.fr/dyn/16/textes/l16b0449_projet-loi',
    CURRENT_TIMESTAMP
),
(
    'fr-renewable-2024',
    'Loi énergies renouvelables',
    'Accélération des procédures d''autorisation pour les parcs éoliens et solaires. Objectif: 50% d''énergies renouvelables d''ici 2030.',
    'Cette loi simplifie et accélère les démarches administratives pour installer des parcs éoliens et solaires. L''objectif est d''atteindre 50% d''énergie renouvelable dans le mix énergétique français d''ici 2030, contribuant ainsi à la transition écologique.',
    'Environnement & Énergie',
    'france',
    'Assemblée Nationale',
    '2024-12-17 15:00:00',
    'upcoming',
    'https://www.assemblee-nationale.fr/dyn/16/textes/l16b0465_projet-loi',
    CURRENT_TIMESTAMP
);

-- Sample votes for testing (optional - uncomment to add test votes)
-- INSERT INTO `votes` (`bill_id`, `vote_type`, `voter_ip`, `user_agent`) VALUES
-- ('eu-dsa-2024', 'for', '192.168.1.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
-- ('eu-dsa-2024', 'for', '192.168.1.2', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'),
-- ('eu-dsa-2024', 'against', '192.168.1.3', 'Mozilla/5.0 (X11; Linux x86_64)'),
-- ('eu-carbon-2024', 'for', '192.168.1.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
-- ('fr-retirement-2024', 'for', '192.168.1.2', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'),
-- ('fr-retirement-2024', 'for', '192.168.1.4', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X)');

-- ============================================================================
-- Useful Administrative Queries
-- ============================================================================

-- View all bills with vote counts
-- SELECT
--     b.id,
--     b.title,
--     b.theme,
--     b.level,
--     b.vote_datetime,
--     b.status,
--     COUNT(v.id) as total_votes,
--     SUM(CASE WHEN v.vote_type = 'for' THEN 1 ELSE 0 END) as votes_for,
--     SUM(CASE WHEN v.vote_type = 'against' THEN 1 ELSE 0 END) as votes_against,
--     SUM(CASE WHEN v.vote_type = 'abstain' THEN 1 ELSE 0 END) as votes_abstain
-- FROM bills b
-- LEFT JOIN votes v ON b.id = v.bill_id
-- GROUP BY b.id
-- ORDER BY b.vote_datetime ASC;

-- Check pending bills by source
-- SELECT
--     source,
--     status,
--     COUNT(*) as count,
--     MIN(fetched_at) as first_fetch,
--     MAX(fetched_at) as last_fetch
-- FROM pending_bills
-- GROUP BY source, status
-- ORDER BY source, status;

-- View recent import operations
-- SELECT
--     source,
--     status,
--     bills_fetched,
--     bills_new,
--     bills_updated,
--     ROUND(execution_time, 2) as exec_time_sec,
--     started_at
-- FROM import_logs
-- ORDER BY started_at DESC
-- LIMIT 20;

-- Bills by theme distribution
-- SELECT
--     theme,
--     COUNT(*) as count,
--     level
-- FROM bills
-- GROUP BY theme, level
-- ORDER BY count DESC;

-- AI classification success rate (pending_bills)
-- SELECT
--     source,
--     COUNT(*) as total_bills,
--     SUM(CASE WHEN ai_processed_at IS NOT NULL THEN 1 ELSE 0 END) as ai_classified,
--     ROUND(SUM(CASE WHEN ai_processed_at IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as success_rate_pct
-- FROM pending_bills
-- GROUP BY source;

-- Find duplicate votes (should be empty due to unique constraint)
-- SELECT bill_id, voter_ip, COUNT(*) as vote_count
-- FROM votes
-- GROUP BY bill_id, voter_ip
-- HAVING vote_count > 1;

-- ============================================================================
-- Schema Verification
-- ============================================================================

-- Display created tables
SELECT 'Database schema created successfully!' AS Status;
SELECT 'Tables created:' AS Info;
SHOW TABLES;

-- Display table sizes
SELECT
    TABLE_NAME as 'Table',
    TABLE_ROWS as 'Rows',
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 2) as 'Size (KB)',
    TABLE_COMMENT as 'Description'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;
