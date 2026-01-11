-- Add column_id to categories table for 4-column layout
-- Version: 1.1
-- Date: 2026-01-11
--
-- column_id is ONLY for root categories (parent_id IS NULL)
-- Subcategories inherit their position from their parent and should have column_id = NULL

ALTER TABLE categories
ADD COLUMN column_id TINYINT UNSIGNED DEFAULT NULL AFTER parent_id,
ADD INDEX idx_user_column (user_id, column_id);

-- Distribute existing root categories across 4 columns
-- Only update root categories (parent_id IS NULL)
-- Subcategories remain with column_id = NULL
SET @row_num := 0;

UPDATE categories
SET column_id = (
    CASE
        WHEN (@row_num := @row_num + 1) % 4 = 1 THEN 1
        WHEN @row_num % 4 = 2 THEN 2
        WHEN @row_num % 4 = 3 THEN 3
        ELSE 4
    END
)
WHERE parent_id IS NULL
ORDER BY sort_order, name;
