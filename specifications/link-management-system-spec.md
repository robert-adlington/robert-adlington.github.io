# Link Management System — Technical Specification

**Version:** 1.0  
**Date:** January 2026  
**Status:** Requirements Complete

---

## 1. Overview

A personal link management system called "Adlinkton", integrated with adlington.fr's session-based authentication. The system provides rich organization through nested categories, tags, and smart categories, with a desktop-first SPA interface emphasizing keyboard-driven productivity and flexible layouts.

Location: projects/adlinkton/

### 1.1 Key Characteristics

- **Desktop-first** with mobile read-only access + link addition
- **Compact UI** optimized for displaying many links
- **Keyboard-driven** with configurable shortcuts
- **Flexible layouts** including split-screen with resizable panes
- **Dual organization** via manual categories and tag-based smart categories

### 1.2 Technical Constraints

| Constraint | Value |
|------------|-------|
| Hosting | Hostinger shared hosting |
| Database | MySQL |
| Backend | PHP (no Node.js support) |
| Auth | Existing session-based system |

---

## 2. Data Model

### 2.1 Entity Relationship Diagram

```
┌─────────────────┐       ┌─────────────────┐
│     users       │       │   user_settings │
│  (existing)     │──────<│                 │
└─────────────────┘       └─────────────────┘
        │
        │ 1:many
        ▼
┌─────────────────┐       ┌─────────────────┐
│     links       │>──────│  link_categories│
│                 │       │   (junction)    │
└─────────────────┘       └─────────────────┘
        │                         │
        │                         │
        ▼                         ▼
┌─────────────────┐       ┌─────────────────┐
│   link_tags     │       │   categories    │
│   (junction)    │       │  (self-ref)     │
└─────────────────┘       └─────────────────┘
        │
        ▼
┌─────────────────┐       ┌─────────────────┐
│     tags        │       │ smart_categories│
└─────────────────┘       └─────────────────┘

┌─────────────────┐       ┌─────────────────┐
│  saved_layouts  │       │keyboard_shortcuts│
└─────────────────┘       └─────────────────┘
```

### 2.2 Table Definitions

