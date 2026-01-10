# Project Context & Architecture

This file contains critical information about the robert-adlington.github.io project that Claude should know in every session.

## Table of Contents
- [Hosting Environment](#hosting-environment)
- [Architecture Decisions](#architecture-decisions)
- [Project Structure](#project-structure)
- [Deployment](#deployment)
- [Common Patterns](#common-patterns)
- [Known Issues & Solutions](#known-issues--solutions)

---

## Hosting Environment

**Provider:** Hostinger Shared Hosting (adlington.fr)

### Critical Constraints

1. **PHP Only - No Node.js Backend**
   - Frontend: Can use any framework (Vue, React, etc.)
   - Backend: **MUST be PHP** - no Node.js/Express support
   - Build process: Run `npm run build` locally/CI, deploy built files only

2. **Database**
   - MySQL database available
   - Credentials stored in `api/config.php` (gitignored)
   - Session-based authentication using existing auth system

3. **Web Server**
   - Apache with `.htaccess` support
   - FTP/SFTP access for deployment
   - Can use GitHub Actions for automated deployment

### Deployment Method

- **Primary:** GitHub Actions (`.github/workflows/deploy.yml`)
- **Fallback:** SSH/FTP manual deployment
- **Target:** `/public_html/` on Hostinger server
- See `deploy-instructions-ssh.md` for manual deployment

---

## Architecture Decisions

### Directory Structure Simplification (January 2025)

**What changed:** Moved everything from `adlington/` subdirectory to repository root.

**Why:**
- Simplified repository structure
- Reduced path confusion in development
- Eliminated need to remember the adlington wrapper folder
- Makes URLs cleaner (e.g., `/projects/` instead of `/adlington/projects/`)

**Old structure:**
```
adlington/
├── api/
├── projects/
└── index.html
```

**New structure (CURRENT):**
```
/
├── api/
├── projects/
├── experiments/
├── specifications/
└── index.html
```

### Session-Based Authentication

- Shared authentication system across all projects
- Located in `/api/auth.php`
- Projects requiring auth should use: `require_once __DIR__ . '/../../api/auth.php';`
- Sessions managed via PHP sessions with secure cookies

### Drag and Drop Implementation in Adlinkton (January 2025)

**Decision:** Use `vue-draggable-next` library instead of custom HTML5 drag and drop implementation.

**Context:**
The Adlinkton UI redesign required drag and drop functionality for reorganizing categories:
- Drag categories onto other categories (make it a child)
- Drag categories onto subcategories (make it a child)
- Drag categories to empty space (move to root level)
- Prevent circular references (can't move parent into its own descendant)

**Initial Approach (Failed):**
Custom implementation using native HTML5 Drag and Drop API with Vue event emitters:
- Made elements draggable with `draggable="true"`
- Used custom event chain: `SubcategoryItem → CategoryCard → CategoryGrid`
- Manual `isDragging` state management with `ref()` for drop zone visibility
- Custom drop handlers and visual feedback with CSS classes

**Problems Identified:**
1. **Fragile event propagation:** Nested re-emits through multiple component levels failed silently
2. **Vue reactivity issues:** Console showed `isDragging = true` but drop zones didn't render
3. **Drag ending immediately:** Events fired in rapid succession (dragstart → dragend), preventing proper drag operation
4. **Complex nested scenarios:** `event.stopPropagation()` in SubcategoryItem blocked native events from bubbling
5. **Conflicting targets:** Same elements were both draggable AND drop targets, confusing browser
6. **Timing issues:** Drag operations cancelled before user could complete them

**Root Cause:**
The custom implementation had fundamental architectural flaws:
- Too complex: ~200 lines of drag/drop code across 3 components
- Brittle: Event chain broke with nested categories (3+ levels deep)
- Browser conflicts: Native drag API + Vue's virtual DOM caused race conditions
- Maintenance burden: Every edge case required custom handling

**Solution Chosen:**
Refactor to use **`vue-draggable-next`** (SortableJS wrapper for Vue 3):
- Already installed as project dependency
- Battle-tested library handling all edge cases
- Built-in visual feedback and ghost elements
- Automatic drop zone management
- Group-based drag/drop (move items between multiple lists)
- Works reliably with nested structures
- 10x less code than custom implementation

**Benefits:**
- ✅ Reliable drag operations with proper visual feedback
- ✅ Drop zones that actually appear and work
- ✅ Handles nested draggables correctly
- ✅ Prevents circular references with built-in validation
- ✅ Smooth animations and transitions
- ✅ Much simpler codebase (less custom code to maintain)
- ✅ Well-documented API with TypeScript support

**Trade-offs:**
- External dependency (but already installed)
- Library-specific API to learn
- Some customization limitations compared to full custom solution

**Reasoning:**
After multiple debugging attempts and architectural analysis, it became clear that building a robust custom drag and drop solution from scratch was not cost-effective. The native HTML5 Drag and Drop API has known issues with complex Vue component trees, and `vue-draggable-next` solves all identified problems with a proven, maintained solution.

**Implementation Pattern:**
See `projects/adlinkton/frontend/src/components/` for usage examples with hierarchical category trees.

---

### Frontend Build Process

For projects using Vue/Vite (e.g., Adlinkton):

1. **Development:**
   - Source: `projects/adlinkton/frontend/src/`
   - Run: `npm run dev` for local development

2. **Production:**
   - Build: `npm run build` (outputs to `dist/`)
   - Deploy: Only the `dist/` folder contents
   - Config: `vite.config.js` with `base: '/projects/adlinkton/dist/'`

---

## Project Structure

```
robert-adlington.github.io/
├── .claude/                    # Context files for AI assistants
├── .github/
│   └── workflows/
│       ├── deploy.yml          # Hostinger FTP deployment
│       └── pr-checks.yml       # PR validation
├── api/                        # Shared PHP backend
│   ├── auth.php               # Session authentication
│   ├── database.php           # Database connection
│   ├── config.php             # DB credentials (gitignored)
│   └── ...
├── projects/
│   ├── adlinkton/             # Link management system (Vue SPA)
│   │   ├── frontend/          # Vue source code
│   │   ├── dist/              # Built Vue app
│   │   └── api/               # Adlinkton-specific API
│   ├── contract-whist/        # Whist score tracker
│   │   ├── index.html         # Vanilla JS app
│   │   └── api/               # Game-specific API endpoints
│   └── random-robby/          # Random selection tools
│       └── index.html         # Vanilla JS app
├── experiments/               # Experimental/learning projects
│   ├── geometry/
│   └── periodical/
├── specifications/            # Technical specs for projects
├── images/                    # Shared images
├── index.html                 # Main dashboard (landing page)
├── favicon-*.png              # Site favicons
└── README.md
```

### Project Types

1. **Vanilla JS Projects** (contract-whist, random-robby)
   - Single `index.html` with embedded CSS/JS
   - Can use external CDN libraries
   - No build process needed

2. **Vue SPA Projects** (adlinkton)
   - Source in `frontend/src/`
   - Build with `npm run build`
   - Deploy built `dist/` folder
   - API in separate `api/` directory

---

## Deployment

### GitHub Actions Deployment

**File:** `.github/workflows/deploy.yml`

**Process:**
1. Checkout code
2. Build Vue frontend (`npm run build` in adlinkton)
3. Deploy to Hostinger via FTP
4. Excludes: source files, node_modules, sensitive configs

**Secrets Required:**
- `FTP_SERVER`
- `FTP_USERNAME`
- `FTP_PASSWORD`

### Manual Deployment (SSH)

See `deploy-instructions-ssh.md` for full instructions.

```bash
ssh user@adlington.fr
cd ~/public_html
git pull origin main
# If Vue app changed:
cd projects/adlinkton/frontend
npm ci && npm run build
```

---

## Database Management

### ⚠️ CRITICAL: Production Database with Active Users

**This database has REAL USERS and LIVE DATA. Treat every database change with extreme care.**

### Database Migration Rules

**NEVER provide schema.sql files that assume a fresh start!**

When making database changes, you MUST:

1. **Check existing schema first**
   ```bash
   # SSH into Hostinger and inspect current tables
   mysql -u username -p database_name
   SHOW TABLES;
   DESCRIBE table_name;
   ```

2. **Provide UPDATE scripts (migrations), not CREATE scripts**
   - Use `ALTER TABLE` to modify existing tables
   - Use `ADD COLUMN IF NOT EXISTS` (MySQL 8.0+) or check first
   - Use `CREATE TABLE IF NOT EXISTS` only for genuinely new tables

3. **Preserve existing data**
   - Never DROP tables without explicit user approval
   - Never TRUNCATE tables
   - Use UPDATE with WHERE clauses carefully
   - Consider data migration for column changes

4. **Never include dummy/test data**
   - ❌ No "admin" / "password123" accounts
   - ❌ No test users or sample data
   - ✅ Only structural changes (tables, columns, indexes)

5. **Provide rollback instructions**
   - Every migration should have a rollback script
   - Document what the migration does
   - Warn about irreversible changes

### Migration Script Template

```sql
-- Migration: [Brief description]
-- Date: YYYY-MM-DD
-- Purpose: [Why this change is needed]

-- Check current state first
SELECT COUNT(*) FROM information_schema.COLUMNS
WHERE TABLE_NAME = 'your_table' AND COLUMN_NAME = 'new_column';

-- Apply migration (example: adding a column)
ALTER TABLE your_table
ADD COLUMN IF NOT EXISTS new_column VARCHAR(255) NULL DEFAULT NULL;

-- Create index if needed
CREATE INDEX IF NOT EXISTS idx_new_column ON your_table(new_column);

-- Verify migration
DESCRIBE your_table;

-- ROLLBACK (if needed):
-- ALTER TABLE your_table DROP COLUMN new_column;
```

### Safe Migration Patterns

**Adding a column:**
```sql
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE;
```

**Adding a table:**
```sql
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id, preference_key)
);
```

**Modifying a column (safe approach):**
```sql
-- Step 1: Add new column
ALTER TABLE links ADD COLUMN new_url_column VARCHAR(2048);

-- Step 2: Copy data
UPDATE links SET new_url_column = old_url_column;

-- Step 3: User manually verifies data, then drops old column
-- ALTER TABLE links DROP COLUMN old_url_column;
-- ALTER TABLE links RENAME COLUMN new_url_column TO url;
```

### Unsafe Patterns to AVOID

❌ **Never do this:**
```sql
-- DON'T: Drop and recreate existing tables
DROP TABLE IF EXISTS users;
CREATE TABLE users (...);

-- DON'T: Truncate production data
TRUNCATE TABLE user_sessions;

-- DON'T: Insert dummy data into production
INSERT INTO users (username, password) VALUES ('admin', 'admin123');

-- DON'T: Modify without checking existence
ALTER TABLE users ADD COLUMN email VARCHAR(255); -- Fails if exists!
```

### Migration Workflow

1. **Analyze:** Review current schema in production
2. **Plan:** Write migration script with rollback
3. **Review:** Share script with user for approval
4. **Backup:** User backs up database before running
5. **Execute:** Run migration on production
6. **Verify:** Check that data is intact and application works
7. **Monitor:** Watch for errors in the hours after deployment

### Finding Current Schema

**To check existing tables:**
```bash
# SSH into Hostinger
mysql -u [user] -p [database] -e "SHOW TABLES;"
```

**To check table structure:**
```bash
mysql -u [user] -p [database] -e "DESCRIBE table_name;"
```

**To check if column exists:**
```sql
SELECT COUNT(*) as column_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'your_database'
  AND TABLE_NAME = 'your_table'
  AND COLUMN_NAME = 'your_column';
```

### Projects with Their Own Tables

Some projects maintain their own database tables:

- **Contract Whist:** `whist_games`, `whist_players`, etc.
- **Adlinkton:** `links`, `categories`, `tags`, etc.
- **Shared Auth:** `users`, `sessions`

Always check which project owns which tables before modifying.

---

## Common Patterns

### Adding a New Project

1. **Create project directory:**
   ```bash
   mkdir -p projects/new-project
   ```

2. **Add project card to `index.html`:**
   ```html
   <a href="/projects/new-project/" class="project-card card">
       <div class="project-icon">🎯</div>
       <div>
           <h3 class="card-title">Project Name</h3>
           <p class="card-excerpt">Description</p>
       </div>
   </a>
   ```

3. **If needs authentication:**
   ```php
   <?php
   require_once __DIR__ . '/../../api/auth.php';
   $user = Auth::requireAuth();
   ```

### API Endpoints

**Pattern:** `/projects/{project}/api/{endpoint}.php`

**Example:** `/projects/contract-whist/api/games.php`

**Shared utilities:** Use `/api/utils.php` for common functions

### Path References

- **Absolute from web root:** `/projects/adlinkton/api/`
- **Relative require_once:** `__DIR__ . '/../../api/auth.php'`
- **Frontend assets:** Use absolute paths from web root in production builds

---

## Known Issues & Solutions

### Issue: PHP Require Paths After Restructuring

**Problem:** After moving from `adlington/` to root, relative paths in PHP files broke.

**Solution:** Update `__DIR__` relative paths:
- Before: `require_once __DIR__ . '/../../../api/auth.php';`
- After: `require_once __DIR__ . '/../../api/auth.php';`

### Issue: Hardcoded `/adlington/` Paths

**Problem:** Some files had hardcoded absolute paths with `/adlington/` prefix.

**Solution:** Search and replace `/adlington/` → `/` in:
- .htaccess files
- JavaScript fetch URLs
- PHP web path returns
- Documentation examples

**Command to find:**
```bash
grep -r "/adlington/" . --exclude-dir=".git" --exclude-dir="node_modules"
```

### Issue: Vue App 404s in Production

**Problem:** Vue SPA shows blank page or 404s.

**Root causes:**
1. Forgot to build (`npm run build`)
2. Incorrect `base` in `vite.config.js`
3. API paths don't match production URLs

**Solution checklist:**
- [ ] Build: `npm run build` in frontend directory
- [ ] Check `vite.config.js` has correct `base: '/projects/adlinkton/dist/'`
- [ ] Verify API calls use absolute paths from web root
- [ ] Deploy the `dist/` folder, not `src/`

### Issue: Database Connection Fails

**Problem:** PHP API returns database connection errors.

**Checklist:**
- [ ] Does `api/config.php` exist? (Copy from `config.example.php`)
- [ ] Are database credentials correct in `config.php`?
- [ ] Is MySQL running on Hostinger?
- [ ] Check `api/test.php` for detailed diagnostics

### Issue: Favicons Not Loading

**Problem:** Browser can't find favicon files.

**Solution:**
- Favicons are at root: `/favicon-32x32.png`
- Reference in HTML: `<link rel="icon" href="/favicon-32x32.png">`
- Do NOT include old `adlington/` path prefix

### Issue: Using axios Instead of apiClient (Adlinkton)

**Problem:** Direct axios usage bypasses the routing interceptor needed for Hostinger's shared hosting environment.

**Background:**
- Adlinkton uses query parameter routing (`?endpoint=/path`) instead of traditional path routing
- The `apiClient` in `frontend/src/api/client.js` has interceptors that convert paths to query parameters
- Using `axios` directly bypasses these interceptors, causing network errors

**❌ WRONG:**
```javascript
import axios from 'axios'

// This will fail - bypasses routing interceptor
const response = await axios.post('/api/import/bookmarks', formData)
```

**✅ CORRECT:**
```javascript
import apiClient from '@/api/client'

// This works - uses configured routing
const response = await apiClient.post('/import/bookmarks', formData)
```

**Rule:** In Adlinkton frontend components, **ALWAYS use `apiClient`**, never `axios` directly.

**Why this matters:**
- The hosting environment doesn't support `.htaccess` URL rewriting reliably
- Query parameter routing is the workaround: `index.php?endpoint=/links`
- The `apiClient` handles this conversion automatically
- Direct axios calls result in `ERR_HTTP2_PROTOCOL_ERROR` or 404s

---

## Development Workflow

### Local Development

```bash
# Clone repository
git clone https://github.com/robert-adlington/robert-adlington.github.io.git
cd robert-adlington.github.io

# For static projects - start simple server
python3 -m http.server 8000

# For Vue projects
cd projects/adlinkton/frontend
npm install
npm run dev
```

### Making Changes

1. Create feature branch: `git checkout -b feature/description`
2. Make changes
3. Test locally
4. Commit and push
5. Create pull request
6. After merge, GitHub Actions deploys to production

---

## Maintenance Notes

### Regular Tasks

- Review and update dependencies in Vue projects
- Monitor `api/debug.php` and `api/test.php` for diagnostics
- Check GitHub Actions workflows for deployment status

### Security

- Never commit `api/config.php` (contains database credentials)
- Never commit API keys or secrets
- Use environment variables in GitHub Actions for deployment secrets
- Review `.gitignore` before committing

---

**Last Updated:** January 2025 (Repository restructuring + Database migration guidelines + Adlinkton apiClient requirement)
