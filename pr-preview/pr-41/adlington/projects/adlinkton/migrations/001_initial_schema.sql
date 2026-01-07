-- Adlinkton Link Management System
-- Initial Database Schema
-- Version: 1.0

-- Note: The users table is assumed to already exist from the main adlington.fr authentication system

-- =====================================================
-- Core Link Storage
-- =====================================================

CREATE TABLE IF NOT EXISTS links (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    url             VARCHAR(2048) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    favicon_path    VARCHAR(255),           -- NULL until fetched
    is_favorite     BOOLEAN DEFAULT FALSE,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    first_accessed  DATETIME,               -- NULL until first click
    last_accessed   DATETIME,
    access_count    INT UNSIGNED DEFAULT 0,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_favorite (user_id, is_favorite),
    INDEX idx_user_recent (user_id, last_accessed DESC),
    INDEX idx_user_created (user_id, created_at DESC),
    FULLTEXT idx_search (name, description, url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Category System
-- =====================================================

CREATE TABLE IF NOT EXISTS categories (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    parent_id       INT UNSIGNED,           -- NULL for root categories
    name            VARCHAR(255) NOT NULL,
    display_mode    ENUM('tab', 'collapsible_tile', 'collapsible_tree') DEFAULT NULL,
                                            -- NULL = inherit from parent
    default_count   INT UNSIGNED DEFAULT 10,-- items shown by default
    sort_order      INT UNSIGNED DEFAULT 0, -- among siblings
    view_count      INT UNSIGNED DEFAULT 0, -- analytics
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_user_parent (user_id, parent_id),
    INDEX idx_sort (parent_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Link-Category Relationships
-- =====================================================

CREATE TABLE IF NOT EXISTS link_categories (
    link_id         INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED NOT NULL,
    sort_order      INT UNSIGNED DEFAULT 0, -- link position in this category

    PRIMARY KEY (link_id, category_id),
    FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category_sort (category_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tag System
-- =====================================================

CREATE TABLE IF NOT EXISTS tags (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_user_name (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Link-Tag Relationships
-- =====================================================

CREATE TABLE IF NOT EXISTS link_tags (
    link_id         INT UNSIGNED NOT NULL,
    tag_id          INT UNSIGNED NOT NULL,

    PRIMARY KEY (link_id, tag_id),
    FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    INDEX idx_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Smart Categories (Tag-based)
-- =====================================================

CREATE TABLE IF NOT EXISTS smart_categories (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    query_mode      ENUM('all', 'any') DEFAULT 'all',
    query_tags      JSON NOT NULL,          -- array of tag IDs: [1, 3, 5]
    display_mode    ENUM('tab', 'collapsible_tile', 'collapsible_tree') DEFAULT 'collapsible_tile',
    default_count   INT UNSIGNED DEFAULT 10,
    sort_order      INT UNSIGNED DEFAULT 0,
    is_system       BOOLEAN DEFAULT FALSE,  -- TRUE for Inbox
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_sort (user_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- User Settings
-- =====================================================

CREATE TABLE IF NOT EXISTS user_settings (
    user_id                 INT UNSIGNED PRIMARY KEY,
    link_open_behavior      ENUM('new_tab', 'same_tab') DEFAULT 'new_tab',
    default_display_mode    ENUM('tab', 'collapsible_tile', 'collapsible_tree') DEFAULT 'collapsible_tile',
    default_sort            ENUM('manual', 'name', 'created', 'accessed', 'frequency') DEFAULT 'manual',
    keyboard_shortcuts      JSON,           -- custom shortcut overrides
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Saved Layouts
-- =====================================================

CREATE TABLE IF NOT EXISTS saved_layouts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    layout_data     JSON NOT NULL,          -- pane configuration
    is_current      BOOLEAN DEFAULT FALSE,  -- active session layout
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- End of Schema
-- =====================================================