#### `links`
```sql
CREATE TABLE links (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `categories`
```sql
CREATE TABLE categories (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `link_categories`
```sql
CREATE TABLE link_categories (
    link_id         INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED NOT NULL,
    sort_order      INT UNSIGNED DEFAULT 0, -- link position in this category
    
    PRIMARY KEY (link_id, category_id),
    FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category_sort (category_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tags`
```sql
CREATE TABLE tags (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE INDEX idx_user_name (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `link_tags`
```sql
CREATE TABLE link_tags (
    link_id         INT UNSIGNED NOT NULL,
    tag_id          INT UNSIGNED NOT NULL,
    
    PRIMARY KEY (link_id, tag_id),
    FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    INDEX idx_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `smart_categories`
```sql
CREATE TABLE smart_categories (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `user_settings`
```sql
CREATE TABLE user_settings (
    user_id                 INT UNSIGNED PRIMARY KEY,
    link_open_behavior      ENUM('new_tab', 'same_tab') DEFAULT 'new_tab',
    default_display_mode    ENUM('tab', 'collapsible_tile', 'collapsible_tree') DEFAULT 'collapsible_tile',
    default_sort            ENUM('manual', 'name', 'created', 'accessed', 'frequency') DEFAULT 'manual',
    keyboard_shortcuts      JSON,           -- custom shortcut overrides
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `saved_layouts`
```sql
CREATE TABLE saved_layouts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    layout_data     JSON NOT NULL,          -- pane configuration
    is_current      BOOLEAN DEFAULT FALSE,  -- active session layout
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.3 Layout Data Structure

```json
{
  "panes": [
    {
      "id": "pane-1",
      "type": "category",
      "categoryId": 5,
      "widthPercent": 50
    },
    {
      "id": "pane-2", 
      "type": "smart_category",
      "smartCategoryId": 2,
      "widthPercent": 50
    }
  ]
}
```

### 2.4 Default Keyboard Shortcuts Structure

```json
{
  "navigation": {
    "nextLink": "j",
    "prevLink": "k",
    "nextCategory": "ArrowDown",
    "prevCategory": "ArrowUp",
    "expandCategory": "ArrowRight",
    "collapseCategory": "ArrowLeft"
  },
  "actions": {
    "openLink": "o",
    "openNewTab": "Enter",
    "editLink": "e",
    "toggleFavorite": "f",
    "deleteLink": "Delete",
    "copyUrl": "c",
    "showDetails": "Space"
  },
  "views": {
    "search": "/",
    "fullscreen": "F11",
    "exitFullscreen": "Escape",
    "layout1": "1",
    "layout2": "2",
    "layout3": "3"
  },
  "bulk": {
    "selectAll": "Ctrl+a",
    "toggleSelect": "x"
  }
}
```

---

## 3. API Endpoints

### 3.1 Authentication

All endpoints require valid session. Return `401 Unauthorized` if session invalid.

### 3.2 Links

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/links` | List links (with filters) |
| GET | `/api/links/{id}` | Get single link |
| POST | `/api/links` | Create link |
| PUT | `/api/links/{id}` | Update link |
| DELETE | `/api/links/{id}` | Delete link |
| POST | `/api/links/{id}/open` | Record link access |
| POST | `/api/links/bulk` | Bulk operations |

#### GET /api/links

Query parameters:
- `category_id` — filter by category
- `smart_category_id` — filter by smart category
- `tag_ids[]` — filter by tags
- `favorite` — boolean, filter favorites
- `search` — full-text search
- `sort` — `manual|name|created|accessed|frequency`
- `order` — `asc|desc`
- `limit` — pagination limit
- `offset` — pagination offset

Response:
```json
{
  "links": [
    {
      "id": 1,
      "url": "https://example.com",
      "name": "Example",
      "description": "An example site",
      "favicon_path": "/favicons/abc123.png",
      "is_favorite": true,
      "created_at": "2026-01-05T10:00:00Z",
      "first_accessed": "2026-01-05T11:00:00Z",
      "last_accessed": "2026-01-06T09:00:00Z",
      "access_count": 5,
      "categories": [1, 3],
      "tags": [{"id": 1, "name": "work"}, {"id": 2, "name": "reference"}]
    }
  ],
  "total": 150,
  "limit": 20,
  "offset": 0
}
```

#### POST /api/links

Request:
```json
{
  "url": "https://example.com",
  "name": "Example",
  "description": "Optional description",
  "category_ids": [1, 3],
  "tag_ids": [1, 2],
  "is_favorite": false
}
```

Behavior:
1. Validate URL format
2. If `name` empty, attempt to fetch page title
3. Fetch favicon synchronously (3s timeout)
4. If favicon fetch fails, generate domain-based fallback
5. If no `category_ids`, link goes to Inbox only

#### POST /api/links/bulk

Request:
```json
{
  "link_ids": [1, 2, 3],
  "action": "move|tag|untag|favorite|unfavorite|delete",
  "category_id": 5,
  "tag_id": 2
}
```

### 3.3 Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/categories` | List all categories (tree) |
| GET | `/api/categories/{id}` | Get category with links |
| POST | `/api/categories` | Create category |
| PUT | `/api/categories/{id}` | Update category |
| DELETE | `/api/categories/{id}` | Delete category |
| PUT | `/api/categories/{id}/reorder` | Reorder category |
| POST | `/api/categories/{id}/open-all` | Get all URLs for "open all" |

#### GET /api/categories

Response (nested tree):
```json
{
  "categories": [
    {
      "id": 1,
      "name": "Work",
      "parent_id": null,
      "display_mode": "collapsible_tile",
      "effective_display_mode": "collapsible_tile",
      "default_count": 10,
      "sort_order": 0,
      "view_count": 45,
      "link_count": 23,
      "children": [
        {
          "id": 2,
          "name": "Projects",
          "parent_id": 1,
          "display_mode": null,
          "effective_display_mode": "collapsible_tile",
          "children": []
        }
      ]
    }
  ]
}
```

Note: `effective_display_mode` resolves inheritance chain.

#### PUT /api/categories/{id}/reorder

Request:
```json
{
  "parent_id": 3,
  "sort_order": 2,
  "inherit_display": true
}
```

### 3.4 Smart Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/smart-categories` | List smart categories |
| GET | `/api/smart-categories/{id}` | Get with matching links |
| POST | `/api/smart-categories` | Create smart category |
| PUT | `/api/smart-categories/{id}` | Update smart category |
| DELETE | `/api/smart-categories/{id}` | Delete (not Inbox) |

#### GET /api/smart-categories/inbox

Special endpoint for Inbox (links with no categories):
```json
{
  "id": "inbox",
  "name": "Inbox",
  "is_system": true,
  "links": [...]
}
```

### 3.5 Tags

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tags` | List all tags |
| POST | `/api/tags` | Create tag |
| PUT | `/api/tags/{id}` | Rename tag |
| DELETE | `/api/tags/{id}` | Delete tag |

### 3.6 Settings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/settings` | Get user settings |
| PUT | `/api/settings` | Update settings |
| GET | `/api/layouts` | List saved layouts |
| POST | `/api/layouts` | Save layout |
| PUT | `/api/layouts/{id}` | Update layout |
| DELETE | `/api/layouts/{id}` | Delete layout |
| PUT | `/api/layouts/current` | Set current session layout |

### 3.7 Import

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/import/bookmarks` | Import browser bookmarks |

Request: `multipart/form-data` with HTML file

Response:
```json
{
  "imported": 156,
  "skipped": 3,
  "errors": ["Invalid URL on line 45"]
}
```

Behavior:
- Parse Netscape bookmark HTML format
- Create categories matching folder structure
- Fetch favicons in batches (background if possible, otherwise synchronous with progress)
- Skip duplicate URLs

### 3.8 Reorder Links

| Method | Endpoint | Description |
|--------|----------|-------------|
| PUT | `/api/links/{id}/reorder` | Move link within/between categories |

Request:
```json
{
  "source_category_id": 1,
  "target_category_id": 3,
  "target_sort_order": 5,
  "respect_current_sort": true
}
```

If `respect_current_sort` is true and target category has non-manual sort active, the link is added and the API returns the resulting position after sort is applied.

---

## 4. Frontend Architecture

### 4.1 Technology Stack

| Layer | Technology | Rationale |
|-------|------------|-----------|
| Framework | Vue 3 or React | Component-based, good DX |
| State | Pinia (Vue) / Zustand (React) | Lightweight, TypeScript support |
| Drag & Drop | SortableJS | Framework-agnostic, touch support |
| Split Panes | Split.js | Lightweight, resizable columns |
| HTTP | Axios or fetch | API communication |
| Build | Vite | Fast builds, outputs static files |
| Styling | Tailwind CSS | Utility-first, compact builds |

Output: Static JS/CSS files served by PHP or directly by web server.

### 4.2 Component Hierarchy

```
App
├── Header
│   ├── Logo
│   ├── GlobalSearch
│   ├── QuickAdd
│   └── SettingsMenu
│
├── Sidebar (desktop only)
│   ├── CategoryTree
│   │   └── CategoryNode (recursive)
│   ├── SmartCategoryList
│   │   └── SmartCategoryItem
│   ├── SystemCategories
│   │   ├── Inbox
│   │   ├── Favorites
│   │   └── Recent
│   └── TagCloud
│
├── MainContent
│   └── PaneContainer
│       └── Pane (1-N)
│           ├── PaneHeader
│           │   ├── CategoryTitle
│           │   ├── SortDropdown
│           │   ├── DisplayModeToggle
│           │   └── FullscreenButton
│           └── LinkList
│               └── LinkItem (compact)
│                   ├── Favicon
│                   ├── LinkName
│                   ├── MenuTrigger
│                   └── SelectCheckbox
│
├── LinkDetailPanel (slide-out or modal)
│   ├── LinkMetadata
│   ├── EditForm
│   ├── TagEditor
│   ├── CategoryAssignment
│   └── Analytics
│
├── SettingsModal
│   ├── GeneralSettings
│   ├── KeyboardShortcuts
│   └── ImportExport
│
└── ContextMenu (global)
```

### 4.3 State Structure

```typescript
interface AppState {
  // Data
  links: Map<number, Link>;
  categories: Map<number, Category>;
  smartCategories: Map<number, SmartCategory>;
  tags: Map<number, Tag>;
  
  // UI State
  selectedLinkIds: Set<number>;
  focusedLinkId: number | null;
  focusedCategoryId: number | null;
  expandedCategoryIds: Set<number>;
  
  // Layout
  panes: Pane[];
  fullscreenPaneId: string | null;
  
  // Search
  searchQuery: string;
  searchResults: number[];
  
  // Settings (cached from server)
  settings: UserSettings;
}

interface Link {
  id: number;
  url: string;
  name: string;
  description: string;
  faviconPath: string | null;
  isFavorite: boolean;
  createdAt: Date;
  firstAccessed: Date | null;
  lastAccessed: Date | null;
  accessCount: number;
  categoryIds: number[];
  tags: Tag[];
}

interface Category {
  id: number;
  name: string;
  parentId: number | null;
  displayMode: DisplayMode | null;
  effectiveDisplayMode: DisplayMode;
  defaultCount: number;
  sortOrder: number;
  viewCount: number;
  linkCount: number;
  childIds: number[];
}

interface Pane {
  id: string;
  type: 'category' | 'smart_category' | 'favorites' | 'recent' | 'inbox' | 'search';
  sourceId: number | null;  // category or smart_category ID
  widthPercent: number;
  sortMode: SortMode;
  sortOrder: 'asc' | 'desc';
}
```

### 4.4 Key Interactions

#### Drag and Drop Flow

```
1. User drags LinkItem
2. SortableJS emits drag start
3. Show drop indicators on valid targets:
   - Other positions in same list
   - Other panes
   - Category tree nodes in sidebar
4. On drop:
   a. Optimistically update local state
   b. Call PUT /api/links/{id}/reorder
   c. On success: update sort_order from response
   d. On failure: rollback local state, show error
```

#### Display Mode Inheritance Override

```
1. User clicks display mode toggle
2. If SHIFT held:
   - Set explicit display_mode on category
   - API call: PUT /api/categories/{id} with display_mode
3. If SHIFT not held:
   - Set display_mode = null (inherit)
   - API call: PUT /api/categories/{id} with display_mode: null
4. Recalculate effectiveDisplayMode for this category and descendants
```

#### Keyboard Navigation

```
1. App registers global keydown listener
2. Check if input/textarea focused → skip shortcuts
3. Look up action in settings.keyboard_shortcuts
4. Execute action:
   - j/k: Move focusedLinkId up/down in current list
   - o/Enter: Open focused link, call POST /api/links/{id}/open
   - f: Toggle favorite on focused link
   - /: Focus search input
   - 1/2/3: Load saved layout
   - Escape: Exit fullscreen, clear selection, close modal
```

### 4.5 Compact Link Display

Desktop link item (collapsed state): ~32-40px height

```
┌─────────────────────────────────────────────────────────┐
│ [☐] [🌐] Link Name Here                    [★] [⋮]     │
└─────────────────────────────────────────────────────────┘
  │    │     │                                │    │
  │    │     └─ Truncated with ellipsis       │    └─ Menu (hover)
  │    └─ Favicon (16x16)                     └─ Favorite indicator
  └─ Select checkbox (multi-select mode)
```

Mobile link item: ~48px height (touch-friendly)

```
┌─────────────────────────────────────────────────────────┐
│ [🌐] Link Name Here                              [★]    │
└─────────────────────────────────────────────────────────┘
```

### 4.6 Split Screen Implementation

Using Split.js for resizable columns:

```javascript
import Split from 'split.js';

// Initialize with pane elements
Split(['#pane-1', '#pane-2', '#pane-3'], {
  sizes: [33, 34, 33],
  minSize: 200,
  gutterSize: 8,
  onDragEnd: (sizes) => {
    // Persist to state and optionally to saved layout
    store.updatePaneSizes(sizes);
  }
});
```

Default layouts:
- Single pane: 100%
- Two panes: 50% / 50%
- Three panes: 33% / 34% / 33%

---

## 5. Favicon Handling

### 5.1 Fetch Strategy

```php
function fetchFavicon(string $url, int $timeoutSeconds = 3): ?string {
    $domain = parse_url($url, PHP_URL_HOST);
    
    // Try common favicon locations in order
    $candidates = [
        "https://{$domain}/favicon.ico",
        "https://{$domain}/favicon.png",
        // Could also parse HTML for <link rel="icon">
    ];
    
    foreach ($candidates as $faviconUrl) {
        $favicon = fetchWithTimeout($faviconUrl, $timeoutSeconds);
        if ($favicon && isValidImage($favicon)) {
            return saveFavicon($favicon, $domain);
        }
    }
    
    return null; // Will use fallback
}
```

### 5.2 Fallback Generation

Domain-based fallback using first letter + consistent color:

```php
function generateFallbackFavicon(string $domain): string {
    $letter = strtoupper($domain[0]);
    $color = '#' . substr(md5($domain), 0, 6);
    
    // Generate simple SVG
    $svg = <<<SVG
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">
      <rect width="32" height="32" fill="{$color}" rx="4"/>
      <text x="16" y="22" text-anchor="middle" fill="white" 
            font-family="Arial" font-size="18" font-weight="bold">{$letter}</text>
    </svg>
    SVG;
    
    return saveSvg($svg, $domain);
}
```

### 5.3 Storage

- Location: `/storage/favicons/`
- Naming: `{md5(domain)}.{ext}`
- Serve via PHP or direct web server access
- Consider: periodic cleanup of orphaned favicons

---

## 6. Search Implementation

### 6.1 MySQL Full-Text Search

```sql
-- Search query
SELECT l.*, 
       MATCH(l.name, l.description, l.url) AGAINST (? IN NATURAL LANGUAGE MODE) as relevance
FROM links l
WHERE l.user_id = ?
  AND MATCH(l.name, l.description, l.url) AGAINST (? IN NATURAL LANGUAGE MODE)
ORDER BY relevance DESC
LIMIT 50;
```

### 6.2 Tag Filtering

```sql
-- Links with ALL specified tags
SELECT l.*
FROM links l
JOIN link_tags lt ON l.id = lt.link_id
WHERE l.user_id = ?
  AND lt.tag_id IN (1, 2, 3)
GROUP BY l.id
HAVING COUNT(DISTINCT lt.tag_id) = 3;

-- Links with ANY specified tags
SELECT DISTINCT l.*
FROM links l
JOIN link_tags lt ON l.id = lt.link_id
WHERE l.user_id = ?
  AND lt.tag_id IN (1, 2, 3);
```

### 6.3 Frontend Search UX

- Debounce input: 300ms
- Show loading indicator
- Highlight matching terms in results
- Press Enter to open first result
- Arrow keys to navigate results

---

## 7. Mobile Experience

### 7.1 Capabilities

| Feature | Mobile Support |
|---------|---------------|
| View links | ✓ Read-only |
| Open links | ✓ Tap to open |
| Add links | ✓ Quick add form |
| Search | ✓ Global search |
| Browse categories | ✓ Collapsible tree |
| Edit links | ✗ |
| Reorder | ✗ |
| Split screen | ✗ |
| Bulk operations | ✗ |

### 7.2 Layout

Single-column layout with:
- Fixed header with search and add button
- Collapsible category navigation
- Touch-optimized link list (larger tap targets)
- Bottom sheet for link options (long-press)

---

## 8. Import: Browser Bookmarks

### 8.1 Netscape Bookmark Format

Standard HTML format exported by Chrome, Firefox, Safari, Edge:

```html
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<DL>
  <DT><H3>Folder Name</H3>
  <DL>
    <DT><A HREF="https://example.com" ADD_DATE="1609459200">Link Name</A>
  </DL>
</DL>
```

### 8.2 Import Algorithm

```
1. Parse HTML with DOM parser
2. Walk tree recursively:
   - <H3> → Create category (track parent)
   - <A> → Create link
3. For each link:
   - Extract URL, name, ADD_DATE
   - Check for duplicate URL (skip or update)
   - Queue favicon fetch
4. Batch insert to database
5. Process favicon queue (with progress callback)
6. Return summary: imported, skipped, errors
```

### 8.3 Progress Feedback

For large imports, provide progress via:
- Initial: "Parsing bookmarks..."
- Phase 1: "Creating categories... (45/45)"
- Phase 2: "Importing links... (156/200)"
- Phase 3: "Fetching favicons... (89/156)"
- Complete: "Import complete! 156 links imported."

---

## 9. Analytics Tracking

### 9.1 Link Access

On every link open:

```php
// POST /api/links/{id}/open
UPDATE links 
SET access_count = access_count + 1,
    last_accessed = NOW(),
    first_accessed = COALESCE(first_accessed, NOW())
WHERE id = ? AND user_id = ?;
```

### 9.2 Category View

On category expand/view:

```php
UPDATE categories 
SET view_count = view_count + 1 
WHERE id = ? AND user_id = ?;
```

### 9.3 Recents Query

```sql
SELECT * FROM links 
WHERE user_id = ? 
  AND last_accessed IS NOT NULL
ORDER BY last_accessed DESC
LIMIT ?;  -- configurable default_count
```

### 9.4 Frequency Sort

```sql
SELECT * FROM links 
WHERE user_id = ? 
ORDER BY access_count DESC, last_accessed DESC;
```

---

## 10. Security Considerations

### 10.1 Authentication

- Verify session on every API request
- Return 401 for invalid/expired sessions
- CSRF token required for state-changing requests

### 10.2 Authorization

- All queries must filter by `user_id`
- Verify ownership before any update/delete
- Validate `parent_id` and `category_id` belong to user

### 10.3 Input Validation

- URL: Validate format, reasonable length
- Name/Description: Sanitize HTML, length limits
- IDs: Validate as positive integers
- Sort order: Validate as non-negative integer

### 10.4 Favicon Fetching

- Timeout: 3 seconds max
- Size limit: Reject files > 100KB
- Validate image format before saving
- Sanitize filename

---

## 11. File Structure

```
/link-manager/
├── api/
│   ├── index.php              # Router
│   ├── auth.php               # Session validation
│   ├── links.php              # Link endpoints
│   ├── categories.php         # Category endpoints
│   ├── smart-categories.php   # Smart category endpoints
│   ├── tags.php               # Tag endpoints
│   ├── settings.php           # Settings endpoints
│   ├── import.php             # Import endpoints
│   └── helpers/
│       ├── db.php             # Database connection
│       ├── response.php       # JSON response helpers
│       ├── favicon.php        # Favicon fetching
│       └── validation.php     # Input validation
│
├── storage/
│   └── favicons/              # Cached favicons
│
├── frontend/
│   ├── src/
│   │   ├── main.js            # Entry point
│   │   ├── App.vue            # Root component
│   │   ├── stores/            # State management
│   │   ├── components/        # UI components
│   │   ├── composables/       # Shared logic (shortcuts, drag-drop)
│   │   ├── api/               # API client
│   │   └── styles/            # Global styles
│   ├── index.html
│   ├── vite.config.js
│   └── package.json
│
├── dist/                      # Built frontend (served)
│   ├── index.html
│   └── assets/
│
└── migrations/
    └── 001_initial_schema.sql
```

---

## 12. Implementation Phases

### Phase 1: Foundation
- [ ] Database schema and migrations
- [ ] Basic PHP API structure with auth
- [ ] Frontend project setup with routing
- [ ] Basic CRUD for links and categories

### Phase 2: Core Features
- [ ] Favicon fetching and fallback
- [ ] Tag system
- [ ] Smart categories with queries
- [ ] Inbox system category
- [ ] Search implementation

### Phase 3: Organization
- [ ] Drag-and-drop reordering
- [ ] Category tree with nesting
- [ ] Display mode inheritance
- [ ] Multiple sort options

### Phase 4: Productivity
- [ ] Keyboard shortcuts
- [ ] Split-screen panes
- [ ] Saved layouts
- [ ] Bulk operations

### Phase 5: Polish
- [ ] Mobile-responsive layout
- [ ] Bookmark import
- [ ] Analytics display
- [ ] Fullscreen mode
- [ ] Open all / incognito hints

---

## 13. Suggested Libraries

| Purpose | Library | Notes |
|---------|---------|-------|
| Drag & Drop | SortableJS | Framework bindings: vue.draggable.next, react-sortablejs |
| Split Panes | Split.js | Lightweight, no dependencies |
| Icons | Lucide | Consistent, tree-shakeable |
| Date Handling | date-fns | Lightweight formatting |
| HTTP Client | Axios | Interceptors for auth |
| Hotkeys | hotkeys-js | Keyboard shortcut handling |
| Virtual Scroll | vue-virtual-scroller / react-window | For large link lists |

---

## 14. Open Questions / Future Considerations

- **Browser extension**: Quick-add links from any page?
- **Sharing**: Share individual links or categories publicly?
- **Export**: Export bookmarks back to HTML format?
- **Archiving**: Save page snapshots via Wayback Machine or local?
- **Link health**: Periodic checking for dead links?
- **Dark mode**: Theme switching?

---

*End of specification*
