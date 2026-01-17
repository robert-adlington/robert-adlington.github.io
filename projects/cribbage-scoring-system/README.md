# Cribbage Scoring System

A digital scoring system for cribbage games, designed for iPad use with physical cards.

## Features

- **Two-Player Scoring**: Track scores for two players with visual feedback
- **Traditional Cribbage Rules**: First to 121 points wins, skunks count for 2 game wins (opponent < 90 points)
- **Visual Board Themes**:
  - Classic wooden cribbage board with 121 holes (60+60+1)
  - Mountain Climb theme
  - Skiing theme
  - Moon Flight theme
- **Two-Peg System**: Shows current score with leading peg and last points gained with trailing peg
- **Session Tracking**: Track multiple games in a session with running totals
- **Dealer Rotation**: Automatically tracks and rotates dealer status
- **Undo Functionality**: Undo the last scoring move

## Setup

### Database Migration

Run the migration to create necessary tables:

```bash
mysql -u your_user -p your_database < migrations/001_create_cribbage_tables.sql
```

### Frontend Development

```bash
cd frontend
npm install
npm run dev
```

### Production Build

```bash
cd frontend
npm run build
```

The built files will be in the `dist` directory.

## Usage

1. Enter player names to start a new session
2. Players score points using the scoring keypad
3. The board visually shows progress with two pegs (current position and last move)
4. First to 121 wins; winning before opponent reaches 90 is a skunk (worth 2 game wins)
5. Start new games within the same session to track overall wins
6. Change board themes for variety

## API Endpoints

- `GET /api/sessions.php` - List all sessions
- `GET /api/sessions.php?id={id}` - Get session details with current game
- `POST /api/sessions.php` - Create new session
- `POST /api/games.php` - Start new game in session
- `POST /api/moves.php` - Add points to current game
- `DELETE /api/moves.php?id={id}` - Undo a move

## Technology Stack

- **Frontend**: Vue.js 3 + Pinia + Tailwind CSS + Vite
- **Backend**: PHP with PDO
- **Database**: MySQL/MariaDB
- **Authentication**: Integrated with existing site auth system
