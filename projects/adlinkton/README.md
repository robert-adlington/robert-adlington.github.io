# Adlinkton

A personal link management system with rich organization through nested categories, tags, and smart categories. Features a desktop-first SPA interface emphasizing keyboard-driven productivity and flexible layouts.

## Features

- **Desktop-first design** with mobile read-only access + quick link addition
- **Compact UI** optimized for displaying many links efficiently
- **Keyboard-driven** with configurable shortcuts
- **Flexible layouts** including split-screen with resizable panes
- **Dual organization** via manual categories and tag-based smart categories
- **Favicon management** with automatic fetching and fallback generation
- **Full-text search** across link names, descriptions, and URLs
- **Analytics tracking** for link access patterns
- **Browser bookmark import** from Netscape HTML format

## Tech Stack

### Backend
- **PHP** - Server-side logic
- **MySQL** - Database
- **Session-based authentication** - Integrated with adlington.fr

### Frontend
- **Vue 3** - JavaScript framework
- **Pinia** - State management
- **Vite** - Build tool
- **Tailwind CSS** - Styling
- **SortableJS** - Drag & drop
- **Split.js** - Resizable panes
- **Axios** - HTTP client

## Project Structure

```
adlinkton/
├── api/                      # PHP backend
│   ├── index.php            # Main router
│   ├── auth.php             # Authentication
│   ├── links.php            # Link endpoints
│   ├── categories.php       # Category endpoints
│   ├── smart-categories.php # Smart category endpoints
│   ├── tags.php             # Tag endpoints
│   ├── settings.php         # Settings & layouts
│   ├── import.php           # Bookmark import
│   └── helpers/
│       ├── db.php           # Database connection
│       ├── response.php     # JSON responses
│       ├── validation.php   # Input validation
│       └── favicon.php      # Favicon handling
│
├── frontend/                # Vue 3 SPA
│   ├── src/
│   │   ├── main.js         # Entry point
│   │   ├── App.vue         # Root component
│   │   ├── stores/         # Pinia stores
│   │   ├── components/     # Vue components
│   │   ├── composables/    # Composition functions
│   │   ├── api/            # API client
│   │   └── styles/         # CSS/Tailwind
│   ├── index.html
│   ├── vite.config.js
│   ├── package.json
│   └── tailwind.config.js
│
├── storage/
│   └── favicons/            # Cached favicons
│
├── dist/                    # Built frontend
│
├── migrations/
│   └── 001_initial_schema.sql
│
└── README.md
```

## Setup Instructions

### 1. Database Setup

Run the database migration to create all required tables:

```bash
mysql -u [username] -p [database] < migrations/001_initial_schema.sql
```

The migration creates the following tables:
- `links` - Link storage
- `categories` - Hierarchical categories
- `link_categories` - Link-category relationships
- `tags` - Tags
- `link_tags` - Link-tag relationships
- `smart_categories` - Tag-based smart categories
- `user_settings` - User preferences
- `saved_layouts` - Saved pane layouts

### 2. Backend Configuration

The API automatically uses the existing adlington.fr database configuration. Ensure the `api/helpers/db.php` file correctly references your database config.

### 3. Frontend Setup

Install dependencies and build:

```bash
cd frontend
npm install
```

For development:
```bash
npm run dev
```

For production build:
```bash
npm run build
```

The built files will be output to the `dist/` directory.

### 4. File Permissions

Ensure the storage directory is writable:

```bash
chmod 755 storage
chmod 755 storage/favicons
```

## Development

### Running in Development Mode

1. Start the development server:
```bash
cd frontend
npm run dev
```

2. Access the application at `http://localhost:3000`

The Vite dev server will proxy API requests to your local server.

### API Endpoints

All API endpoints are prefixed with `/adlington/projects/adlinkton/api/`

**Links:**
- `GET /links` - List links (with filters)
- `GET /links/{id}` - Get single link
- `POST /links` - Create link
- `PUT /links/{id}` - Update link
- `DELETE /links/{id}` - Delete link
- `POST /links/{id}/open` - Record access
- `PUT /links/{id}/reorder` - Reorder link
- `POST /links/bulk` - Bulk operations

**Categories:**
- `GET /categories` - List categories (tree)
- `GET /categories/{id}` - Get category
- `POST /categories` - Create category
- `PUT /categories/{id}` - Update category
- `DELETE /categories/{id}` - Delete category
- `PUT /categories/{id}/reorder` - Reorder category
- `POST /categories/{id}/open-all` - Get all URLs

**Tags:**
- `GET /tags` - List tags
- `POST /tags` - Create tag
- `PUT /tags/{id}` - Update tag
- `DELETE /tags/{id}` - Delete tag

**Smart Categories:**
- `GET /smart-categories` - List smart categories
- `GET /smart-categories/{id}` - Get smart category
- `GET /smart-categories/inbox` - Get inbox
- `POST /smart-categories` - Create smart category
- `PUT /smart-categories/{id}` - Update smart category
- `DELETE /smart-categories/{id}` - Delete smart category

**Settings:**
- `GET /settings` - Get user settings
- `PUT /settings` - Update settings
- `GET /layouts` - List saved layouts
- `POST /layouts` - Save layout
- `PUT /layouts/{id}` - Update layout
- `DELETE /layouts/{id}` - Delete layout

**Import:**
- `POST /import/bookmarks` - Import browser bookmarks

## Implementation Status

### ✅ Phase 1: Foundation (Setup Complete)
- [x] Database schema and migrations
- [x] Basic PHP API structure with auth
- [x] Frontend project setup with routing
- [ ] Basic CRUD for links and categories

### 🚧 Phase 2: Core Features (Pending)
- [ ] Favicon fetching and fallback
- [ ] Tag system
- [ ] Smart categories with queries
- [ ] Inbox system category
- [ ] Search implementation

### 📋 Phase 3: Organization (Pending)
- [ ] Drag-and-drop reordering
- [ ] Category tree with nesting
- [ ] Display mode inheritance
- [ ] Multiple sort options

### 📋 Phase 4: Productivity (Pending)
- [ ] Keyboard shortcuts
- [ ] Split-screen panes
- [ ] Saved layouts
- [ ] Bulk operations

### 📋 Phase 5: Polish (Pending)
- [ ] Mobile-responsive layout
- [ ] Bookmark import
- [ ] Analytics display
- [ ] Fullscreen mode
- [ ] Open all / incognito hints

## Default Keyboard Shortcuts

| Action | Key |
|--------|-----|
| Next link | `j` |
| Previous link | `k` |
| Open link | `o` or `Enter` |
| Toggle favorite | `f` |
| Edit link | `e` |
| Delete link | `Delete` |
| Copy URL | `c` |
| Search | `/` |
| Fullscreen | `F11` |
| Exit fullscreen | `Escape` |

## Security Considerations

- All API endpoints require valid session authentication
- User ID verification on all database queries
- Input validation and sanitization
- CSRF token support
- SQL injection prevention via prepared statements
- XSS protection via output escaping

## License

Private project for personal use.

## Documentation

See `../../../specifications/link-management-system-spec.md` for the complete technical specification.
