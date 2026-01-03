# Contract Whist Database API

This API provides backend services for the Contract Whist game, including user authentication and game score storage.

## Features

- User registration and authentication
- Session management with secure cookies
- Game score storage and retrieval
- RESTful API endpoints
- CORS support for cross-origin requests

## Database Setup

### 1. Configure Database Connection

**IMPORTANT:** Never commit `config.php` to the repository!

Create your local configuration file:

```bash
cd adlington/api
cp config.example.php config.php
```

Edit `config.php` and update with your actual database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_existing_database_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_secure_password');
```

### 2. Create Database Tables

The schema.sql file will add tables to your existing database. Review it first, then run:

```bash
mysql -u your_db_user -p your_database_name < schema.sql
```

Or import via phpMyAdmin or your preferred MySQL client.

**Note:** The schema uses `CREATE TABLE IF NOT EXISTS`, so it won't overwrite existing tables.

### 3. Update CORS Settings

In `config.php`, configure allowed origins for your domain:

```php
define('ALLOWED_ORIGINS', [
    'https://adlington.fr',
    'https://www.adlington.fr',
]);
```

### 4. Security Settings

**IMPORTANT:** Before going to production:
1. Change the default admin password (default: `changeme123`)
2. Set `DEBUG_MODE` to `false` in `config.php`
3. Ensure database credentials are secure
4. Enable HTTPS for secure cookie transmission
5. **NEVER** commit `config.php` to version control (already in `.gitignore`)
6. Restrict file permissions: `chmod 600 config.php`

## API Endpoints

### Authentication

#### Register New User
```
POST /api/auth-api.php?action=register
Content-Type: application/json

{
  "username": "john_doe",
  "email": "john@example.com",
  "password": "secure_password"
}
```

#### Login
```
POST /api/auth-api.php?action=login
Content-Type: application/json

{
  "username": "john_doe",
  "password": "secure_password"
}
```

#### Logout
```
POST /api/auth-api.php?action=logout
```

#### Get Current User
```
GET /api/auth-api.php?action=me
```

### Games Management

#### List User's Games
```
GET /api/games.php?limit=20&offset=0
```

#### Get Specific Game
```
GET /api/games.php?id=123
```

#### Create New Game
```
POST /api/games.php
Content-Type: application/json

{
  "game_name": "Game 2024-01-03",
  "players": ["Alice", "Bob", "Charlie", "Diana"],
  "scores": [...],
  "current_round": 10,
  "is_complete": true
}
```

#### Update Game
```
PUT /api/games.php?id=123
Content-Type: application/json

{
  "current_round": 5,
  "scores": [...]
}
```

#### Delete Game
```
DELETE /api/games.php?id=123
```

## Database Schema

### Users Table
- `id` - Auto-increment primary key
- `username` - Unique username (3-50 characters)
- `email` - Unique email address
- `password_hash` - Bcrypt hashed password
- `is_admin` - Admin flag
- `created_at` - Account creation timestamp
- `updated_at` - Last update timestamp

### Sessions Table
- `id` - Session token (128 character random string)
- `user_id` - Foreign key to users table
- `created_at` - Session creation time
- `expires_at` - Session expiration time

### Whist_Games Table
- `id` - Auto-increment primary key
- `user_id` - Foreign key to users table
- `game_name` - Game description/name
- `players` - JSON array of player names
- `scores` - JSON array of round scores
- `current_round` - Current round number
- `is_complete` - Game completion status
- `played_at` - Game creation timestamp
- `updated_at` - Last update timestamp

## File Structure

```
adlington/api/
├── README.md              # This file
├── schema.sql             # Database schema
├── config.example.php     # Configuration template (committed to repo)
├── config.php             # Your local config (NOT in repo, create from example)
├── database.php           # Database connection handler
├── auth.php               # Authentication class
├── auth-api.php           # Authentication API endpoints
├── games.php              # Games API endpoints
└── utils.php              # Utility functions
```

**Note:** `config.php` is excluded from version control via `.gitignore` to protect your database credentials.

## Session Management

- Sessions are stored in the database
- Default session duration: 30 days
- Sessions are validated on each API request
- Expired sessions are automatically rejected
- Session cleanup can be run periodically:
  ```sql
  DELETE FROM sessions WHERE expires_at < NOW();
  ```

## Security Features

- Password hashing using PHP's `password_hash()` (bcrypt)
- Prepared statements to prevent SQL injection
- CORS validation for allowed origins
- HttpOnly cookies for session tokens
- Session expiration and validation
- Input sanitization and validation
- Error message handling (verbose in debug mode, generic in production)

## Frontend Integration

The Contract Whist HTML file has been updated to use this API:

1. **Authentication**: Users can register and login with username/email and password
2. **Session Management**: Sessions are maintained via secure cookies
3. **Game Storage**: Games are automatically saved to the database
4. **Fallback**: If API is unavailable, falls back to localStorage

## Maintenance

### Cleanup Expired Sessions

Add this to your cron jobs to run daily:

```bash
0 0 * * * mysql -u user -p'password' adlington_games -e "DELETE FROM sessions WHERE expires_at < NOW();"
```

### Backup Database

Regular backups recommended:

```bash
mysqldump -u user -p adlington_games > backup_$(date +%Y%m%d).sql
```

## Troubleshooting

### CORS Errors
- Verify your domain is in the `ALLOWED_ORIGINS` array in `config.php`
- Check browser console for specific CORS error messages

### Database Connection Errors
- Verify database credentials in `config.php`
- Ensure MySQL service is running
- Check database user has proper permissions

### Authentication Issues
- Clear browser cookies and try again
- Verify session hasn't expired
- Check that cookies are enabled in browser

## License

Copyright © 2025 Adlington.fr
