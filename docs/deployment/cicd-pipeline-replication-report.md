# CI/CD and deployment pipeline replication report

This report documents the production deployment pattern implemented for the Program of Action ERP and public website. It is written so the same pattern can be reused in other Laravel/Inertia projects that deploy to shared hosting over SSH.

## 1. Executive summary

The pipeline uses GitHub Actions as a controlled release orchestrator. It builds and tests the application code in GitHub Actions, packages compiled Vite assets, transfers those assets to the server, and performs a guarded SSH deployment into an existing Laravel checkout.

For Program of Action, one workflow in the ERP repository coordinates two repositories:

- ERP: `Mabonax/pro_erp`, branch `program-of-action-erp`
- Public website: `Mabonax/poa_blog-`, branch `main`

The ERP is deployed first because the website depends on the ERP Citizen Access API. The website deploys only after ERP deployment and health checks pass.

## 2. Why this architecture was selected

The production environment is Afrihost shared hosting. The server has PHP 8.3 and Composer, but Node.js/npm must not be assumed. Docker is also not part of the production hosting model.

The chosen architecture therefore avoids:

- Docker image builds or GHCR runtime deployment.
- Running npm on the production server.
- Copying `.env` files from GitHub.
- Parallel deployment of dependent applications.
- Broad destructive Git commands on the server.

The workflow instead uses:

- `workflow_dispatch` for manual release starts.
- A GitHub `production` environment for deployment protection.
- GitHub Actions build runners for Composer, npm, tests, and Vite builds.
- SSH with verified `known_hosts`.
- Server-side `git checkout --detach <sha>` after confirming the target commit exists.
- Laravel maintenance mode with cleanup recovery.
- Health checks before moving to the dependent application.
- GitHub job summaries for commit SHA and deployment outcome records.

## 3. Source documentation basis

The workflow relies on current GitHub Actions platform behavior:

- `workflow_dispatch` supports manual workflow inputs.
- Jobs can reference an `environment`, allowing deployment protection rules and environment-scoped secrets.
- Environment secrets are only available to jobs that reference that environment, and approval-gated environments do not expose those secrets until approval.
- `concurrency` prevents overlapping deployments.
- `permissions` should be scoped least-privilege per workflow/job.
- `$GITHUB_STEP_SUMMARY` can publish Markdown deployment summaries.

These platform details were checked against GitHub Actions documentation through Context7 before writing this report.

## 4. Pipeline lifecycle

The deployment lifecycle is:

1. A maintainer manually starts the GitHub Actions workflow.
2. The workflow accepts explicit refs or SHAs for each application.
3. GitHub checks out the ERP ref.
4. GitHub checks out the website ref.
5. Each application installs Composer dependencies.
6. Each application installs npm dependencies with `npm ci`.
7. Each application prepares a CI-safe Laravel `.env`.
8. Backend tests run.
9. Vite production builds run.
10. `public/build/manifest.json` is verified.
11. Built `public/build` assets are zipped and uploaded as artifacts.
12. The deploy job waits for the GitHub `production` environment approval if configured.
13. SSH is configured using a private deploy key and verified `known_hosts`.
14. Build artifacts are copied to `/tmp` on the server.
15. A server deployment lock is acquired.
16. ERP is deployed.
17. ERP health checks run.
18. Website is deployed only if ERP checks pass.
19. Website health checks run.
20. The deployment lock and temporary artifact files are removed.
21. The GitHub summary records deployed commit SHAs and release outcome.

## 5. Workflow design

The workflow is defined in:

```text
.github/workflows/deploy.yml
```

Core workflow controls:

```yaml
on:
  workflow_dispatch:
    inputs:
      erp_ref:
      website_ref:
      allow_merge_commit:

permissions:
  contents: read

concurrency:
  group: poa-production-release
  cancel-in-progress: false
```

These controls are important:

- `workflow_dispatch` prevents accidental deployment from every push.
- Explicit refs make releases reproducible.
- `allow_merge_commit` forces deliberate handling of merge commits.
- `contents: read` avoids broad GitHub token permissions.
- `concurrency` prevents two production deployments from overlapping.

## 6. Build jobs

Each application has its own build job. The jobs are intentionally independent because both applications must be buildable before any production deployment starts.

The build job pattern is:

```yaml
- uses: actions/checkout
- uses: shivammathur/setup-php
- uses: actions/setup-node
- run: composer install --no-interaction --prefer-dist --optimize-autoloader
- run: npm ci
- run: php artisan test
- run: npm run build
- run: test -f public/build/manifest.json
- uses: actions/upload-artifact
```

