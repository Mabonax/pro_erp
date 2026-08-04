# System Scenario Roadmap

Date: 2026-08-03

## Purpose

This report records a browser-led and source-backed exercise across the ERP domains to identify what users are likely to do next, where cross-domain workflows are blocked, and which implementation slices should expand end-to-end functionality.

The first implemented slice from this audit is the beneficiary service journey: the beneficiary file now exposes linked Citizen Access cases, evidence readiness, and milestone outcomes, Citizen Access case creation preserves the beneficiary's current programme/project/location context, operators can upload beneficiary evidence directly into the linked document-library workspace, readiness gaps can now become governed Task Management work, and project managers can see beneficiary journey risk rolled up by project location.

The second implemented slice strengthens the service-delivery architecture above those workflows: programmes remain strategic interventions, service pathways define reusable service blueprints, service offerings connect pathway versions to projects and locations, and Citizen Access support cases can now serve either natural-person beneficiaries or enterprise recipients through the same case engine.

## Method

- Authenticated locally as the seeded super-admin at `http://127.0.0.1:8001`.
- Iterated through the sidebar domains and key create/detail routes using the in-app browser.
- Cross-checked visible actions against controllers, resources, models, routes, and feature tests.
- Prioritized gaps where a user cannot complete a natural workflow without manually jumping between unrelated screens.

## Domain Relationship Model

The highest-value operating chain is:

`Program -> Project -> Project Location -> Beneficiary -> Project Enrollment -> Citizen Access Support Case -> Requirements/Evidence -> Readiness Actions -> Application/Referral/Outcome -> Milestone Assessment -> Reporting`

Supporting chains:

- `Beneficiary -> Next of Kin -> Emergency/contact continuity`
- `Project -> Facilitator -> Attendance -> Milestone assessment`
- `Support Case -> Evidence Item -> Document Library`
- `Work Task -> Readiness Action -> Staff/department execution`
- `Program Category -> Program -> Service Pathway -> Service Pathway Version -> Service Offering -> Support Case`
- `Enterprise -> Enterprise Person Role -> Citizen Access Support Case -> Evidence -> Outcome`
- `Marketing/Event/Finance/Assets -> Project or organization delivery support`

## Concept Mapping

| Target Concept | Existing Concept | Action | Migration Risk | Compatibility Notes |
| --- | --- | --- | --- | --- |
| Program Category | No direct model | Created `ProgramCategory` and optional `programs.program_category_id` | Low | Programs can remain uncategorized during transition |
| Program | `Program` | Retain and extend by optional category | Low | No existing program fields removed |
| Service Pathway | Partly implied by service stream/template | Created `ServicePathway` | Low | Links to existing `ServiceStream` instead of replacing it |
| Service Pathway Version | Partly `RequirementTemplateVersion` | Created `ServicePathwayVersion`, linked optionally to requirement-template version | Medium | Requirement templates remain the source of assessment snapshots |
| Pathway Stage | No direct model | Created ordered `PathwayStage` | Low | Stages are descriptive in this phase, not a workflow engine |
| Requirement Template/Rule | `RequirementTemplate`, `RequirementTemplateVersion`, `RequirementDefinition` | Retain and link to pathway versions | Low | Existing requirement snapshots still work |
| Outcome Definition | Programme outcomes and case activity outcomes | Created `OutcomeDefinition` for pathway-specific outcome vocabulary | Low | Does not replace programme outcome reporting |
| Project | `Project` and `ProjectLocation` | Retain | Low | Service offerings bind pathways to project/location context |
| Service Offering | `Opportunity` / public offering | Extend `Opportunity` with pathway/version, dates and capacity | Medium | New admin-published offerings require an active pathway version; legacy published offerings remain visible until backfilled |
| Support Case | `SupportCase` | Extend with enterprise recipient and pathway version | Medium | Beneficiary cases remain valid; old payloads default to person recipient |
| Beneficiary | `Beneficiary` | Retain for natural persons only | Low | Businesses are not stored as beneficiaries |
| Enterprise | No direct Citizen Access model | Created `Enterprise` | Medium | Enterprise evidence uses the same `EvidenceItem` and Document Library pattern |
| Enterprise Contact/Role | No direct model | Created `EnterprisePersonRole` | Medium | May link to a beneficiary/person record without duplicating identity data |

## Scenario Findings

