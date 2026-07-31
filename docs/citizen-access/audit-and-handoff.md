# Citizen Access, Needs Assessment and Readiness Case Management

## Audit Summary

- ERP: Laravel 12, PHP `^8.2`, Inertia 2, React 19, TypeScript, Tailwind 4, Spatie permissions, Pest, Laravel notifications, database queues and private `local`/`document_library` disks.
- ERP structure: domain folders under `app/Domains/{Domain}/{Controllers,Models,Requests,Resources,Services,Policies,Repositories}` with route-level Spatie permission middleware and Inertia pages under `resources/js/pages`.
- Reused ERP domains: `Beneficiaries`, `Members`, `Projects`, `Programs`, `Documents`, `TaskManagement`, `ServiceDelivery`, `Reporting`, `Staff`, notifications and access control.
- Website: Laravel 12, Inertia 2, React 19, TypeScript, Puck CMS layouts, existing `/forms/{formKey}/submissions` CMS form storage, and a trusted Laravel backend. It can securely proxy to ERP without exposing secrets in JavaScript.

## Domain Map

`CitizenAccess` owns intake/enquiry records, selected needs, configurable service streams/institutions/opportunities/cycles, versioned requirement templates, case-specific assessment snapshots, evidence links, readiness actions, application/referral/outcome records and domain audit events.

Beneficiaries remain the canonical active citizen support record. Public submissions create intakes only. Officers explicitly convert or link an intake after consent, screening and duplicate review.

## Security And Privacy

- Public API: `POST /api/public/v1/intakes`.
- Authentication: bearer token from `CITIZEN_ACCESS_PUBLIC_INTAKE_TOKEN`.
- Website proxy: `/assistance/requests` forwards server-side using `POA_ERP_PUBLIC_INTAKE_TOKEN`.
- Idempotency: website sends `Idempotency-Key`; ERP stores it and returns the existing intake on retry.
- Public response returns only public reference, timestamp, safe status and next step.
- Sensitive evidence is not accepted by the anonymous website form. Evidence is linked later through ERP document/evidence workflows using private storage.
- Audit events record workflow changes without full identity numbers or sensitive form bodies.

## Readiness Rules

Requirement template versions are copied into `citizen_access_assessment_items.requirement_snapshot` when applied to a support case. Later template edits do not rewrite existing assessment snapshots.

Readiness is rule-based. A case cannot become `ready_for_application_support` while a mandatory blocking item is missing, rejected, expired or under verification. Eligibility wording remains an internal indication only.

## Environment

ERP:

```env
CITIZEN_ACCESS_PUBLIC_INTAKE_TOKEN=replace-with-random-scoped-token
```

Website:

```env
POA_ERP_BASE_URL=https://erp.example.org
POA_ERP_PUBLIC_INTAKE_TOKEN=replace-with-same-scoped-token
POA_ERP_TIMEOUT=8
```

Never expose those values through `VITE_*`, HTML, source maps or committed files.

## Deployment Order

1. Back up ERP database and uploaded evidence.
2. Deploy ERP code.
3. Run migrations and seed configurable sample records only where appropriate.
4. Configure the scoped ERP intake token.
5. Verify `POST /api/public/v1/intakes`.
6. Deploy website code and configure ERP URL/token.
7. Submit one controlled production request.
8. Confirm one ERP intake exists and retry does not duplicate it.
9. Monitor logs, queues and notifications.

## Known Limitations

- The public website now contains a complete `/get-assistance` Citizen Assistance page with service cards, journey explanation, scope boundaries, privacy/consent guidance, accessible intake form, card-to-form preselection, loading/error handling, and a confirmation state based only on a successful ERP response.
- The public anonymous form intentionally does not collect identity-document numbers, date of birth, institutional passwords, OTPs or evidence uploads. Those should be collected later only through reviewed authenticated staff workflows and private document storage.
- The ERP intake API now validates required public location fields and accuracy confirmation, stores ward/delivery-channel fields, and preserves non-sensitive public form context in intake metadata.
- The authenticated ERP support-case workspace can record applications, referrals, follow-ups and outcomes into the existing `citizen_access_case_applications` table, updates the case stage for major activity types, and audits the activity capture.
- The Citizen Access migration was reviewed with `migrate --pretend`, fixed for MySQL's 64-character foreign-key identifier limit, and run locally successfully.
- The first slice still does not include full reporting dashboards, exports, SMS/email acknowledgement, comprehensive evidence-upload UX, granular Citizen Access permissions, or full authenticated browser verification across every ERP workspace.
- Seeded requirements are development/sample configuration, not current institutional policy.
- Website integration requires configured ERP URL/token and reachable ERP runtime.

## Verification Update - 2026-07-31

- ERP: `php artisan migrate --pretend --path=database/migrations/2026_07_30_200000_create_citizen_access_tables.php` reviewed additive table creation.
- ERP: `php artisan migrate --path=database/migrations/2026_07_30_200000_create_citizen_access_tables.php` completed locally; `migrate:status` reports the migration as `Ran`.
- ERP: `php artisan test tests\Feature\CitizenAccessWorkflowTest.php` passed, 5 tests and 22 assertions.
- ERP: `npm.cmd run types` passed.
- ERP: `npm.cmd run build` passed.
- Website: `php artisan test tests\Feature\CitizenAssistanceIntegrationTest.php` passed, 4 tests and 21 assertions.
- Website: `npm.cmd run build` passed with the existing Vite large-chunk warning.
- Browser: `/get-assistance` rendered on a local server with file logging/session writes disabled because the checkout cannot write to `storage/logs` and `storage/framework/sessions` under the current Windows user. DOM checks at desktop, tablet and mobile widths found the hero, service cards, journey, form and footer, with no horizontal overflow. Service-card selection preselected the matching form option without submitting.