In another Laravel/Inertia project, keep this pattern and adapt only:

- PHP version.
- Node version.
- Required PHP extensions.
- Test command.
- Build command.
- Artifact name.

## 7. Why assets are built in GitHub Actions

Shared hosting should not be treated as a frontend build environment. Vite builds can require a specific Node/npm version and native dependencies. Building in GitHub Actions makes the deployment repeatable and avoids server drift.

The only frontend output copied to production is:

```text
public/build
```

The workflow explicitly checks:

```text
public/build/manifest.json
```

This prevents a deployment that would leave Laravel pointing at missing compiled assets.

## 8. Production deployment job

The deploy job references:

```yaml
environment:
  name: production
```

This is where GitHub environment protection and environment secrets apply.

Required environment variables or secrets:

```text
POA_ERP_PATH
POA_WEBSITE_PATH
```

Required secrets:

```text
POA_DEPLOY_HOST
POA_DEPLOY_PORT
POA_DEPLOY_USER
POA_DEPLOY_SSH_KEY
POA_DEPLOY_KNOWN_HOSTS
POA_ERP_PUBLIC_INTAKE_TOKEN
```

Optional secret:

```text
POA_WEBSITE_REPOSITORY_TOKEN
```

Use the optional token only when the orchestrator repository cannot check out the second repository with the default workflow token.

## 9. SSH security model

The workflow uses a dedicated deploy key. It does not use a human personal SSH key.

The private key is stored only in GitHub Actions secrets:

```text
POA_DEPLOY_SSH_KEY
```

The public key is installed on the server for the deployment SSH user.

The workflow requires a verified server host key:

```text
POA_DEPLOY_KNOWN_HOSTS
```

The workflow uses:

```text
StrictHostKeyChecking=yes
```

It deliberately does not use:

```text
StrictHostKeyChecking=no
```

This protects the deployment from silently trusting a different SSH server.

## 10. Server deployment algorithm

The server-side deployment uses the existing application directory as the release target.

The deployment performs:

1. `cd` to the configured deployment path.
2. Resolve and validate the current directory.
3. Record the previous commit SHA.
4. Refuse deployment if tracked files are dirty.
5. Fetch branches and tags from origin.
6. Verify the target SHA exists locally.
7. Checkout the target SHA detached.
8. Enable Laravel maintenance mode.
9. Run production Composer install.
10. Run migrations where required.
11. Clear and rebuild Laravel caches.
12. Extract prebuilt Vite assets into `public/build`.
13. Verify `public/build/manifest.json`.
14. Repair writable permissions only for `storage` and `bootstrap/cache`.
15. Disable Laravel maintenance mode.
16. Record the new deployed SHA.

The workflow avoids `git reset --hard`. It also avoids deleting `storage`, user uploads, `.env`, or persistent data.

## 11. Maintenance mode recovery

Every server deployment block uses a cleanup trap. The cleanup always attempts:

```bash
php artisan up
```

For ERP, if the deployment fails before migrations complete, the script attempts to return the code checkout to the previous commit. It does not automatically roll back successful database migrations.

This is deliberate. Database rollback is only safe when the migrations are known reversible and the production data impact is understood.

## 12. Deployment lock

The deploy job creates a lock directory on the server:

```text
$HOME/.poa-production-release.lock
```

This protects against a second release starting while the first is still running. GitHub Actions concurrency protects at the workflow level; the server lock adds a second layer in case another deployment path is introduced later.

## 13. Health checks

ERP health checks:

```text
https://erp.programofaction.org/api/public/v1/offerings
https://erp.programofaction.org/citizen-access/intakes
https://erp.programofaction.org/citizen-access/cases
https://erp.programofaction.org/citizen-access/admin
https://erp.programofaction.org/access-control/roles
```

The public offerings endpoint must return successful JSON with a `data` array. Protected pages may redirect to login when checked anonymously. HTTP 404 and 5xx responses fail the release.

Website health checks:

```text
https://programofaction.org
https://programofaction.org/get-assistance
https://programofaction.org/get-assistance?offering=nsfas-applications-2027
```

The workflow uses GET-only checks and does not submit production forms.

## 14. Secrets and variables

Secrets are values that must never appear in logs, code, PRs, or chat:

- SSH private keys.
- API tokens.
- Database credentials.
- Laravel app keys.
- Shared integration tokens.

Variables are non-sensitive configuration values:

- Deployment paths.
- Public URLs.
- Non-secret feature flags.

