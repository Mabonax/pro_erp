# Pull-based production release

This is the production deployment model for Program of Action on Afrihost/cPanel shared hosting.

GitHub-hosted runners do not push to the server. Afrihost initiates deployment from cron by pulling an approved private GitHub Release over HTTPS.

## Architecture

1. A maintainer manually starts `.github/workflows/deploy.yml`.
2. GitHub checks out the requested ERP ref from `Mabonax/pro_erp`.
3. GitHub checks out the requested website ref from `Mabonax/poa_blog-`.
4. GitHub installs Composer and npm dependencies for validation.
5. GitHub builds Vite assets for both applications.
6. GitHub runs backend tests.
7. GitHub packages production archives that exclude `.env`, `.git`, `node_modules`, `vendor`, runtime storage, logs and tests.
8. The `publish-release` job waits for the protected GitHub `production` environment approval.
9. After approval, GitHub publishes a private release containing:
   - ERP archive
   - ERP SHA-256 file
   - website archive
   - website SHA-256 file
   - JSON release manifest
   - combined checksums file
10. A cPanel cron job runs `/home/prograg9g3o8/deploy/poa-release/deploy.sh`.
11. The script downloads the latest `poa-production-*` release through GitHub's API using an Authorization header.
12. The script validates the manifest, verifies SHA-256 checksums, rejects unsafe archive paths, deploys ERP first, health-checks ERP, then deploys and health-checks the website.

## GitHub configuration

Keep the `production` environment and required reviewers. The protected environment now gates release publication instead of SSH deployment.

Required repository or environment secret:

- `POA_WEBSITE_REPOSITORY_TOKEN`: only needed if the default workflow token cannot read `Mabonax/poa_blog-`.

No Afrihost SSH secrets are required for the pull model.

The workflow requires `contents: write` only in the `publish-release` job so it can create a GitHub Release. Build jobs keep `contents: read`.

## Server token

Create a fine-grained GitHub token for the Afrihost server.

Minimum permission:

- Repository: `Mabonax/pro_erp`
- Contents: read-only

The server does not need access to `Mabonax/poa_blog-` because the ERP release contains the approved website archive.

Store the token only in:

```text
/home/prograg9g3o8/deploy/poa-release/config.env
```

Permissions:

```bash
chmod 600 /home/prograg9g3o8/deploy/poa-release/config.env
```

Do not put the token in the cron command. Do not put it in `.env`. Do not pass it as a query string.

For cPanel public roots, the deploy script preserves common server-managed files and symlinks in `public_html`: `.htaccess`, `.user.ini`, `index.php`, `php.ini`, `cgi-bin`, `build`, and `storage`.

## Server setup

Create private deployment directories:

```bash
mkdir -p /home/prograg9g3o8/deploy/poa-release/{logs,state,locks,tmp,releases,shared}
chmod 700 /home/prograg9g3o8/deploy/poa-release
```

Copy:

```text
scripts/deployment/pull-release-deploy.sh
```

to:

```text
/home/prograg9g3o8/deploy/poa-release/deploy.sh
```

Then:

```bash
chmod 700 /home/prograg9g3o8/deploy/poa-release/deploy.sh
```

Copy `scripts/deployment/pull-release-config.example.env` to:

```text
/home/prograg9g3o8/deploy/poa-release/config.env
```

Edit paths and command locations. Discover command paths on Afrihost with:

```bash
command -v php
command -v composer
command -v curl
command -v tar
command -v sha256sum
command -v rsync
```

## Cron

Recommended cPanel cron command:

```bash
/usr/bin/bash /home/prograg9g3o8/deploy/poa-release/deploy.sh >> /home/prograg9g3o8/deploy/poa-release/logs/cron.log 2>&1
```

Recommended interval:

```text
*/5 * * * *
```

Already-deployed release IDs are skipped safely.

## First manual dry run

Do not start with cron. First run manually from cPanel Terminal:

```bash
/usr/bin/bash /home/prograg9g3o8/deploy/poa-release/deploy.sh
```

Inspect:

```bash
tail -n 200 /home/prograg9g3o8/deploy/poa-release/logs/cron.log
ls -la /home/prograg9g3o8/deploy/poa-release/logs
cat /home/prograg9g3o8/deploy/poa-release/state/deployment-state.json
```

## Health checks

ERP checks:

- `https://erp.programofaction.org`
- `https://erp.programofaction.org/api/public/v1/offerings`

Website checks:

- `https://programofaction.org`
- `https://programofaction.org/get-assistance`
- `https://programofaction.org/get-assistance?offering=nsfas-applications-2027`

The website `/get-assistance` check is GET-only and exercises the server-side ERP offerings integration without creating citizen intake data.

## Rollback model

The script records the previous release ID in `deployment-state.json`.

Code rollback is safest before database migrations. After migrations have run, rollback may require a migration-specific plan. The script does not run destructive migration rollbacks automatically.

Keep database backups before production releases. Use backward-compatible migrations whenever possible.

## Token rotation

1. Create a new fine-grained token with read-only contents access to `Mabonax/pro_erp`.
2. Update `/home/prograg9g3o8/deploy/poa-release/config.env`.
3. Run the deploy script manually.
4. Revoke the old token.

## Adding another Laravel/Inertia app

Reuse the pattern:

- Build assets in GitHub Actions.
- Publish immutable release assets after environment approval.
- Store only a narrow read-only token on the server.
- Pull over HTTPS from cron.
- Verify checksums.
- Deploy dependencies in dependency order.
- Preserve `.env`, storage and uploads.
*** Add File: docs/deployment/cpanel-pull-release-runbook.md
# cPanel pull release runbook

Use this runbook when operating Program of Action production deployment from Afrihost/cPanel.

## Publish a release

1. Open GitHub Actions in `Mabonax/pro_erp`.
2. Run `Production release`.
3. Use:
   - `erp_ref`: `program-of-action-erp`
   - `website_ref`: `main`
   - `allow_merge_commit`: `true` only for reviewed merge commits
4. Wait for both build jobs to pass.
5. Approve the `production` environment job.
6. Confirm GitHub created a `poa-production-*` release.

This does not deploy by itself. Afrihost pulls the release.

## Manual server pull

In cPanel Terminal:

```bash
/usr/bin/bash /home/prograg9g3o8/deploy/poa-release/deploy.sh
```

## Cron command

```bash
/usr/bin/bash /home/prograg9g3o8/deploy/poa-release/deploy.sh >> /home/prograg9g3o8/deploy/poa-release/logs/cron.log 2>&1
```

Schedule:

```text
*/5 * * * *
```

## Logs

```bash
tail -n 200 /home/prograg9g3o8/deploy/poa-release/logs/cron.log
ls -1 /home/prograg9g3o8/deploy/poa-release/logs
tail -n 200 /home/prograg9g3o8/deploy/poa-release/logs/deploy-*.log
```

## State

```bash
cat /home/prograg9g3o8/deploy/poa-release/state/deployment-state.json
```

## Common failures

- `No approved release found`: approve and publish a `poa-production-*` GitHub Release.
- `Checksum mismatch`: do not deploy; publish a new release.
- `Archive contains a parent traversal path`: do not deploy; investigate release packaging.
- `production .env missing`: restore the server `.env` for that app.
- `Health check failed`: inspect Laravel logs and keep the failed release out of service.

## Emergency stop

Disable the cPanel cron job.

If a deployment is running, wait for it to finish or remove the lock only after verifying no deploy process remains:

```bash
rmdir /home/prograg9g3o8/deploy/poa-release/locks/poa-release.lock
```
