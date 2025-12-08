-- ============================================================================
-- Migration: Add AI Classification Support to pending_bills table
-- Database: rera8347_constituant
-- Date: 2025-12-08
-- Description: Adds theme, ai_summary, and ai_processed_at columns for
--              Mistral AI integration
-- ============================================================================

-- Start transaction for safety
START TRANSACTION;

-- Display current database
SELECT 'Starting migration: Adding AI classification columns...' AS Status;

-- Step 1: Add new columns to pending_bills table
ALTER TABLE `pending_bills`
ADD COLUMN `theme` VARCHAR(100) DEFAULT 'Sans catégorie' COMMENT 'Legislative category from AI classification',
ADD COLUMN `ai_summary` TEXT NULL COMMENT 'Plain-language explanation from Mistral AI',
ADD COLUMN `ai_processed_at` TIMESTAMP NULL COMMENT 'Timestamp when AI analysis was performed';

SELECT 'Columns added successfully.' AS Status;

-- Step 2: Update any NULL theme values (safety measure for existing records)
UPDATE `pending_bills`
SET `theme` = 'Sans catégorie'
WHERE `theme` IS NULL;

SELECT CONCAT('Updated ', ROW_COUNT(), ' rows with default theme value.') AS Status;

-- Step 3: Verify columns were added correctly
SELECT 'Verifying new columns...' AS Status;

SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM
    INFORMATION_SCHEMA.COLUMNS
WHERE
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pending_bills'
    AND COLUMN_NAME IN ('theme', 'ai_summary', 'ai_processed_at')
ORDER BY
    COLUMN_NAME;

SELECT 'Migration completed successfully!' AS Status;

-- Commit transaction
COMMIT;
