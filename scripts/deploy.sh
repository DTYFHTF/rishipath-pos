#!/bin/bash
set -euo pipefail

[ -z "${HOME:-}" ] && HOME=$(getent passwd "$(id -un)" | cut -d: -f6) && export HOME

LOCK="$HOME/.deploy-rishipath-pos.lock"
BASE="$HOME/rishipath-pos"
PHP="/opt/cpanel/ea-php82/root/usr/bin/php"
COMPOSER="$HOME/.composer/vendor/bin/composer"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
export DEPLOY_BRANCH
LOG="[$(date '+%Y-%m-%d %H:%M:%S')] [rishipath-pos]"

# ── Phase 1: pull then re-exec for post-pull steps ──────────────────────────
if [[ "${1:-}" != "--post-pull" ]]; then
  if [ -f "$LOCK" ]; then
    echo "$LOG SKIPPED – deploy already running"
    exit 0
  fi
  touch "$LOCK"
  trap 'rm -f "$LOCK"' EXIT

  echo "$LOG === Deploy started (phase 1: pull) ==="

  # Repo is public — no credentials needed to fetch/pull.
  git -C "$BASE" fetch --prune origin "$DEPLOY_BRANCH"
  git -C "$BASE" reset --hard "origin/$DEPLOY_BRANCH"

  rm -f "$LOCK"
  echo "$LOG === Phase 1 done – re-executing for post-pull ==="
  exec bash "$BASE/scripts/deploy.sh" --post-pull
fi

# ── Phase 2: post-pull steps ─────────────────────────────────────────────────
if [ -f "$LOCK" ]; then
  echo "$LOG SKIPPED – deploy already running (post-pull)"
  exit 0
fi
touch "$LOCK"
trap 'rm -f "$LOCK"' EXIT

echo "$LOG === Phase 2: post-pull steps ==="
cd "$BASE"

# Resolve composer binary
COMPOSER_BIN=""
for c in "$COMPOSER" "$(which composer 2>/dev/null || true)" "/usr/local/bin/composer" "/usr/bin/composer"; do
  if [ -n "$c" ] && [ -f "$c" ]; then
    COMPOSER_BIN="$c"
    break
  fi
done
if [ -z "$COMPOSER_BIN" ]; then
  echo "$LOG Composer not found – aborting"
  exit 1
fi

echo "$LOG Running composer install..."
$PHP -d memory_limit=512M "$COMPOSER_BIN" install \
  --no-dev --optimize-autoloader --no-interaction --no-progress

echo "$LOG Running migrations..."
$PHP artisan migrate --force

echo "$LOG Seeding (safe – skip on error)..."
$PHP artisan db:seed --force 2>/dev/null || true

echo "$LOG Linking storage..."
$PHP artisan storage:link 2>/dev/null || true

echo "$LOG Publishing Filament assets..."
$PHP artisan filament:assets 2>/dev/null || true

echo "$LOG Clearing & warming cache..."
$PHP artisan optimize:clear
$PHP artisan optimize

echo "$LOG === Deploy complete ==="
