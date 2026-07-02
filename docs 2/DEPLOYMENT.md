# Deployment Setup

## Overview
The repository includes two CI/CD workflows:
- **CI Workflow** (`.github/workflows/ci.yml`): Runs tests, code style checks (Pint), and static analysis (PHPStan) on all PRs and pushes to `main`/`dev`.
- **Deploy Workflow** (`.github/workflows/deploy.yml`): Deploys to staging/production environments.

## Deploy Workflow Configuration

### Required Secrets
Add these in **Settings → Secrets and variables → Actions → Secrets**:

1. `SSH_PRIVATE_KEY`: SSH private key for server access
   ```bash
   cat ~/.ssh/id_rsa  # or your deploy key
   ```

### Required Variables
Add these in **Settings → Secrets and variables → Actions → Variables**:

1. `DEPLOY_HOST`: Server hostname or IP (e.g., `staging.example.com`)
2. `DEPLOY_USER`: SSH username (e.g., `forge`, `deployer`)
3. `DEPLOY_PATH`: Absolute path on server (e.g., `/var/www/rishipath-pos`)

### Environment Setup
Create environments in **Settings → Environments**:
- `staging`: Auto-deploy on push to `main`
- `production`: Require manual approval

### Deployment Methods

#### 1. Automatic (on push to main)
```bash
git push origin main  # Deploys to staging automatically
```

#### 2. Manual Dispatch
In GitHub:
1. Go to **Actions** → **Deploy** workflow
2. Click **Run workflow**
3. Select environment (staging/production)
4. Click **Run workflow**

#### 3. Command Line
```bash
gh workflow run deploy.yml -f environment=production
```

### Server Prerequisites
The target server should have:
- PHP 8.4+ with required extensions
- Composer installed
- MySQL/MariaDB
- `.env` file configured at `$DEPLOY_PATH`
- Write permissions for deployer user
- Queue worker running (if using queues)

### Post-Deploy Commands
The workflow automatically runs:
```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

### Excluded Files
The rsync deployment excludes:
- `.git/` 
- `node_modules/`
- `storage/logs/*`
- `storage/framework/cache/*`
- `storage/framework/sessions/*`
- `.env` (uses server's existing .env)

## Branch Protection
Branch protection on `main` requires:
- ✅ Tests job passing
- ✅ Code Style (Pint) job passing
- ✅ Static Analysis (PHPStan) job passing
- ✅ 1 approving review
- ✅ Enforce for administrators

Update required checks if job names change.

## Codecov (Optional)
For private repos, add `CODECOV_TOKEN` secret:
1. Go to [codecov.io](https://codecov.io) and link your repo
2. Copy the upload token
3. Add as `CODECOV_TOKEN` in GitHub Secrets

Public repos work without a token.

## Example Deploy Flow
```bash
# 1. Make changes on feature branch
git checkout -b feature/new-feature
# ... make changes ...
git commit -am "feat: add new feature"
git push origin feature/new-feature

# 2. Create PR to dev
gh pr create --base dev --title "Add new feature"

# 3. CI runs (tests, pint, phpstan)
# 4. After approval, merge to dev
gh pr merge

# 5. Create PR from dev to main
git checkout dev
git pull
gh pr create --base main --title "Release: new feature"

# 6. CI runs again on main PR
# 7. After approval, merge to main
gh pr merge

# 8. Deploy workflow automatically deploys to staging
# 9. Manually dispatch deploy to production when ready
gh workflow run deploy.yml -f environment=production
```

## Troubleshooting

### CI Failures
- Check migration order if DB errors occur
- Run `composer install` and tests locally first
- Ensure all dependencies in `composer.json`

### Deploy Failures
- Verify SSH key has access to server
- Check `DEPLOY_PATH` exists and has correct permissions
- Ensure `.env` on server is properly configured
- Check server has sufficient disk space

### Branch Protection Blocks Push
Create a PR instead:
```bash
git checkout -b fix/urgent-fix
# ... fix ...
git commit -am "fix: urgent fix"
git push origin fix/urgent-fix
gh pr create --base main --fill
gh pr merge --auto --squash  # After CI passes
```
