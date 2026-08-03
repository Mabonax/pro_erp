#!/usr/bin/env bash
set -Eeuo pipefail

CONFIG_FILE="${POA_RELEASE_CONFIG:-/home/prograg9g3o8/deploy/poa-release/config.env}"

umask 077

die() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

log() {
  printf '[%s] %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*"
}

load_config() {
  [[ -f "$CONFIG_FILE" ]] || die "Missing config file: $CONFIG_FILE"
  while IFS='=' read -r key value || [[ -n "${key:-}" ]]; do
    [[ -z "${key// }" || "${key:0:1}" == "#" ]] && continue
    [[ "$key" =~ ^[A-Z0-9_]+$ ]] || die "Invalid config key: $key"
    case "$key" in
      GITHUB_TOKEN|GITHUB_REPOSITORY|GITHUB_API_URL|RELEASE_TAG_PREFIX|DEPLOY_ROOT|LOG_DIR|STATE_FILE|LOCK_DIR|TMP_DIR|ERP_PATH|WEBSITE_PATH|WEBSITE_PUBLIC_ROOT|ERP_URL|WEBSITE_URL|PHP_BIN|COMPOSER_BIN|CURL_BIN|TAR_BIN|SHA256_BIN|RSYNC_BIN|RETAIN_RELEASES|USE_SYMLINKS|WEBSITE_PUBLIC_ROOT_MANAGED)
        printf -v "$key" '%s' "$value"
        export "$key"
        ;;
      *)
        die "Unsupported config key: $key"
        ;;
    esac
  done < "$CONFIG_FILE"
}

require_config() {
  local name
  for name in "$@"; do
    [[ -n "${!name:-}" ]] || die "$name is not configured"
  done
}

require_command() {
  local path="$1"
  [[ -x "$path" ]] || command -v "$path" >/dev/null 2>&1 || die "Required command is not executable or on PATH: $path"
}

json_extract() {
  local file="$1"
  local path="$2"
  "$PHP_BIN" -r '
    $file = $argv[1];
    $path = explode(".", $argv[2]);
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) { fwrite(STDERR, "Invalid JSON\n"); exit(2); }
    $value = $data;
    foreach ($path as $segment) {
      if (!is_array($value) || !array_key_exists($segment, $value)) { exit(3); }
      $value = $value[$segment];
    }
    if (is_array($value)) { echo json_encode($value, JSON_UNESCAPED_SLASHES); }
    else { echo (string) $value; }
  ' "$file" "$path"
}

latest_release_json() {
  "$CURL_BIN" -fsS --retry 2 --retry-delay 2 \
    -H "Accept: application/vnd.github+json" \
    -H "Authorization: Bearer $GITHUB_TOKEN" \
    -H "X-GitHub-Api-Version: 2022-11-28" \
    "$GITHUB_API_URL/repos/$GITHUB_REPOSITORY/releases?per_page=20"
}

select_release() {
  local releases_file="$1"
  "$PHP_BIN" -r '
    $prefix = $argv[2];
    $releases = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($releases)) { fwrite(STDERR, "Invalid releases JSON\n"); exit(2); }
    foreach ($releases as $release) {
      if (!empty($release["draft"]) || !empty($release["prerelease"])) { continue; }
      $tag = (string) ($release["tag_name"] ?? "");
      if ($prefix !== "" && !str_starts_with($tag, $prefix)) { continue; }
      echo json_encode($release, JSON_UNESCAPED_SLASHES);
      exit(0);
    }
    exit(4);
  ' "$releases_file" "$RELEASE_TAG_PREFIX"
}

asset_api_url() {
  local release_file="$1"
  local asset_name="$2"
  "$PHP_BIN" -r '
    $release = json_decode(file_get_contents($argv[1]), true);
    $wanted = $argv[2];
    foreach (($release["assets"] ?? []) as $asset) {
      if (($asset["name"] ?? "") === $wanted) {
        echo $asset["url"];
        exit(0);
      }
    }
    exit(5);
  ' "$release_file" "$asset_name"
}

