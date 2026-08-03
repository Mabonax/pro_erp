#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
SCRIPT="$ROOT_DIR/scripts/deployment/pull-release-deploy.sh"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

cat > "$TMP_DIR/config.env" <<EOF
GITHUB_TOKEN=test-token
GITHUB_REPOSITORY=Mabonax/pro_erp
DEPLOY_ROOT=$TMP_DIR/deploy
LOG_DIR=$TMP_DIR/deploy/logs
STATE_FILE=$TMP_DIR/deploy/state/deployment-state.json
LOCK_DIR=$TMP_DIR/deploy/locks
TMP_DIR=$TMP_DIR/deploy/tmp
ERP_PATH=$TMP_DIR/erp-live
WEBSITE_PATH=$TMP_DIR/website-live
WEBSITE_PUBLIC_ROOT=$TMP_DIR/public_html
ERP_URL=https://erp.example.test
WEBSITE_URL=https://programofaction.example.test
PHP_BIN=php
COMPOSER_BIN=composer
CURL_BIN=curl
TAR_BIN=tar
SHA256_BIN=sha256sum
RSYNC_BIN=rsync
EOF

mkdir -p "$TMP_DIR/erp-live" "$TMP_DIR/website-live" "$TMP_DIR/public_html"
touch "$TMP_DIR/erp-live/.env" "$TMP_DIR/website-live/.env"

POA_RELEASE_CONFIG="$TMP_DIR/config.env" bash "$SCRIPT" --self-test

printf 'pull-release-deploy self-test passed\n'
