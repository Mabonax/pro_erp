# Citizen Access Offerings

ERP is the authoritative Citizen Access catalogue.

After the ERP and public website integration code is deployed, administrators do not need a Git commit, pull request, or website deployment to publish ordinary Citizen Access content. Git/deployment is only required when software, UI, API, or schema behavior changes.

## Publication Model

An Offering is a managed Citizen Access `Opportunity`. It appears on the public website only when `Opportunity::publishedPublic()` passes.

The publication path is:

```text
Create
-> configure
-> satisfy readiness
-> Publish Offering
-> GET /api/public/v1/offerings
-> website grouped catalogue
```

The readiness service checks:

- offering is active
- public slug is present
- public title is present
- publication action sets the published state
- lifecycle status is Published
- offering is not archived
- active service stream is assigned
- program is assigned
- project belongs to the selected program
- project location belongs to the selected project
- requirement template has a published version
- selected pathway version is active, when used
- opening and closing dates are valid

The Publish Offering action is separate from Save. The UI can disable publishing when readiness fails, and the server still rejects bypassed publish requests.

## Public Grouping

The public website does not maintain a second hard-coded list of offerings.

Public support areas come from ERP `ServiceStream` records. The API exposes a safe `support_area` object containing only public grouping fields:

- `slug`
- `label`
- `summary`
- `display_order`

The website groups published offerings dynamically from this API data.

## Cache Behavior

The website fetches offerings server-side with `POA_ERP_PUBLIC_INTAKE_TOKEN`; the token is never exposed to browser JavaScript.

The fresh catalogue cache uses:

```bash
POA_ERP_OFFERINGS_CACHE_SECONDS=300
```

The default expected delay after publishing, unpublishing, deactivating, or archiving is up to 5 minutes unless the website cache is cleared sooner. The website also stores a last successful catalogue and can serve it temporarily if ERP is unavailable after the fresh cache expires.

Status meanings:

- `ok`: ERP loaded successfully, including the legitimate case of zero published offerings.
- `cached`: ERP lookup failed, but the website is serving the last successful catalogue.
- `unavailable`: ERP lookup failed and no successful catalogue is available.
- `not_configured`: website ERP URL/token is missing.

## Catalogue Command Safety

Run:

```bash
php artisan citizen-access:seed-catalogue
```

The command is idempotent. It creates missing canonical programs, projects, locations, requirement templates, service streams, institutions, offerings, and supported external opportunity cycles.

For existing canonical offerings, the command preserves administrator-owned production content, including:

- publication status
- active/inactive state
- archive state
- public title and descriptions
- contact references
- owner and facilitator assignments
- geography
- project, location, and requirement-template configuration

The command may update service stream public grouping defaults because those are catalogue-level defaults.

## Production Verification Commands

ERP:

```bash
php artisan migrate --force
php artisan citizen-access:seed-catalogue
php artisan route:list --path=citizen-access
php artisan route:list --path=api/public/v1/offerings
php artisan test tests/Feature/CitizenAccessWorkflowTest.php
php artisan test tests/Feature/CitizenAccessOfferingManagementTest.php
php artisan test tests/Feature/CitizenAccessServicePathwayArchitectureTest.php
npm.cmd run build
```

Website:

```bash
php artisan test tests/Feature/CitizenAssistanceIntegrationTest.php
npm.cmd run build
```

Required environment variables:

ERP:

```bash
CITIZEN_ACCESS_PUBLIC_INTAKE_TOKEN=
```

Website:

```bash
POA_ERP_BASE_URL=
POA_ERP_PUBLIC_INTAKE_TOKEN=
POA_ERP_OFFERINGS_CACHE_SECONDS=300
```

Do not commit secrets.
