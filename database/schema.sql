-- Constituant MVP - Database Schema
-- MySQL 5.7+ / MariaDB 10.2+
-- Execute this file to set up the database

-- Create database (uncomment if needed)
-- CREATE DATABASE IF NOT EXISTS constituant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE constituant;

-- ============================================================================
-- Table: bills
-- Stores legislation being voted on in EU Parliament and French Assembly
-- ============================================================================
CREATE TABLE IF NOT EXISTS bills (
    id VARCHAR(50) PRIMARY KEY COMMENT 'Unique identifier (e.g., eu-dsa-2024)',
    title VARCHAR(500) NOT NULL COMMENT 'Bill title',
    summary TEXT NOT NULL COMMENT 'Brief description of the bill',
    full_text_url VARCHAR(500) COMMENT 'Link to official legislation text',
    level ENUM('eu', 'france') NOT NULL COMMENT 'Legislative level',
    chamber VARCHAR(100) COMMENT 'Chamber name (e.g., European Parliament, Assemblée Nationale)',
    vote_datetime DATETIME NOT NULL COMMENT 'When the official vote takes place',
    status ENUM('upcoming', 'voting_now', 'completed') DEFAULT 'upcoming' COMMENT 'Current status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_level (level),
    INDEX idx_status (status),
    INDEX idx_vote_date (vote_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Legislative bills';

-- ============================================================================
-- Table: votes
-- Stores citizen votes on bills (one vote per IP per bill)
-- ============================================================================
CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id VARCHAR(50) NOT NULL COMMENT 'Reference to bill',
    vote_type ENUM('for', 'against', 'abstain') NOT NULL COMMENT 'Type of vote cast',
    voter_ip VARCHAR(45) NOT NULL COMMENT 'Voter IP address (IPv4 or IPv6)',
    user_agent VARCHAR(255) COMMENT 'Browser user agent for analytics',
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote (bill_id, voter_ip) COMMENT 'Prevent double voting',
    INDEX idx_bill (bill_id),
    INDEX idx_voted_at (voted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Citizen votes';

-- ============================================================================
-- Sample Data for Testing
-- ============================================================================
INSERT INTO bills (id, title, summary, level, chamber, vote_datetime, status, full_text_url) VALUES
(
    'eu-dsa-2024',
    'Digital Services Act - Amendment 247',
    'Requires online platforms to remove illegal content within 24 hours or face fines up to 6% of global revenue. Includes provisions for appeal mechanisms and transparency reporting.',
    'eu',
    'European Parliament',
    '2024-12-15 14:00:00',
    'upcoming',
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:52020PC0825'
),
(
    'eu-carbon-2024',
    'Carbon Border Adjustment Mechanism',
    'Imposes carbon tax on imports from countries with weaker climate policies. Aims to prevent carbon leakage and level the playing field for EU businesses.',
    'eu',
    'European Parliament',
    '2024-12-16 10:00:00',
    'upcoming',
    'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:52021PC0564'
),
(
    'fr-retirement-2024',
    'Réforme des retraites - Article 7',
    'Indexation des pensions sur l\'inflation avec un plancher de 1.1% par an. Concerne 14 millions de retraités.',
    'france',
    'Assemblée Nationale',
    '2024-12-15 16:00:00',
    'upcoming',
    'https://www.assemblee-nationale.fr/dyn/16/textes/l16b0449_projet-loi'
),
(
    'fr-renewable-2024',
    'Loi énergies renouvelables',
    'Accélération des procédures d\'autorisation pour les parcs éoliens et solaires. Objectif: 50% d\'énergies renouvelables d\'ici 2030.',
    'france',
    'Assemblée Nationale',
    '2024-12-17 15:00:00',
    'upcoming',
    'https://www.assemblee-nationale.fr/dyn/16/textes/l16b0465_projet-loi'
);

-- ============================================================================
-- Sample votes for testing (optional)
-- ============================================================================
-- Uncomment to add test votes
-- INSERT INTO votes (bill_id, vote_type, voter_ip, user_agent) VALUES
-- ('eu-dsa-2024', 'for', '192.168.1.1', 'Mozilla/5.0...'),
-- ('eu-dsa-2024', 'for', '192.168.1.2', 'Mozilla/5.0...'),
-- ('eu-dsa-2024', 'against', '192.168.1.3', 'Mozilla/5.0...'),
-- ('eu-carbon-2024', 'for', '192.168.1.1', 'Mozilla/5.0...');

-- ============================================================================
-- Useful queries for administration
-- ============================================================================

-- View all bills with vote counts
-- SELECT
--     b.id,
--     b.title,
--     b.level,
--     b.vote_datetime,
--     COUNT(v.id) as total_votes,
--     SUM(CASE WHEN v.vote_type = 'for' THEN 1 ELSE 0 END) as votes_for,
--     SUM(CASE WHEN v.vote_type = 'against' THEN 1 ELSE 0 END) as votes_against,
--     SUM(CASE WHEN v.vote_type = 'abstain' THEN 1 ELSE 0 END) as votes_abstain
-- FROM bills b
-- LEFT JOIN votes v ON b.id = v.bill_id
-- GROUP BY b.id
-- ORDER BY b.vote_datetime ASC;

-- Find duplicate votes (should be empty due to unique constraint)
-- SELECT bill_id, voter_ip, COUNT(*) as count
-- FROM votes
-- GROUP BY bill_id, voter_ip
-- HAVING count > 1;