download_asset() {
  local release_file="$1"
  local asset_name="$2"
  local output="$3"
  local url
  url="$(asset_api_url "$release_file" "$asset_name")" || die "Release asset not found: $asset_name"
  "$CURL_BIN" -fsS --retry 2 --retry-delay 2 \
    -H "Accept: application/octet-stream" \
    -H "Authorization: Bearer $GITHUB_TOKEN" \
    -H "X-GitHub-Api-Version: 2022-11-28" \
    -o "$output" \
    "$url"
}

validate_sha256() {
  local file="$1"
  local expected="$2"
  [[ "$expected" =~ ^[a-fA-F0-9]{64}$ ]] || die "Invalid SHA-256 value for $file"
  local actual
  actual="$("$SHA256_BIN" "$file" | awk '{print $1}')"
  [[ "$actual" == "$expected" ]] || die "Checksum mismatch for $file"
}

validate_archive_paths() {
  local archive="$1"
  local entry
  while IFS= read -r entry; do
    [[ -n "$entry" ]] || die "Archive contains an empty path"
    [[ "$entry" != /* ]] || die "Archive contains an absolute path: $entry"
    [[ "$entry" != *".."* ]] || die "Archive contains a parent traversal path: $entry"
  done < <("$TAR_BIN" -tzf "$archive")
}

validate_manifest() {
  local manifest="$1"
  local release_id erp_archive website_archive erp_sha website_sha
  release_id="$(json_extract "$manifest" release_id)"
  erp_archive="$(json_extract "$manifest" erp.archive)"
  website_archive="$(json_extract "$manifest" website.archive)"
  erp_sha="$(json_extract "$manifest" erp.sha256)"
  website_sha="$(json_extract "$manifest" website.sha256)"
  [[ "$release_id" =~ ^[A-Za-z0-9._:-]+$ ]] || die "Invalid release_id"
  [[ "$erp_archive" =~ ^poa-erp-[a-f0-9]{40}\.tar\.gz$ ]] || die "Unexpected ERP archive name"
  [[ "$website_archive" =~ ^poa-website-[a-f0-9]{40}\.tar\.gz$ ]] || die "Unexpected website archive name"
  [[ "$erp_sha" =~ ^[a-fA-F0-9]{64}$ ]] || die "Invalid ERP checksum in manifest"
  [[ "$website_sha" =~ ^[a-fA-F0-9]{64}$ ]] || die "Invalid website checksum in manifest"
}

current_release_id() {
  [[ -f "$STATE_FILE" ]] || return 1
  json_extract "$STATE_FILE" current.release_id 2>/dev/null || return 1
}

write_state() {
  local release_id="$1"
  local status="$2"
  local erp_commit="$3"
  local website_commit="$4"
  local previous_release="${5:-}"
  mkdir -p "$(dirname "$STATE_FILE")"
  "$PHP_BIN" -r '
    $state = [
      "current" => [
        "release_id" => $argv[1],
        "status" => $argv[2],
        "erp_commit" => $argv[3],
        "website_commit" => $argv[4],
        "deployed_at" => gmdate("c"),
      ],
      "previous" => [
        "release_id" => $argv[5],
      ],
    ];
    file_put_contents($argv[6], json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
  ' "$release_id" "$status" "$erp_commit" "$website_commit" "$previous_release" "$STATE_FILE"
  chmod 600 "$STATE_FILE"
}

prepare_laravel_release() {
  local app_name="$1"
  local archive="$2"
  local release_id="$3"
  local live_path="$4"
  local release_dir="$DEPLOY_ROOT/releases/$app_name/$release_id"
  local shared_dir="$DEPLOY_ROOT/shared/$app_name"

  [[ -d "$live_path" || -L "$live_path" ]] || die "$app_name live path does not exist: $live_path"
  rm -rf "$release_dir"
  mkdir -p "$release_dir" "$shared_dir/storage" "$shared_dir/bootstrap-cache"
  if [[ ! -f "$shared_dir/.env" ]]; then
    [[ -f "$live_path/.env" ]] || die "$app_name production .env missing at $live_path/.env"
    cp "$live_path/.env" "$shared_dir/.env"
    chmod 600 "$shared_dir/.env"
  fi
  if [[ -d "$live_path/storage" && -z "$(find "$shared_dir/storage" -mindepth 1 -maxdepth 1 2>/dev/null | head -n 1)" ]]; then
    "$RSYNC_BIN" -a "$live_path/storage/" "$shared_dir/storage/"
  fi
  "$TAR_BIN" -xzf "$archive" -C "$release_dir"
  [[ -f "$release_dir/public/build/manifest.json" ]] || die "$app_name Vite manifest missing after extraction"
  ln -sfn "$shared_dir/.env" "$release_dir/.env"
  rm -rf "$release_dir/storage" "$release_dir/bootstrap/cache"
  ln -sfn "$shared_dir/storage" "$release_dir/storage"
  mkdir -p "$release_dir/bootstrap"
  ln -sfn "$shared_dir/bootstrap-cache" "$release_dir/bootstrap/cache"
  (cd "$release_dir" && "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction >&2)
  printf '%s' "$release_dir"
}

activate_release() {
  local app_name="$1"
  local release_dir="$2"
  local live_path="$3"

  if [[ "${USE_SYMLINKS:-auto}" == "1" || ( "${USE_SYMLINKS:-auto}" == "auto" && -L "$live_path" ) ]]; then
    ln -sfn "$release_dir" "$live_path.next"
    mv -Tf "$live_path.next" "$live_path"
    return 0
  fi

  [[ "${RSYNC_BIN:-rsync}" != "" ]] || die "RSYNC_BIN is required for non-symlink deployment"
  require_command "$RSYNC_BIN"
  log "$app_name is using non-symlink rsync activation."
  "$RSYNC_BIN" -a --delete \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='bootstrap/cache/' \
    "$release_dir/" "$live_path/"
}

laravel_down() {
  local path="$1"
  (cd "$path" && "$PHP_BIN" artisan down --render=errors::503 --retry=60) || true
}

laravel_up() {
  local path="$1"
  (cd "$path" && "$PHP_BIN" artisan up) || true
}

run_laravel_release_commands() {
  local path="$1"
  local run_migrations="$2"
  (cd "$path" && "$PHP_BIN" artisan optimize:clear)
  if [[ "$run_migrations" == "1" ]]; then
    (cd "$path" && "$PHP_BIN" artisan migrate --force)
  fi
  (cd "$path" && "$PHP_BIN" artisan optimize)
}

http_ok() {
  local url="$1"
  local code
  code="$("$CURL_BIN" -fsS -o /dev/null -w '%{http_code}' --connect-timeout 10 --max-time 30 "$url" || true)"
  [[ "$code" =~ ^[23] ]] || die "Health check failed for $url with HTTP $code"
}

sync_website_public_root() {
  local release_dir="$1"
  [[ -d "$WEBSITE_PUBLIC_ROOT" ]] || die "Website public root does not exist: $WEBSITE_PUBLIC_ROOT"
  local delete_flag=()
  if [[ "${WEBSITE_PUBLIC_ROOT_MANAGED:-0}" == "1" ]]; then
    delete_flag=(--delete)
  fi
  "$RSYNC_BIN" -a "${delete_flag[@]}" \
    --exclude='.htaccess' \
    --exclude='.user.ini' \
    --exclude='cgi-bin/' \
    --exclude='index.php' \
    --exclude='php.ini' \
    --exclude='build/' \
    --exclude='storage/' \
    "$release_dir/public/" "$WEBSITE_PUBLIC_ROOT/"
}

prune_old_releases() {
  local app_name="$1"
  local keep="${RETAIN_RELEASES:-5}"
  local dir="$DEPLOY_ROOT/releases/$app_name"
  [[ -d "$dir" ]] || return 0
  find "$dir" -mindepth 1 -maxdepth 1 -type d | sort -r | awk -v keep="$keep" 'NR>keep {print}' | while IFS= read -r old; do
    rm -rf "$old"
  done
}

self_test() {
  local temp
  temp="$(mktemp -d)"
  trap 'rm -rf "$temp"' RETURN
  mkdir -p "$temp/archive/good/public/build"
  printf '{}\n' > "$temp/archive/good/public/build/manifest.json"
  "$TAR_BIN" -C "$temp/archive/good" -czf "$temp/good.tar.gz" .
  validate_archive_paths "$temp/good.tar.gz"
  validate_sha256 "$temp/good.tar.gz" "$("$SHA256_BIN" "$temp/good.tar.gz" | awk '{print $1}')"
  mkdir -p "$temp/archive/bad"
  printf 'bad\n' > "$temp/archive/bad/file"
  (cd "$temp/archive/bad" && "$TAR_BIN" -czf "$temp/bad.tar.gz" --transform='s#file#../file#' file)
  if ( validate_archive_paths "$temp/bad.tar.gz" ) 2>/dev/null; then
    die "Self-test failed: traversal archive was accepted"
  fi
  log "Self-test passed."
}

main() {
  load_config
  : "${GITHUB_API_URL:=https://api.github.com}"
  : "${RELEASE_TAG_PREFIX:=poa-production-}"
  : "${PHP_BIN:=php}"
  : "${COMPOSER_BIN:=composer}"
  : "${CURL_BIN:=curl}"
  : "${TAR_BIN:=tar}"
  : "${SHA256_BIN:=sha256sum}"
  : "${RSYNC_BIN:=rsync}"
  : "${RETAIN_RELEASES:=5}"
  : "${USE_SYMLINKS:=auto}"
  : "${WEBSITE_PUBLIC_ROOT_MANAGED:=0}"
  require_config GITHUB_TOKEN GITHUB_REPOSITORY DEPLOY_ROOT LOG_DIR STATE_FILE LOCK_DIR TMP_DIR ERP_PATH WEBSITE_PATH WEBSITE_PUBLIC_ROOT ERP_URL WEBSITE_URL
  require_command "$PHP_BIN"
  require_command "$COMPOSER_BIN"
  require_command "$CURL_BIN"
  require_command "$TAR_BIN"
  require_command "$SHA256_BIN"
  require_command "$RSYNC_BIN"

  mkdir -p "$DEPLOY_ROOT" "$LOG_DIR" "$TMP_DIR" "$LOCK_DIR"
  local log_file="$LOG_DIR/deploy-$(date -u +'%Y%m%dT%H%M%SZ').log"
  exec >> "$log_file" 2>&1

  local lock="$LOCK_DIR/poa-release.lock"
  if ! mkdir "$lock" 2>/dev/null; then
    log "Another deployment process is already running."
    exit 0
  fi

  local work
  work="$(mktemp -d "$TMP_DIR/release.XXXXXX")"
  local release_id=""
  local previous_release=""
  local erp_commit=""
  local website_commit=""
  local erp_maintenance=0
  local website_maintenance=0
  cleanup() {
    local status=$?
    if [[ "$status" -ne 0 ]]; then
      [[ "$erp_maintenance" == "1" ]] && laravel_up "$ERP_PATH"
      [[ "$website_maintenance" == "1" ]] && laravel_up "$WEBSITE_PATH"
      if [[ -n "$release_id" ]]; then
        write_state "$release_id" "failed" "$erp_commit" "$website_commit" "$previous_release" || true
      fi
    fi
    rm -rf "$work"
    rm -rf "$lock"
    exit "$status"
  }
  trap cleanup EXIT

  log "Checking for approved Program of Action release."
  latest_release_json > "$work/releases.json"
  select_release "$work/releases.json" > "$work/release.json" || die "No approved release found with prefix $RELEASE_TAG_PREFIX"
  local manifest_name
  manifest_name="$(json_extract "$work/release.json" assets | "$PHP_BIN" -r '
    $assets = json_decode(stream_get_contents(STDIN), true);
    foreach ($assets as $asset) {
      $name = (string) ($asset["name"] ?? "");
      if (preg_match("/^poa-production-.+-manifest\\.json$/", $name)) { echo $name; exit(0); }
    }
    exit(6);
  ')" || die "Release manifest asset not found"
  download_asset "$work/release.json" "$manifest_name" "$work/manifest.json"
  validate_manifest "$work/manifest.json"

  release_id="$(json_extract "$work/manifest.json" release_id)"
  local erp_archive website_archive erp_checksum website_checksum
  erp_archive="$(json_extract "$work/manifest.json" erp.archive)"
  website_archive="$(json_extract "$work/manifest.json" website.archive)"
  erp_checksum="$(json_extract "$work/manifest.json" erp.sha256)"
  website_checksum="$(json_extract "$work/manifest.json" website.sha256)"
  erp_commit="$(json_extract "$work/manifest.json" erp.commit_sha)"
  website_commit="$(json_extract "$work/manifest.json" website.commit_sha)"

  if [[ "$(current_release_id || true)" == "$release_id" ]]; then
    log "Release $release_id is already deployed; skipping."
    exit 0
  fi

  download_asset "$work/release.json" "$erp_archive" "$work/$erp_archive"
  download_asset "$work/release.json" "$website_archive" "$work/$website_archive"
  validate_sha256 "$work/$erp_archive" "$erp_checksum"
  validate_sha256 "$work/$website_archive" "$website_checksum"
  validate_archive_paths "$work/$erp_archive"
  validate_archive_paths "$work/$website_archive"

  previous_release="$(current_release_id || true)"
  log "Deploying release $release_id. Previous release: ${previous_release:-none}"

  local erp_release website_release
  erp_release="$(prepare_laravel_release erp "$work/$erp_archive" "$release_id" "$ERP_PATH")"
  website_release="$(prepare_laravel_release website "$work/$website_archive" "$release_id" "$WEBSITE_PATH")"

  local erp_migrated=0
  laravel_down "$ERP_PATH"
  erp_maintenance=1
  run_laravel_release_commands "$erp_release" 1
  erp_migrated=1
  activate_release erp "$erp_release" "$ERP_PATH"
  laravel_up "$ERP_PATH"
  erp_maintenance=0
  http_ok "$ERP_URL"
  http_ok "$ERP_URL/api/public/v1/offerings"

  laravel_down "$WEBSITE_PATH"
  website_maintenance=1
  run_laravel_release_commands "$website_release" 1
  activate_release website "$website_release" "$WEBSITE_PATH"
  sync_website_public_root "$website_release"
  laravel_up "$WEBSITE_PATH"
  website_maintenance=0
  http_ok "$WEBSITE_URL"
  http_ok "$WEBSITE_URL/get-assistance"
  http_ok "$WEBSITE_URL/get-assistance?offering=nsfas-applications-2027"

  write_state "$release_id" "deployed" "$erp_commit" "$website_commit" "$previous_release"
  prune_old_releases erp
  prune_old_releases website
  log "Release $release_id deployed successfully."
  [[ "$erp_migrated" == "1" ]] || true
}

if [[ "${1:-}" == "--self-test" ]]; then
  load_config
  : "${PHP_BIN:=php}"
  : "${TAR_BIN:=tar}"
  : "${SHA256_BIN:=sha256sum}"
  require_command "$PHP_BIN"
  require_command "$TAR_BIN"
  require_command "$SHA256_BIN"
  self_test
else
  main "$@"
fi
