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
cd api
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

### 4. Configure Email for Password Resets

The password reset feature requires email configuration. Edit `config.php`:

```php
// Site Configuration
define('SITE_URL', 'https://yourdomain.com'); // Your site URL (no trailing slash)
define('SITE_NAME', 'Adlington.fr');

// Email Configuration
define('EMAIL_METHOD', 'mail'); // 'mail' or 'smtp'
define('EMAIL_FROM_ADDRESS', 'noreply@yourdomain.com');
define('EMAIL_FROM_NAME', 'Adlington.fr');

// Password reset token expiry (default: 1 hour)
define('RESET_TOKEN_EXPIRY', 60 * 60);
```

**Email Options:**

1. **Using PHP `mail()` function (default):**
   - Set `EMAIL_METHOD` to `'mail'`
   - Requires your server to have `sendmail` or similar configured
   - Works out-of-the-box on most shared hosting

2. **Using SMTP (optional):**
   - Set `EMAIL_METHOD` to `'smtp'`
   - Configure SMTP settings in `config.php`:
   ```php
   define('SMTP_HOST', 'smtp.example.com');
   define('SMTP_PORT', 587); // Usually 587 for TLS
   define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
   define('SMTP_USERNAME', 'your_email@example.com');
   define('SMTP_PASSWORD', 'your_email_password');
   ```
   - **Note:** SMTP implementation requires PHPMailer library (not included)

**Testing Email:**
- After configuration, test by requesting a password reset
- Check your email spam folder if emails don't arrive
- Verify `SITE_URL` is correct (used in reset links)

### 5. Security Settings

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

#### Request Password Reset
```
POST /api/auth-api.php?action=request-reset
Content-Type: application/json

{
  "email": "john@example.com"
}
```

**Response:** Always returns success (doesn't reveal if email exists for security).

**Note:** Sends an email with a password reset link valid for 1 hour.

#### Verify Reset Token
```
POST /api/auth-api.php?action=verify-token
Content-Type: application/json

{
  "token": "abc123..."
}
```

**Response:**
```json
{
  "valid": true
}
```

#### Reset Password
```
POST /api/auth-api.php?action=reset-password
Content-Type: application/json

{
  "token": "abc123...",
  "password": "new_secure_password"
}
```

**Note:** Invalidates all existing sessions after password reset.

### Players Management

Players are unique by name and shared across user accounts. Statistics are tracked per player across all games.

#### Check if Player Exists
```
GET /api/players.php?check=PlayerName
```

**Response (if exists):**
```json
{
  "exists": true,
  "player": {
    "id": 123,
    "name": "PlayerName",
    "total_games": 15,
    "last_game_location": "Location recorded",
    "last_game_date": "2024-01-03 14:30:00"
  }
}
```

#### Get Player with Statistics
```
GET /api/players.php?id=123
```

#### Search Players
```
GET /api/players.php?search=query&limit=20&offset=0
```

#### Create Player
```
POST /api/players.php
Content-Type: application/json

{
  "name": "NewPlayerName"
}
```

### Friends Management

Friends are the user's favorite players, displayed when creating a new game.

#### List User's Friends
```
GET /api/friends.php
```

**Response:**
```json
{
  "friends": [
    {
      "id": 1,
      "name": "Alice",
      "statistics": {
        "total_games": 15,
        "games_won": 5
      }
    }
  ],
  "total": 1
}
```

#### Add Friend

Create new player and add as friend:
```
POST /api/friends.php
Content-Type: application/json

{
  "name": "NewFriendName"
}
```

Or add existing player as friend:
```
POST /api/friends.php
Content-Type: application/json

{
  "player_id": 123
}
```

#### Remove Friend
```
DELETE /api/friends.php?player_id=123
```

**Note:** Removing a friend does NOT delete the player record or their statistics.

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

Using player IDs (preferred):
```
POST /api/games.php
Content-Type: application/json

{
  "game_name": "Game 2024-01-03",
  "player_ids": [1, 2, 3, 4],
  "scores": [...],
  "current_round": 10,
  "is_complete": true
}
```

Using player names (legacy support, creates players if needed):
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

### Password_Reset_Tokens Table
- `token` - Reset token (64 character random string)
- `user_id` - Foreign key to users table
- `created_at` - Token creation time
- `expires_at` - Token expiration time (default: 1 hour)
- `used_at` - Timestamp when token was used (NULL if unused)

### Players Table
- `id` - Auto-increment primary key
- `name` - Unique player name identifier
- `created_by_user_id` - User who first created this player
- `created_at` - Player creation timestamp

### User_Friends Table
- `id` - Auto-increment primary key
- `user_id` - Foreign key to users table
- `player_id` - Foreign key to players table
- `created_at` - When the friend was added
- Unique constraint on (user_id, player_id)

### Whist_Games Table
- `id` - Auto-increment primary key
- `user_id` - Foreign key to users table
- `game_name` - Game description/name
- `players` - JSON array of player names (legacy support)
- `scores` - JSON array of round scores
- `current_round` - Current round number
- `is_complete` - Game completion status
- `ip_address` - Client IP address for location tracking
- `played_at` - Game creation timestamp
- `updated_at` - Last update timestamp

### Game_Players Table
- `id` - Auto-increment primary key
- `game_id` - Foreign key to whist_games table
- `player_id` - Foreign key to players table
- `position` - Player position in game (0-indexed)
- `final_score` - Final score when game is complete
- `won` - Whether this player won the game
- Unique constraints on (game_id, position) and (game_id, player_id)

## File Structure

```
api/
├── README.md              # This file
├── schema.sql             # Database schema
├── config.example.php     # Configuration template (committed to repo)
├── config.php             # Your local config (NOT in repo, create from example)
├── database.php           # Database connection handler
├── auth.php               # Authentication class
├── auth-api.php           # Authentication API endpoints
├── games.php              # Games API endpoints
├── players.php            # Players API endpoints
├── friends.php            # Friends/favorites API endpoints
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
