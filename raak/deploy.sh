#!/usr/bin/env bash
# deploy.sh - build and upload the site from the project root
# Usage: ./deploy.sh
# This script assumes you have node/npm (or nvm) and php-cli installed and working,
# and that your network can reach the FTP server used by upload.php.

set -euo pipefail
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_DIR"

echo "Project dir: $PROJECT_DIR"

# Check tools
command -v php >/dev/null 2>&1 || { echo "php CLI not found. Please install php-cli."; exit 2; }
command -v npm >/dev/null 2>&1 || echo "npm not found; if you're using nvm, ensure 'nvm use <version>' is active in this shell."

# Optional: print versions
echo "php: $(php -v | head -n1)"
if command -v npm >/dev/null 2>&1; then
  echo "node: $(node -v 2>/dev/null || echo 'node not found')"
  echo "npm: $(npm -v 2>/dev/null || echo 'npm not found')"
fi

# Install dependencies (if package.json exists)
if [ -f package.json ]; then
  echo "Installing npm dependencies..."
  # prefer ci for reproducible install; fall back to install
  if command -v npm >/dev/null 2>&1; then
    if [ -f package-lock.json ]; then
      npm ci
    else
      npm install
    fi
  else
    echo "npm not available; skipping JS build steps"
  fi
fi

# Build (if applicable)
if [ -f package.json ] && command -v npm >/dev/null 2>&1; then
  echo "Running build: PUBLIC_URL=/ npm run build"
  PUBLIC_URL=/ npm run build
  echo "Build completed. build/ size: $(du -sh build | awk '{print $1}')"
else
  echo "No JS build step required or npm missing. Skipping build."
fi

# Upload changed files - you can switch to a selective upload by giving filenames
# Default: full upload
read -p "Upload full site (build/ and php/) to FTP? [y/N]: " yn
yn=${yn:-N}
if [[ "$yn" =~ ^[Yy]$ ]]; then
  echo "Starting full upload via php upload.php"
  php upload.php
else
  echo "Uploading selective files: form_smakelijk_wandelen.html smakelijk_wandelen.php insert_smakelijk_2026.php"
  php upload.php form_smakelijk_wandelen.html smakelijk_wandelen.php insert_smakelijk_2026.php
fi

# Optionally call the insert helper to ensure 2026 entry exists
read -p "Call server helper to insert Smakelijk 2026 if missing? (will call https://raakachterbos.be/php/insert_smakelijk_2026.php) [y/N]: " call_insert
call_insert=${call_insert:-N}
if [[ "$call_insert" =~ ^[Yy]$ ]]; then
  echo "Calling https://raakachterbos.be/php/insert_smakelijk_2026.php"
  if command -v curl >/dev/null 2>&1; then
    curl -sS https://raakachterbos.be/php/insert_smakelijk_2026.php | jq . || true
  else
    echo "curl not installed; please open the URL in your browser: https://raakachterbos.be/php/insert_smakelijk_2026.php"
  fi
fi

echo "Done. After upload, do a hard-refresh (Ctrl+F5) on the form page or purge CDN cache if you use one."