| Domain | User Scenario | Current State | Roadblock | Priority |
| --- | --- | --- | --- | --- |
| Beneficiaries | Add a beneficiary to a programme/project and update next of kin | Create/edit supports placement and next-of-kin | Detail page did not show downstream cases, evidence, or milestones | Implemented |
| Beneficiaries + Citizen Access | Open a beneficiary and create an access-support case | Case creation route exists | Form did not visibly preserve the beneficiary's project/location context | Implemented |
| Citizen Access | Convert public intake into beneficiary, enrollment, case, and requirement snapshot | Backend workflow exists and tests pass | Progress visibility now exists from beneficiary and project surfaces; deeper guided interventions remain possible | Implemented |
| Projects | Track all enrolled beneficiaries through locations, attendance, milestones, and closure | Deep project workflow exists | Beneficiary-level outcome view was disconnected from project milestone assessments | Implemented |
| Document Library | Store beneficiary support evidence and publish approved outputs | Owner-based workspace exists | Beneficiary evidence upload is now available from the journey panel; verification workflows can still be deepened | Implemented |
| Task Management | Create tasks from readiness gaps and track execution | Readiness actions can reference work tasks | Support-case readiness actions can now create linked governed WorkTasks | Implemented |
| Service Delivery | View placements, attendance, outcomes, and partner delivery | Dedicated surfaces exist | Project page now shows cross-domain beneficiary journey risk by delivery site; guided intervention flows can still deepen | Implemented |
| Finance | Manage travel claims for delivery operations | Travel claim workflow exists | No clear link from project activity/event delivery to travel claims | Medium |
| Assets | Assign equipment to delivery work | Asset lifecycle exists | No beneficiary/project-location context from the service journey | Medium |
| Events | Run events and track participants | Event workflow exists | Participant-to-beneficiary conversion/linking should be clearer | Medium |
| Marketing | Request and approve campaign work | Marketing request/job workflow exists | Campaigns are not linked strongly enough to programme outcomes or public offerings | Medium |
| HR | Manage staff, leave, attendance | HR dashboard and staff registry exist | Delivery roles and case/task workload need stronger capacity planning | Medium |
| Access Control | Grant domain permissions | Role/permission UI exists | Role templates by operational scenario would reduce setup errors | Low |

## Implemented Slice

### Beneficiary Service Journey

Added relationships and resource fields so a beneficiary file can show:

- Current and historical project participation.
- Linked Citizen Access support cases with stage, stream, readiness state, and readiness percentage.
- Linked evidence items with verification and expiry state.
- Linked project milestone assessments with status and score.
- Summary counters for open cases, evidence, and completed milestones.

### Context-Preserving Case Creation

When a user opens case creation from a beneficiary file, the form now:

- Preselects the beneficiary.
- Carries the beneficiary's current programme, project, and project-location context.
- Falls back server-side to the beneficiary's current enrollment if those fields are not submitted.

### Beneficiary Evidence Upload

The beneficiary Service Journey panel now supports direct evidence upload into the document library. The upload:

- Creates or reuses the beneficiary-owned document-library folder.
- Stores the uploaded file through the document-library file service.
- Creates a linked Citizen Access evidence item with evidence type, issuer, dates, verification status, sensitivity classification, and document download metadata.
- Keeps legacy evidence records without document files visible in the same journey list.

### Readiness Task Creation

Citizen Access support-case readiness actions now bridge into Task Management. The case workspace can:

- Show whether a readiness action already has a linked task.
- Create a WorkTask from an open readiness action without leaving the case page.
- Preserve Task Management governance by requiring normal task-create authorization.
- Carry case context into the task description, including support case reference, beneficiary, service stream, programme, project, priority, due date, and assignment.
- Prevent duplicate tasks by redirecting to the existing linked task when a readiness action already has one.

### Project-Level Beneficiary Journey Rollup

The project workspace now summarizes beneficiary journey risk across delivery locations. The rollup:

- Counts open Citizen Access support cases, evidence gaps, open readiness actions, and locations with journey risk.
- Groups beneficiary risks by project location.
- Highlights at-risk beneficiaries with links back to the beneficiary file.
- Shows missing evidence types, linked case counts, open readiness actions, completed milestone assessments, and attendance rate per beneficiary.
- Uses existing project enrollment, location, attendance, milestone, evidence, and support-case relationships without introducing a parallel reporting table.

### Service Pathway Architecture

Citizen Access now has explicit service-delivery building blocks:

