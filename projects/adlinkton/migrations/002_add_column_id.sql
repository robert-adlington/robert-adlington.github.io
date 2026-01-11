-- Add column_id to categories table for 4-column layout
-- Version: 1.1
-- Date: 2026-01-11

ALTER TABLE categories
ADD COLUMN column_id TINYINT UNSIGNED DEFAULT 1 AFTER parent_id,
ADD INDEX idx_user_column (user_id, column_id);

-- Distribute existing root categories across 4 columns
-- This will evenly spread existing categories
UPDATE categories
SET column_id = (
    CASE
        WHEN (@row_num := IFNULL(@row_num, 0) + 1) % 4 = 1 THEN 1
        WHEN @row_num % 4 = 2 THEN 2
        WHEN @row_num % 4 = 3 THEN 3
        ELSE 4
    END
)
WHERE parent_id IS NULL
ORDER BY sort_order, name;

-- Reset row counter
SET @row_num := 0;
