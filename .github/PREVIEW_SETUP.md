# PR Preview Deployment Setup

This repository is configured with automated PR preview deployments. Follow these steps to enable them.


## Prerequisites

The preview system uses GitHub Pages to host PR previews. You need to:

1. **Enable GitHub Pages** in your repository
2. **Create the gh-pages branch** manually (due to branch protection)
3. **Configure Pages to deploy** from the gh-pages branch

## Setup Steps

### 1. Create the gh-pages Branch

Run these commands locally:

```bash
# Create an orphan gh-pages branch
git checkout --orphan gh-pages

# Remove all files
git rm -rf .

# Create initial structure
mkdir -p pr-preview
echo "# PR Preview Deployments" > README.md
echo "PR previews will appear here automatically." > pr-preview/README.md

# Commit and push
git add .
git commit -m "Initialize gh-pages for PR previews"
git push origin gh-pages

# Switch back to your main branch
git checkout main  # or your default branch
```

### 2. Enable GitHub Pages

1. Go to your repository on GitHub
2. Click **Settings** → **Pages**
3. Under "Build and deployment":
   - **Source:** Deploy from a branch
   - **Branch:** Select `gh-pages` and `/` (root)
4. Click **Save**

### 3. Verify GitHub Actions Permissions

1. Go to **Settings** → **Actions** → **General**
2. Under "Workflow permissions":
   - Select **Read and write permissions**
   - Check **Allow GitHub Actions to create and approve pull requests**
3. Click **Save**

## How It Works

Once set up, the system automatically:

1. **On PR Open/Update:**
   - Deploys PR content to `gh-pages` branch in `pr-preview/pr-{number}/`
   - Posts a comment with the preview URL
   - Updates on each new commit

2. **On PR Close:**
   - Removes the preview directory from gh-pages
   - Posts a cleanup comment

## Preview URL Format

```
https://robert-adlington.github.io/robert-adlington.github.io/pr-preview/pr-{NUMBER}/adlington/
```

Example: PR #5 would be at:
```
https://robert-adlington.github.io/robert-adlington.github.io/pr-preview/pr-5/adlington/
```

## Troubleshooting

### Workflow fails with "failed to push"
- Check that GitHub Actions has write permissions (see step 3 above)
- Verify the gh-pages branch exists

### Preview URL returns 404
- Wait 1-2 minutes for GitHub Pages to build
- Check that GitHub Pages is enabled and set to gh-pages branch
- Verify the workflow completed successfully

### Preview not updating
- Check the Actions tab for workflow run status
- Ensure new commits are being pushed (not just local changes)

## Manual Testing

To test a PR preview manually:

1. Checkout the PR branch locally
2. Run: `cd adlington && python3 -m http.server 8000`
3. Visit: `http://localhost:8000/`

This simulates what the preview will show.

## Disabling Previews

If you want to disable PR previews:

1. Delete the workflow files:
   - `.github/workflows/pr-preview.yml`
   - `.github/workflows/pr-preview-cleanup.yml`

2. Optionally delete the gh-pages branch:
   ```bash
   git push origin --delete gh-pages
   ```
