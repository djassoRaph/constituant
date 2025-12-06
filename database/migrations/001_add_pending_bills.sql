-- Migration: Add pending_bills table for automated bill imports
-- Created: 2024-12-06
-- Purpose: Store bills fetched from external APIs before admin approval

-- ============================================================================
-- Table: pending_bills
-- Stores bills awaiting admin review/approval
-- ============================================================================
CREATE TABLE IF NOT EXISTS pending_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    external_id VARCHAR(100) NOT NULL COMMENT 'ID from external source (e.g., dossier number)',
    source VARCHAR(50) NOT NULL COMMENT 'Data source (nosdeputes, eu-parliament, lafabrique)',
    title VARCHAR(500) NOT NULL COMMENT 'Bill title',
    summary TEXT COMMENT 'Bill description/summary',
    full_text_url VARCHAR(500) COMMENT 'Link to official text',
    level ENUM('eu', 'france') NOT NULL COMMENT 'Legislative level',
    chamber VARCHAR(100) COMMENT 'Chamber name',
    vote_datetime DATETIME COMMENT 'Official vote date/time',
    raw_data JSON COMMENT 'Original API response for debugging',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'Review status',
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When bill was fetched',
    reviewed_at TIMESTAMP NULL COMMENT 'When admin reviewed',
    reviewed_by VARCHAR(100) COMMENT 'Admin username who reviewed',
    notes TEXT COMMENT 'Admin notes',

    UNIQUE KEY unique_external (source, external_id) COMMENT 'Prevent duplicate imports',
    INDEX idx_source (source),
    INDEX idx_status (status),
    INDEX idx_level (level),
    INDEX idx_fetched_at (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pending bills from automated imports';

-- ============================================================================
-- Table: import_logs
-- Tracks import operations for monitoring and debugging
-- ============================================================================
CREATE TABLE IF NOT EXISTS import_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(50) NOT NULL COMMENT 'Data source name',
    status ENUM('success', 'partial', 'failed') NOT NULL COMMENT 'Import result',
    bills_fetched INT DEFAULT 0 COMMENT 'Number of bills retrieved',
    bills_new INT DEFAULT 0 COMMENT 'Number of new bills added',
    bills_updated INT DEFAULT 0 COMMENT 'Number of existing bills updated',
    errors TEXT COMMENT 'Error messages if any',
    execution_time FLOAT COMMENT 'Time taken in seconds',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,

    INDEX idx_source (source),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Import operation logs';

-- ============================================================================
-- Initial data / Test verification
-- ============================================================================
-- Verify tables were created
SELECT 'pending_bills table created' as status;
SELECT 'import_logs table created' as status;