- `ProgramCategory` captures broad intervention areas such as Citizen Access or Business Support.
- `ServicePathway` captures reusable operational blueprints such as NSFAS Application Support or Business Compliance Readiness.
- `ServicePathwayVersion` freezes a pathway's structure for a rules year or delivery period.
- `PathwayStage` defines ordered operational stages such as intake, eligibility screening, evidence collection, submission support, follow-up, and outcome capture.
- `OutcomeDefinition` separates service outputs, immediate outcomes, and longer-term impacts.
- `Opportunity` now acts as the transitional Service Offering by linking program, project, project location, requirement template, service pathway, pathway version, dates, capacity, and public intake copy.

Pathway versions used by support cases are immutable. Updating or deleting an in-use version, stage, or outcome definition throws an application-level exception. Operational changes must be introduced through a new pathway version so historical cases retain the exact rules they started with.

### Enterprise Recipients

Businesses are now modeled separately from beneficiaries:

- `Enterprise` stores business identity and contact metadata such as legal name, trading name, registration number, sector, registration status, trading status, province, municipality, email, telephone, and notes.
- `EnterprisePersonRole` links people to enterprises with governed roles such as owner, director, primary contact, authorised representative, employee, or mentor/advisor.
- Citizen Access support cases can now serve either one beneficiary or one enterprise.
- `EvidenceItem` can link to either a beneficiary or an enterprise while still using the Document Library for files.

### Recipient Database Decision

The selected design uses explicit nullable foreign keys: `beneficiary_id` and `enterprise_id`, with `recipient_type` as the service-recipient discriminator.

This was chosen over a polymorphic association because the ERP relies heavily on relational integrity, queryable relationships, and Laravel eager loading across reports. Explicit foreign keys preserve database-level references to beneficiaries and enterprises, make permissions and reporting easier to reason about, and avoid the referential-integrity weakness of polymorphic IDs. Validation enforces that a new support case has exactly one recipient. The first migration keeps the old beneficiary column and only makes it nullable to support enterprise cases.

### Representative Pathways

The Citizen Access seeder now creates representative pathway fixtures:

- `NSFAS Application Support`, version `NSFAS 2027`, recipient type `person`.
- `Business Compliance Readiness`, version `Compliance Readiness 2027`, recipient type `enterprise`.

The NSFAS pathway includes stages for intake, eligibility screening, evidence collection, application preparation, submission support, follow-up, outcome capture, and appeal/escalation. Its requirements include identity verification, household income assessment, academic record, institution proof, and verified contact details.

The business compliance pathway includes stages for business intake, compliance diagnosis, missing requirement identification, evidence collection, referral or assisted registration, submission tracking, compliance verification, and outcome capture. Its requirements include CIPC status, SARS tax number, Tax Compliance Status, B-BBEE evidence, business bank account, municipal permit, and industry-specific licence where applicable.

## Remaining Backlog

1. Add event participant conversion/linking to beneficiary records.
2. Add project/activity context to finance travel claims.
3. Add project-location asset assignment views for equipment used in delivery.
4. Add role templates for common users: intake officer, programme manager, facilitator, finance officer, marketing officer, and executive viewer.
5. Add dedicated index/detail/edit pages for Service Pathways and Enterprises once the compact admin forms prove the data model.
6. Add a safe backfill command to attach existing published opportunities to representative pathway versions.
7. Add enterprise evidence upload UI, reusing the Document Library ownership conventions already proven for beneficiary evidence.
8. Add explicit application/outcome records tied to `OutcomeDefinition` rather than free-text case activity categories.

## Verification

- `php -l app\Domains\Beneficiaries\Models\Beneficiary.php`
- `php -l app\Domains\Beneficiaries\Controllers\BeneficiaryController.php`
- `php -l app\Domains\Beneficiaries\Resources\BeneficiaryResource.php`
- `php -l app\Domains\CitizenAccess\Controllers\SupportCaseController.php`
- `php -l app\Domains\CitizenAccess\Models\EvidenceItem.php`
- `php -l app\Domains\CitizenAccess\Requests\StoreBeneficiaryEvidenceRequest.php`
- `php -l app\Domains\Documents\Services\DocumentFolderService.php`
- `php artisan test tests\Feature\BeneficiaryPageWorkflowTest.php`
- `php artisan test tests\Feature\CitizenAccessWorkflowTest.php`
- `php artisan test tests\Feature\DocumentLibraryTest.php`
- `php artisan test tests\Feature\TaskManagementDomainTest.php --filter="department manager can assign task to a direct report"`
- `php artisan test tests\Feature\Projects\ProjectProgressServiceTest.php`
- `php artisan test tests\Feature\CitizenAccessServicePathwayArchitectureTest.php`
- `php artisan route:list --path=citizen-access`
- `npm.cmd run build`
