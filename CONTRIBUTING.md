# Contributing Guide

Welcome! This guide will help you contribute to the Adlington website project.

## Getting Started

### 1. Clone the Repository

```bash
git clone https://github.com/robert-adlington/robert-adlington.github.io.git
cd robert-adlington.github.io
```

### 2. Create a Feature Branch

Always create a new branch for your changes. Never work directly on `main`.

```bash
# Create and switch to a new branch
git checkout -b feature/your-feature-name

# Examples:
git checkout -b feature/new-game-project
git checkout -b fix/broken-link
git checkout -b update/improve-styling
```

**Branch Naming Convention:**
- `feature/` - for new features or projects
- `fix/` - for bug fixes
- `update/` - for improvements to existing features
- `docs/` - for documentation updates

### 3. Make Your Changes

Edit files as needed. The main project structure:

```
adlington/
├── index.html              # Main dashboard page
├── projects/
│   ├── contract-whist/     # Contract Whist project
│   └── random-robby/       # Random Robby project
└── api/                    # Backend API files
```

### 4. Test Locally

Before pushing, always test your changes locally:

```bash
# Navigate to the adlington folder
cd adlington

# Start a local web server
python3 -m http.server 8000

# Open in browser: http://localhost:8000/
```

**Testing Checklist:**
- [ ] Page loads without errors
- [ ] Check browser console (F12) for JavaScript errors
- [ ] All links work correctly
- [ ] Test on mobile size (responsive design)
- [ ] API calls work (if applicable)

### 5. Commit Your Changes

```bash
# Stage your changes
git add .

# Or stage specific files
git add adlington/projects/new-project/index.html

# Commit with a clear message
git commit -m "Add new dice roller project"
```

**Good Commit Messages:**
- ✅ "Add new dice roller project"
- ✅ "Fix broken link in Random Robby"
- ✅ "Update Contract Whist styling"
- ❌ "changes"
- ❌ "fix stuff"

### 6. Push Your Branch

```bash
# Push your branch to GitHub
git push -u origin feature/your-feature-name
```

### 7. Create a Pull Request

1. Go to: https://github.com/robert-adlington/robert-adlington.github.io
2. Click **Pull requests** → **New pull request**
3. Select your branch
4. Fill out the PR template:
   - Describe what you changed
   - Check all the boxes in the checklist
   - Add screenshots if you changed something visual
5. Click **Create pull request**

### 8. Review Process

- Your PR will automatically run checks (GitHub Actions)
- A reviewer will look at your changes
- They may ask questions or request changes
- Once approved, they'll merge it to `main`

## Project Structure

### Adding a New Project

New projects go in `adlington/projects/`:

```
adlington/projects/
└── your-project-name/
    └── index.html
```

Then add a card to `adlington/index.html` in the Projects section:

```html
<a href="https://adlington.fr/projects/your-project-name/" class="project-card card">
    <div class="project-icon">🎮</div>
    <div>
        <h3 class="card-title">Your Project Name</h3>
        <p class="card-excerpt">Brief description</p>
    </div>
</a>
```

## Common Tasks

### Updating an Existing Project

```bash
git checkout -b update/improve-random-robby
# Edit adlington/projects/random-robby/index.html
git add adlington/projects/random-robby/index.html
git commit -m "Improve Random Robby UI"
git push -u origin update/improve-random-robby
# Create PR on GitHub
```

### Fixing a Bug

```bash
git checkout -b fix/broken-contract-whist-link
# Fix the bug
git add .
git commit -m "Fix broken link in Contract Whist"
git push -u origin fix/broken-contract-whist-link
# Create PR on GitHub
```

## Tips & Best Practices

### DO:
- ✅ Test locally before pushing
- ✅ Write clear commit messages
- ✅ Keep changes focused (one feature per PR)
- ✅ Check browser console for errors
- ✅ Ask questions if unsure

### DON'T:
- ❌ Push directly to `main` (it's protected anyway)
- ❌ Commit large files without asking first
- ❌ Change unrelated files in the same PR
- ❌ Forget to test your changes

## Getting Help

- **Git Questions**: https://git-scm.com/doc
- **GitHub Questions**: https://docs.github.com
- **HTML/CSS/JS**: https://developer.mozilla.org
- **Ask Dad!** 😊

## Useful Git Commands

```bash
# Check what branch you're on
git branch

# See what files changed
git status

# See what you changed in a file
git diff

# Switch to a different branch
git checkout branch-name

# Get latest changes from main
git checkout main
git pull origin main

# Update your branch with latest main
git checkout your-feature-branch
git merge main

# Undo uncommitted changes
git checkout -- filename.html
```

## Example Workflow

Here's a complete example of adding a new project:

```bash
# 1. Start from main and get latest
git checkout main
git pull origin main

# 2. Create feature branch
git checkout -b feature/add-coin-flip

# 3. Create project folder
mkdir -p adlington/projects/coin-flip

# 4. Create index.html
# ... write your code ...

# 5. Update main dashboard
# Edit adlington/index.html to add project card

# 6. Test locally
cd adlington
python3 -m http.server 8000
# Test in browser

# 7. Commit
git add .
git commit -m "Add coin flip project"

# 8. Push
git push -u origin feature/add-coin-flip

# 9. Create PR on GitHub
# 10. Wait for review and approval
# 11. Celebrate! 🎉
```

Happy coding!
