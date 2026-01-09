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

**Last Updated:** January 2025 (Repository restructuring)
