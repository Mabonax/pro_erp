# Production release process

> Superseded: GitHub-hosted runners cannot reliably SSH into Afrihost/cPanel from outside South Africa. The current production release design is pull-based and is documented in `docs/deployment/pull-based-production-release.md`.

Program of Action production releases are coordinated from the ERP repository through `.github/workflows/deploy.yml`. The workflow is manual only and deploys to the Afrihost shared server over SSH. Docker and server-side npm are deliberately not used.

## Architecture

The ERP repository is the release orchestrator. A single `workflow_dispatch` run checks out the requested ERP ref and the requested public website ref, builds and tests both applications in GitHub Actions, deploys the ERP first, verifies ERP health, then deploys and verifies the public website.

The public website depends on ERP-published Citizen Access offerings, so the website is never deployed until the ERP deployment and health checks pass.

## Current validation baseline

The release workflow runs Laravel tests and production Vite builds before deployment. Full frontend lint and TypeScript gates are not enabled in this production workflow yet because the current repository baselines contain unrelated preexisting failures. Re-enable full `eslint`, Prettier, and website `tsc --noEmit` gates after those baselines are cleaned without broad auto-fix churn.

## Required GitHub configuration

Create a `production` environment in GitHub and add required reviewers before allowing deployment.

Required secrets:

- `POA_DEPLOY_HOST`
- `POA_DEPLOY_PORT`
- `POA_DEPLOY_USER`
- `POA_DEPLOY_SSH_KEY`
- `POA_DEPLOY_KNOWN_HOSTS`
- `POA_ERP_PUBLIC_INTAKE_TOKEN`
- `POA_WEBSITE_REPOSITORY_TOKEN` if the workflow token cannot read `Mabonax/poa_blog-`

Required environment variables or secrets:

- `POA_ERP_PATH`
- `POA_WEBSITE_PATH`

Expected production paths:

- ERP: `/home/prograg9g3o8/apps/erp.programofaction.org`
- Website: `/home/prograg9g3o8/apps/website`

Do not store `.env` values in the repository. Production `.env` files stay on the server.

## Deployment key

Create a dedicated SSH key for GitHub Actions, not a personal workstation key.

1. Generate an Ed25519 key pair locally or in a secure admin environment.
2. Install the public key in the Afrihost account for the deployment user.
3. Restrict the deployment user to the two application directories where practical.
4. Store the private key as `POA_DEPLOY_SSH_KEY`.
5. Rotate the key by adding a new public key on the server, updating the GitHub secret, running a dry release, then removing the old public key.

## known_hosts

Populate `POA_DEPLOY_KNOWN_HOSTS` with a verified host key entry for the Afrihost SSH host and port. Use `ssh-keyscan` only from a trusted network and compare the fingerprint with Afrihost control-panel or support-provided data before storing it.

The workflow uses `StrictHostKeyChecking=yes` and does not use `StrictHostKeyChecking=no`.

## Server requirements

The deployment user needs:

- SSH access to the Afrihost server.
- Read and write access to the ERP and website application directories.
- Ability to run `git`, `php`, `composer`, `unzip`, and Laravel `artisan`.
- Writable `storage` and `bootstrap/cache` for each Laravel app.
- Existing production `.env` files in both application directories.

Node.js and npm are not required on the server.

## Triggering a release

Run the ERP repository `Production release` workflow manually.

Inputs:

- `erp_ref`: branch, tag, or commit. Default: `program-of-action-erp`.
- `website_ref`: branch, tag, or commit. Default: `main`.
- `allow_merge_commit`: keep `false` unless the merge commit has been reviewed for production.

The workflow records the deployed ERP and website commit SHAs in the GitHub Actions summary and writes `storage/app/current-deploy-sha.txt` on each server application.

## Preserved data

The workflow does not overwrite, print, download, or commit production `.env` files. It does not delete `storage`, uploaded files, user data, sessions, logs, or database content.

Only `public/build` receives prebuilt Vite assets from GitHub Actions. `public/build/manifest.json` is checked after extraction.

## Health checks

ERP checks:

- `https://erp.programofaction.org/api/public/v1/offerings` with the configured bearer token.
- `/citizen-access/intakes`
- `/citizen-access/cases`
- `/citizen-access/admin`
- `/access-control/roles`

Authenticated pages may return a login redirect. HTTP 404 and 5xx responses fail the deployment.

Website checks:

- `https://programofaction.org`
- `https://programofaction.org/get-assistance`
- `https://programofaction.org/get-assistance?offering=nsfas-applications-2027`

The workflow does not submit public or ERP forms.

## Rollback

Each deployment records the previous commit SHA in `storage/app/previous-deploy-sha.txt`. To roll back code after review:

```bash
cd /home/prograg9g3o8/apps/erp.programofaction.org
php artisan down --render=errors::503 --retry=60
git checkout --detach "$(cat storage/app/previous-deploy-sha.txt)"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan optimize
php artisan up
```

Repeat the same pattern in `/home/prograg9g3o8/apps/website` for the public website.

Do not automatically roll back successful ERP database migrations unless a specific reversible migration strategy has been verified. If the website deployment fails after ERP succeeds, leave ERP live and fix or roll back only the website.

## Testing without production submissions

Use GET-only health checks for `/get-assistance` and offering preselection. Do not POST to `/assistance/requests` or `/api/public/v1/intakes` in production release verification unless explicit approval is given for a controlled test record.