When in doubt, store as a secret.

## 15. Local automation helper

This repo includes:

```text
scripts/setup-github-production-environment.ps1
```

The script assists with:

- GitHub CLI authentication check.
- Deploy key generation.
- Public key display for Afrihost/cPanel.
- `known_hosts` collection.
- GitHub environment creation.
- GitHub environment variable creation.
- GitHub environment secret creation.
- Citizen Access token generation.
- Server `.env` value output.

The script intentionally pauses for human confirmation before trusting SSH host keys. That step cannot be safely automated without a trusted source for the server fingerprint.

## 16. Required server `.env` alignment

The GitHub secret and server `.env` files must agree.

ERP production `.env`:

```text
CITIZEN_ACCESS_PUBLIC_INTAKE_TOKEN=<same generated token>
```

Website production `.env`:

```text
POA_ERP_BASE_URL=https://erp.programofaction.org
POA_ERP_PUBLIC_INTAKE_TOKEN=<same generated token>
```

GitHub environment secret:

```text
POA_ERP_PUBLIC_INTAKE_TOKEN=<same generated token>
```

This lets the deployment workflow verify the ERP offerings endpoint without exposing the token in logs.

## 17. Rollback model

Each app records:

```text
storage/app/previous-deploy-sha.txt
storage/app/current-deploy-sha.txt
```

Manual code rollback pattern:

```bash
cd /path/to/app
php artisan down --render=errors::503 --retry=60
git checkout --detach "$(cat storage/app/previous-deploy-sha.txt)"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan optimize
php artisan up
```

Migration limitation:

Do not automatically roll back successful production migrations unless the specific migration is verified reversible and data-safe.

## 18. Replication checklist for another Laravel/Inertia project

To reuse this model:

1. Confirm production hosting model.
2. Confirm PHP and Composer availability.
3. Confirm Node/npm should run only in CI, not production.
4. Identify the production branch.
5. Identify the production server path.
6. Identify the public health-check URLs.
7. Create a manual `workflow_dispatch` deployment workflow.
8. Add explicit ref inputs.
9. Add `concurrency`.
10. Add least-privilege `permissions`.
11. Add build/test job.
12. Add Vite asset packaging.
13. Add a deployment job with `environment: production`.
14. Use a dedicated SSH deploy key.
15. Use verified `known_hosts`.
16. Add server deployment lock.
17. Preserve `.env`, `storage`, uploads, and database data.
18. Use Laravel maintenance mode and cleanup recovery.
19. Run migrations only when appropriate.
20. Add health checks before reporting success.
21. Add job summary output with deployed SHA.
22. Document rollback and manual deployment limitations.

## 19. Template workflow shape

Use this as the starting shape:

```yaml
name: Production release

on:
  workflow_dispatch:
    inputs:
      app_ref:
        description: Branch, tag, or commit to deploy
        required: true
        default: main
        type: string

permissions:
  contents: read

concurrency:
  group: my-app-production-release
  cancel-in-progress: false

jobs:
  build:
    runs-on: ubuntu-latest
    timeout-minutes: 30
    steps:
      - uses: actions/checkout@v5
      - uses: shivammathur/setup-php@v2
      - uses: actions/setup-node@v4
      - run: composer install --no-interaction --prefer-dist --optimize-autoloader
      - run: npm ci
      - run: php artisan test
      - run: npm run build
      - run: test -f public/build/manifest.json
      - uses: actions/upload-artifact@v4

  deploy:
    needs: build
    runs-on: ubuntu-latest
    environment:
      name: production
    timeout-minutes: 30
    steps:
      - uses: actions/download-artifact@v5
      - name: Configure SSH
        run: |
          install -m 700 -d ~/.ssh
          printf '%s\n' "$SSH_PRIVATE_KEY" > ~/.ssh/id_ed25519
          chmod 600 ~/.ssh/id_ed25519
          printf '%s\n' "$SSH_KNOWN_HOSTS" > ~/.ssh/known_hosts
          chmod 600 ~/.ssh/known_hosts
```

Adapt the server deployment block to each project.

## 20. Current Program of Action status

Current draft PRs:

- ERP: `https://github.com/Mabonax/pro_erp/pull/1`
- Website: `https://github.com/Mabonax/poa_blog-/pull/1`

Current blocker:

- Afrihost SSH access is not yet returning a host key on the provided endpoint. SSH must be enabled or the correct SSH host/port must be confirmed before GitHub deployment secrets can be completed.

No production deployment has been triggered.
