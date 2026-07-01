-- ============================================
-- Phase 5 Part 2: Word Occurrence Audit Module
-- ============================================

USE audit_withgufran;

-- ============================================
-- Word Audit Searches Table
-- Stores keyword search history per project
-- ============================================
CREATE TABLE IF NOT EXISTS word_audit_searches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    keyword VARCHAR(500) NOT NULL COMMENT 'Single keyword or phrase searched',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_project_id (project_id),
    INDEX idx_keyword (keyword),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Notes
-- ============================================
-- Word occurrence results are stored in the existing
-- page_audit_results table with:
--   module_name   = 'word_occurrence_audit'
--   result_status = PASS (found) / FAIL (not found) / ERROR (unavailable)
--   result_value  = summary (e.g., "3 occurrence(s) of 'free shipping'")
--   raw_data_json = { keyword, total_occurrences, locations, snippets }
--   checked_at    = audit timestamp
--
-- Because page_audit_results has UNIQUE KEY (project_page_id, module_name),
-- the latest word occurrence result per page is kept (most recent keyword run).
-- Search history is preserved separately in word_audit_searches.
