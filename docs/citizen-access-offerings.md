# Citizen Access Offerings

An Offering is a managed Citizen Access `Opportunity` that can be shown on the public website only when it passes the server-side publication rules in `Opportunity::publishedPublic()`.

Programme vs Project vs Offering:
- Programme is the structured service pathway, such as Citizen Access Program or Entrepreneurship Program.
- Project is an actual delivery run, such as `Citizen Access 2026/27 - Gauteng`.
- Offering is the public/admin service configuration that connects a stream, programme, project, project location, requirement template, and safe public copy.

Publication works through `OpportunityPublicationReadinessService`. A public offering must be active, lifecycle `published`, not archived, published, have a public slug/title, be linked to a programme, project, matching project location, active service stream, and requirement template with a published version. If a pathway version is selected, it must be active.

Administrators manage offerings at:

```bash
/citizen-access/admin/offerings
```

Manual workflow:
- Create an offering as `draft` or `ready`.
- Use controlled selections for programme, project, location, requirement template, owner, facilitator, stream, institution, and pathway.
- Review Public Offering Readiness on the detail page.
- Publish only when the server says the offering is ready.
- Unpublish to remove it from the public API without losing the record.
- Archive for normal removal.
- Restore archived offerings as drafts.
- Hard deletion is only allowed when there are no historical support cases, intake needs, or application cycles.

Production catalogue seeding:

```bash
php artisan migrate --force
php artisan citizen-access:seed-catalogue
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

The catalogue command is idempotent. It creates or updates canonical programmes, projects, project locations, requirement templates, service streams, institutions, offerings, and supported external opportunity cycles. It does not delete beneficiary, intake, case, application, outcome, or other operational records.

Required environment variables:

ERP:

```bash
CITIZEN_ACCESS_PUBLIC_INTAKE_TOKEN=
```

Website:

```bash
POA_ERP_BASE_URL=
POA_ERP_PUBLIC_INTAKE_TOKEN=
```

Do not commit actual secrets.
