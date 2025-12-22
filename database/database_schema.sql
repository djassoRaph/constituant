-- ============================================================================
-- CONSTITUANT - Clean Database Schema v2.0
-- ============================================================================
-- Optimized for: Fully automated bill imports with AI classification
-- Zero human intervention required
-- ============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Drop existing tables if doing fresh install (CAREFUL!)
-- DROP TABLE IF EXISTS votes;
-- DROP TABLE IF EXISTS bills;
-- DROP TABLE IF EXISTS import_logs;

-- ============================================================================
-- TABLE 1: bills
-- ============================================================================
-- Single source of truth for all bills
-- Automatically populated by cron, no admin approval needed
-- ============================================================================

CREATE TABLE IF NOT EXISTS `bills` (
    -- Primary Key
    `id` VARCHAR(100) PRIMARY KEY COMMENT 'Unique ID: source-identifier-year',
    
    -- Core Content (What users see)
    `title` VARCHAR(500) NOT NULL COMMENT 'Bill title',
    `summary` TEXT COMMENT 'Original technical summary from API',
    `ai_summary` TEXT COMMENT 'Citizen-friendly summary from Mistral AI',
    `full_text_url` VARCHAR(1000) COMMENT 'Link to official legislation',
    
    -- Classification (from Mistral AI)
    `theme` VARCHAR(100) NOT NULL DEFAULT 'Sans catégorie' COMMENT 'AI-classified theme',
    `ai_confidence` DECIMAL(3,2) COMMENT 'AI confidence score (0.00-1.00)',
    
    -- Legislative Context
    `level` ENUM('france', 'eu') NOT NULL COMMENT 'Legislative level',
    `chamber` VARCHAR(100) COMMENT 'Chamber (Assemblée Nationale, Sénat, European Parliament)',
    `vote_datetime` DATETIME NOT NULL COMMENT 'Official parliamentary vote date',
    
    -- Vote Counts (denormalized for performance)
    `votes_for` INT UNSIGNED DEFAULT 0 COMMENT 'Number of FOR votes',
    `votes_against` INT UNSIGNED DEFAULT 0 COMMENT 'Number of AGAINST votes',
    `votes_abstain` INT UNSIGNED DEFAULT 0 COMMENT 'Number of ABSTAIN votes',
    `votes_total` INT UNSIGNED DEFAULT 0 COMMENT 'Total votes cast',
    
    -- Status (auto-calculated based on vote_datetime)
    `status` ENUM('upcoming', 'voting_now', 'completed') DEFAULT 'upcoming' COMMENT 'Vote status',
    
    -- Metadata
    `source` VARCHAR(50) NOT NULL COMMENT 'Data source (nosdeputes, lafabrique, eu-parliament)',
    `external_id` VARCHAR(100) NOT NULL COMMENT 'ID from external source',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When bill was imported',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update',
    `ai_processed_at` TIMESTAMP NULL COMMENT 'When AI classification completed',
    
    -- Indexes for performance
    INDEX `idx_status` (`status`),
    INDEX `idx_level` (`level`),
    INDEX `idx_theme` (`theme`),
    INDEX `idx_vote_datetime` (`vote_datetime`),
    INDEX `idx_source_external` (`source`, `external_id`),
    INDEX `idx_active_bills` (`status`, `vote_datetime`),
    
    -- Prevent duplicate imports from same source
    UNIQUE KEY `unique_source_bill` (`source`, `external_id`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Published legislative bills';

-- ============================================================================
-- TABLE 2: votes
-- ============================================================================
-- Citizen votes on bills
-- One vote per IP per bill (prevents double voting)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `votes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Vote Data
    `bill_id` VARCHAR(100) NOT NULL COMMENT 'Reference to bill',
    `vote_type` ENUM('for', 'against', 'abstain') NOT NULL COMMENT 'Type of vote',
    
    -- Voter Identification (anonymous)
    `voter_ip` VARCHAR(45) NOT NULL COMMENT 'Voter IP address (IPv4/IPv6)',
    `user_agent` VARCHAR(255) COMMENT 'Browser user agent',
    
    -- Timestamp
    `voted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When vote was cast',
    
    -- Foreign Key
    FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`) ON DELETE CASCADE,
    
    -- Prevent double voting
    UNIQUE KEY `unique_vote` (`bill_id`, `voter_ip`),
    
    -- Indexes
    INDEX `idx_bill_id` (`bill_id`),
    INDEX `idx_voted_at` (`voted_at`),
    INDEX `idx_voter_ip` (`voter_ip`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Citizen votes';

-- ============================================================================
-- TABLE 3: import_logs
-- ============================================================================
-- Track import operations for monitoring
-- Optional but useful for debugging
-- ============================================================================

CREATE TABLE IF NOT EXISTS `import_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Import Details
    `source` VARCHAR(50) NOT NULL COMMENT 'Data source name',
    `status` ENUM('success', 'partial', 'failed') NOT NULL COMMENT 'Import status',
    
    -- Statistics
    `bills_fetched` INT UNSIGNED DEFAULT 0 COMMENT 'Bills found in API',
    `bills_new` INT UNSIGNED DEFAULT 0 COMMENT 'New bills inserted',
    `bills_updated` INT UNSIGNED DEFAULT 0 COMMENT 'Existing bills updated',
    `bills_skipped` INT UNSIGNED DEFAULT 0 COMMENT 'Bills skipped',
    `errors_count` INT UNSIGNED DEFAULT 0 COMMENT 'Number of errors',
    
    -- Performance
    `execution_time` DECIMAL(8,2) COMMENT 'Execution time in seconds',
    
    -- Error Details
    `error_messages` TEXT COMMENT 'Error messages (JSON array)',
    
    -- Timestamp
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Import start time',
    `completed_at` TIMESTAMP NULL COMMENT 'Import completion time',
    
    -- Indexes
    INDEX `idx_source` (`source`),
    INDEX `idx_started_at` (`started_at`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Import operation logs';

-- ============================================================================
-- TRIGGERS
-- ============================================================================
-- Automatically update vote counts when votes are cast
-- ============================================================================

DELIMITER //

-- Increment vote counts when vote is inserted
CREATE TRIGGER IF NOT EXISTS `after_vote_insert`
AFTER INSERT ON `votes`
FOR EACH ROW
BEGIN
    UPDATE `bills`
    SET 
        `votes_for` = `votes_for` + IF(NEW.vote_type = 'for', 1, 0),
        `votes_against` = `votes_against` + IF(NEW.vote_type = 'against', 1, 0),
        `votes_abstain` = `votes_abstain` + IF(NEW.vote_type = 'abstain', 1, 0),
        `votes_total` = `votes_total` + 1
    WHERE `id` = NEW.bill_id;
END//

-- Update vote counts when vote is changed
CREATE TRIGGER IF NOT EXISTS `after_vote_update`
AFTER UPDATE ON `votes`
FOR EACH ROW
BEGIN
    -- Decrement old vote type
    UPDATE `bills`
    SET 
        `votes_for` = `votes_for` - IF(OLD.vote_type = 'for', 1, 0),
        `votes_against` = `votes_against` - IF(OLD.vote_type = 'against', 1, 0),
        `votes_abstain` = `votes_abstain` - IF(OLD.vote_type = 'abstain', 1, 0)
    WHERE `id` = OLD.bill_id;
    
    -- Increment new vote type
    UPDATE `bills`
    SET 
        `votes_for` = `votes_for` + IF(NEW.vote_type = 'for', 1, 0),
        `votes_against` = `votes_against` + IF(NEW.vote_type = 'against', 1, 0),
        `votes_abstain` = `votes_abstain` + IF(NEW.vote_type = 'abstain', 1, 0)
    WHERE `id` = NEW.bill_id;
END//

-- Decrement vote counts when vote is deleted
CREATE TRIGGER IF NOT EXISTS `after_vote_delete`
AFTER DELETE ON `votes`
FOR EACH ROW
BEGIN
    UPDATE `bills`
    SET 
        `votes_for` = `votes_for` - IF(OLD.vote_type = 'for', 1, 0),
        `votes_against` = `votes_against` - IF(OLD.vote_type = 'against', 1, 0),
        `votes_abstain` = `votes_abstain` - IF(OLD.vote_type = 'abstain', 1, 0),
        `votes_total` = `votes_total` - 1
    WHERE `id` = OLD.bill_id;
END//

DELIMITER ;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER //

-- Auto-update bill status based on vote_datetime
CREATE PROCEDURE IF NOT EXISTS `update_bill_statuses`()
BEGIN
    DECLARE now_time DATETIME;
    SET now_time = NOW();
    
    -- Mark bills as 'completed' if vote_datetime has passed
    UPDATE `bills`
    SET `status` = 'completed'
    WHERE `vote_datetime` < now_time
    AND `status` != 'completed';
    
    -- Mark bills as 'voting_now' if vote is within 7 days
    UPDATE `bills`
    SET `status` = 'voting_now'
    WHERE `vote_datetime` >= now_time
    AND `vote_datetime` <= DATE_ADD(now_time, INTERVAL 7 DAY)
    AND `status` != 'voting_now'
    AND `status` != 'completed';
    
    -- Mark future bills as 'upcoming'
    UPDATE `bills`
    SET `status` = 'upcoming'
    WHERE `vote_datetime` > DATE_ADD(now_time, INTERVAL 7 DAY)
    AND `status` != 'upcoming';
END//

DELIMITER ;

-- ============================================================================
-- EVENTS (MySQL scheduled tasks)
-- ============================================================================
-- Automatically update bill statuses every hour
-- Requires: SET GLOBAL event_scheduler = ON;
-- ============================================================================

-- Enable event scheduler (run once after install)
-- SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS `auto_update_bill_statuses`
ON SCHEDULE EVERY 1 HOUR
DO CALL update_bill_statuses();

-- ============================================================================
-- USEFUL QUERIES
-- ============================================================================

-- View all active bills with vote counts
-- SELECT 
--     id, title, theme, level, vote_datetime, status,
--     votes_for, votes_against, votes_abstain, votes_total
-- FROM bills
-- WHERE status IN ('upcoming', 'voting_now')
-- ORDER BY vote_datetime ASC;

-- View import statistics by source
-- SELECT 
--     source,
--     COUNT(*) as total_imports,
--     SUM(bills_new) as total_bills_added,
--     AVG(execution_time) as avg_execution_time,
--     MAX(started_at) as last_import
-- FROM import_logs
-- GROUP BY source;

-- View bills by theme
-- SELECT 
--     theme,
--     level,
--     COUNT(*) as bill_count,
--     SUM(votes_total) as total_votes
-- FROM bills
-- WHERE status IN ('upcoming', 'voting_now')
-- GROUP BY theme, level
-- ORDER BY bill_count DESC;

-- Check for duplicate bills (should be empty)
-- SELECT source, external_id, COUNT(*) as count
-- FROM bills
-- GROUP BY source, external_id
-- HAVING count > 1;

-- ============================================================================
-- VERIFICATION
-- ============================================================================

SELECT 'Database schema created successfully!' AS Status;
SELECT 'Tables created:' AS Info;
SHOW TABLES;

-- Display table information
SELECT 
    TABLE_NAME as 'Table',
    TABLE_ROWS as 'Rows',
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 2) as 'Size (KB)',
    TABLE_COMMENT as 'Description'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

SELECT 'Run this to enable automatic status updates:' AS Reminder;
SELECT 'SET GLOBAL event_scheduler = ON;' AS Command;

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
