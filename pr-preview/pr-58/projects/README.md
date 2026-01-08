# Adlington Projects

This folder contains all the projects hosted on the Adlington website.

## Current Projects

### 🃏 Contract Whist
**Path:** `contract-whist/`

A score tracker application for the Contract Whist card game. Features:
- Real-time score tracking
- Multi-player support
- Game history
- Requires user authentication

**Access:** Requires login at [adlington.fr](https://adlington.fr)

### 🎲 Random Robby
**Path:** `random-robby/`

A fun random selection tool with various modes:
- Coin flip
- Dice roller
- Random name picker
- Custom random generators

**Access:** Public - [adlington.fr/projects/random-robby/](https://adlington.fr/projects/random-robby/)

## Adding a New Project

To add a new project:

1. Create a new folder: `projects/your-project-name/`
2. Add your `index.html` file inside
3. Update `index.html` to add a project card
4. Test locally before pushing

See [CONTRIBUTING.md](../../CONTRIBUTING.md) for detailed instructions.

## Project Structure

Each project should follow this structure:

```
your-project-name/
├── index.html          # Main project file
├── README.md           # (Optional) Project documentation
└── assets/             # (Optional) Images, CSS, JS files
    ├── style.css
    ├── script.js
    └── images/
```

## API Access

Projects can access the shared API at `/api/` for:
- User authentication
- Database storage
- Shared utilities

See `api/README.md` for API documentation.
