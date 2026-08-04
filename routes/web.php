<?php

/*
| Copyright (c) 2026 John Mabona. All rights reserved.
| Proprietary and confidential. System Architecture by John Mabona.
*/

use App\Domains\Assets\Controllers\AssetCategoryController;
use App\Domains\Assets\Controllers\AssetController;
use App\Domains\Beneficiaries\Controllers\BeneficiaryController;
use App\Domains\BusinessDevelopment\Controllers\BdsApplicationController;
use App\Domains\BusinessDevelopment\Controllers\BdsDashboardController;
use App\Domains\BusinessDevelopment\Controllers\BdsIncubateeController;
use App\Domains\BusinessDevelopment\Controllers\BdsIncubateeKpiController;
use App\Domains\BusinessDevelopment\Controllers\BdsPitchSessionController;
use App\Domains\CitizenAccess\Controllers\CitizenAccessAdminController;
use App\Domains\CitizenAccess\Controllers\IntakeController as CitizenAccessIntakeController;
use App\Domains\CitizenAccess\Controllers\SupportCaseController as CitizenAccessSupportCaseController;
use App\Domains\Committees\Controllers\CommitteeController;
use App\Domains\Compliance\Controllers\ComplianceRegistryController;
use App\Domains\Documents\Controllers\DocumentLibraryController;
use App\Domains\Events\Controllers\EventController;
use App\Domains\Facilitators\Controllers\FacilitatorController;
use App\Domains\Finance\Controllers\TravelClaimController;
use App\Domains\Geography\Controllers\GeographicRegistryController;
use App\Domains\Governance\Controllers\GovernanceDashboardController;
use App\Domains\HumanCapital\Controllers\HumanCapitalDashboardController;
use App\Domains\HumanResources\Controllers\HumanResourcesController;
use App\Domains\Intelligence\Controllers\AgentController;
use App\Domains\Intelligence\Controllers\AiToolController;
use App\Domains\Intelligence\Controllers\ConversationController;
use App\Domains\Intelligence\Controllers\IntelligenceWorkspaceController;
use App\Domains\Intelligence\Controllers\MemoryController;
use App\Domains\Intelligence\Controllers\ModelRoutingController;
use App\Domains\Intelligence\Controllers\PromptTemplateController;
use App\Domains\Intelligence\Controllers\ToolExecutionLogController;
use App\Domains\Leave\Controllers\LeaveRequestController;
use App\Domains\Marketing\Controllers\MarketingController;
use App\Domains\Marketing\Controllers\MarketingOperationsController;
use App\Domains\Meetings\Controllers\MeetingController;
use App\Domains\Members\Controllers\MemberController;
use App\Domains\Organisation\Controllers\OrganisationRegistryController;
use App\Domains\Organization\Controllers\OrganizationDocumentController;
use App\Domains\Organization\Controllers\OrganizationProfileController;
use App\Domains\Programs\Controllers\ProgramController;
use App\Domains\Projects\Controllers\MilestoneTemplateController;
use App\Domains\Projects\Controllers\ProjectAttendanceController;
use App\Domains\Projects\Controllers\ProjectController;
use App\Domains\Projects\Controllers\ProjectEnrollmentController;
use App\Domains\Projects\Controllers\ProjectLocationController;
use App\Domains\Projects\Controllers\ProjectMilestoneAssessmentController;
use App\Domains\Resolutions\Controllers\ResolutionController;
use App\Domains\ServiceDelivery\Controllers\PartnershipController;
use App\Domains\ServiceDelivery\Controllers\PlacementController;
use App\Domains\ServiceDelivery\Controllers\ProgrammeDocumentController;
use App\Domains\ServiceDelivery\Controllers\ProgrammeOutcomeController;
use App\Domains\ServiceDelivery\Controllers\ProjectActivityController;
use App\Domains\ServiceDelivery\Controllers\ServiceAttendanceController;
use App\Domains\ServiceDelivery\Controllers\ServiceDeliveryDashboardController;
use App\Domains\Staff\Controllers\StaffController;
use App\Domains\Staff\Controllers\StaffDepartmentController;
use App\Domains\StaffAttendance\Controllers\StaffAttendanceController;
use App\Domains\Stakeholders\Controllers\StakeholderController;
use App\Domains\TaskManagement\Controllers\SupportTicketController;
use App\Domains\TaskManagement\Controllers\WorkTaskController;
use App\Http\Controllers\AccessControl\AccessControlController;
use App\Http\Controllers\BusinessDevelopment\AdjudicationAssessmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    $viewPermission = static fn (string $domain): string => "permission:domain.{$domain}.view|domain.{$domain}.manage";
    $managePermission = static fn (string $domain): string => "permission:domain.{$domain}.manage";
    $adjudicationPermission = 'permission:domain.business-development.view|domain.business-development.manage|business-development.adjudications.score';
    $adjudicationManagePermission = 'permission:domain.business-development.manage|business-development.adjudications.score';

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])
        ->name('notifications.mark-all-read');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])
        ->name('notifications.open');

    Route::post('beneficiaries/import', [BeneficiaryController::class, 'import'])
        ->middleware($managePermission('beneficiaries'))
        ->name('beneficiaries.import');
    Route::post('beneficiaries/{beneficiary}/evidence', [BeneficiaryController::class, 'storeEvidence'])
        ->middleware($managePermission('beneficiaries'))
        ->whereNumber('beneficiary')
        ->name('beneficiaries.evidence.store');
    Route::resource('beneficiaries', BeneficiaryController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('beneficiaries'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('beneficiaries'));

    Route::prefix('citizen-access')
        ->name('citizen-access.')
        ->middleware($viewPermission('citizen-access'))
        ->group(function () use ($managePermission) {
            Route::get('intakes', [CitizenAccessIntakeController::class, 'index'])->name('intakes.index');
            Route::get('intakes/{intake}', [CitizenAccessIntakeController::class, 'show'])->whereNumber('intake')->name('intakes.show');
            Route::post('intakes/{intake}/assign', [CitizenAccessIntakeController::class, 'assign'])->middleware($managePermission('citizen-access'))->whereNumber('intake')->name('intakes.assign');
            Route::post('intakes/{intake}/status', [CitizenAccessIntakeController::class, 'status'])->middleware($managePermission('citizen-access'))->whereNumber('intake')->name('intakes.status');
            Route::post('intakes/{intake}/convert', [CitizenAccessIntakeController::class, 'convert'])->middleware($managePermission('citizen-access'))->whereNumber('intake')->name('intakes.convert');
            Route::post('intakes/{intake}/link', [CitizenAccessIntakeController::class, 'link'])->middleware($managePermission('citizen-access'))->whereNumber('intake')->name('intakes.link');

            Route::get('cases', [CitizenAccessSupportCaseController::class, 'index'])->name('cases.index');
            Route::get('cases/create', [CitizenAccessSupportCaseController::class, 'create'])->middleware($managePermission('citizen-access'))->name('cases.create');
            Route::post('cases', [CitizenAccessSupportCaseController::class, 'store'])->middleware($managePermission('citizen-access'))->name('cases.store');
            Route::get('cases/{case}', [CitizenAccessSupportCaseController::class, 'show'])->whereNumber('case')->name('cases.show');
            Route::post('cases/{case}/template', [CitizenAccessSupportCaseController::class, 'applyTemplate'])->middleware($managePermission('citizen-access'))->whereNumber('case')->name('cases.template');
            Route::post('cases/{case}/readiness', [CitizenAccessSupportCaseController::class, 'recalculate'])->middleware($managePermission('citizen-access'))->whereNumber('case')->name('cases.readiness');
            Route::post('cases/{case}/activities', [CitizenAccessSupportCaseController::class, 'storeActivity'])->middleware($managePermission('citizen-access'))->whereNumber('case')->name('cases.activities.store');
            Route::post('cases/{case}/readiness-actions/{action}/task', [CitizenAccessSupportCaseController::class, 'createReadinessTask'])->middleware($managePermission('citizen-access'))->whereNumber(['case', 'action'])->name('cases.readiness-actions.task');
            Route::post('assessment-items/{item}/status', [CitizenAccessSupportCaseController::class, 'assessmentStatus'])->middleware($managePermission('citizen-access'))->whereNumber('item')->name('assessment-items.status');

            Route::get('admin', [CitizenAccessAdminController::class, 'index'])->middleware($managePermission('citizen-access'))->name('admin.index');
            Route::post('admin/program-categories', [CitizenAccessAdminController::class, 'storeProgramCategory'])->middleware($managePermission('citizen-access'))->name('admin.program-categories.store');
            Route::post('admin/service-streams', [CitizenAccessAdminController::class, 'storeStream'])->middleware($managePermission('citizen-access'))->name('admin.service-streams.store');
            Route::post('admin/institutions', [CitizenAccessAdminController::class, 'storeInstitution'])->middleware($managePermission('citizen-access'))->name('admin.institutions.store');
            Route::post('admin/service-pathways', [CitizenAccessAdminController::class, 'storePathway'])->middleware($managePermission('citizen-access'))->name('admin.service-pathways.store');
            Route::post('admin/service-pathways/{pathway}/versions', [CitizenAccessAdminController::class, 'storePathwayVersion'])->middleware($managePermission('citizen-access'))->whereNumber('pathway')->name('admin.service-pathways.versions.store');
            Route::post('admin/enterprises', [CitizenAccessAdminController::class, 'storeEnterprise'])->middleware($managePermission('citizen-access'))->name('admin.enterprises.store');
            Route::post('admin/enterprises/{enterprise}/people', [CitizenAccessAdminController::class, 'storeEnterprisePerson'])->middleware($managePermission('citizen-access'))->whereNumber('enterprise')->name('admin.enterprises.people.store');
            Route::post('admin/opportunities', [CitizenAccessAdminController::class, 'storeOpportunity'])->middleware($managePermission('citizen-access'))->name('admin.opportunities.store');
            Route::put('admin/opportunities/{opportunity}', [CitizenAccessAdminController::class, 'updateOpportunity'])->middleware($managePermission('citizen-access'))->whereNumber('opportunity')->name('admin.opportunities.update');
            Route::post('admin/templates', [CitizenAccessAdminController::class, 'storeTemplate'])->middleware($managePermission('citizen-access'))->name('admin.templates.store');
        });

    Route::get('business-development', BdsDashboardController::class)
        ->middleware('permission:domain.business-development.view|domain.business-development.manage')
        ->name('business-development.dashboard');
    Route::get('task-management', \App\Domains\TaskManagement\Controllers\TaskManagementDashboardController::class)
        ->middleware('permission:domain.task-management.view|domain.task-management.manage')
        ->name('task-management.dashboard');
    Route::get('finance/travel-claims', [TravelClaimController::class, 'index'])
        ->middleware('permission:domain.finance.view|domain.finance.manage|travel-claims.submit')
        ->name('finance.travel-claims.index');
    Route::get('finance/travel-claims/create', [TravelClaimController::class, 'create'])
        ->middleware('permission:travel-claims.submit')
        ->name('finance.travel-claims.create');
    Route::post('finance/travel-claims', [TravelClaimController::class, 'store'])
        ->middleware('permission:travel-claims.submit')
        ->name('finance.travel-claims.store');
    Route::get('finance/travel-claims/{travelClaim}', [TravelClaimController::class, 'show'])
        ->middleware('permission:domain.finance.view|domain.finance.manage|travel-claims.submit')
        ->whereNumber('travelClaim')
        ->name('finance.travel-claims.show');
    Route::get('finance/travel-claims/{travelClaim}/pdf', [TravelClaimController::class, 'pdf'])
        ->middleware('permission:domain.finance.view|domain.finance.manage|travel-claims.submit')
        ->whereNumber('travelClaim')
        ->name('finance.travel-claims.pdf');
    Route::post('finance/travel-claims/{travelClaim}/approve', [TravelClaimController::class, 'approve'])
        ->whereNumber('travelClaim')
        ->name('finance.travel-claims.approve');
    Route::post('finance/travel-claims/{travelClaim}/approval-reject', [TravelClaimController::class, 'rejectApproval'])
        ->whereNumber('travelClaim')
        ->name('finance.travel-claims.approval-reject');
    Route::post('finance/travel-claims/{travelClaim}/receive', [TravelClaimController::class, 'receive'])
        ->middleware('permission:domain.finance.view|domain.finance.manage')
        ->whereNumber('travelClaim')
        ->name('finance.travel-claims.receive');
    Route::post('finance/travel-claims/{travelClaim}/pay', [TravelClaimController::class, 'pay'])
        ->middleware('permission:domain.finance.view|domain.finance.manage')
        ->whereNumber('travelClaim')
        ->name('finance.travel-claims.pay');
    Route::post('finance/travel-claims/{travelClaim}/reject', [TravelClaimController::class, 'reject'])
        ->middleware('permission:domain.finance.view|domain.finance.manage')
        ->whereNumber('travelClaim')
        ->name('finance.travel-claims.reject');
    Route::get('marketing', [MarketingOperationsController::class, 'dashboard'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->name('marketing.dashboard');
    Route::get('marketing/requests', [MarketingOperationsController::class, 'requestsIndex'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->name('marketing.requests.index');
    Route::get('marketing/requests/create', [MarketingOperationsController::class, 'createRequest'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->name('marketing.requests.create');
    Route::post('marketing/requests', [MarketingOperationsController::class, 'storeRequest'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->name('marketing.requests.store');
    Route::get('marketing/requests/{marketingRequest}', [MarketingOperationsController::class, 'showRequest'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('marketingRequest')
        ->name('marketing.requests.show');
    Route::put('marketing/requests/{marketingRequest}', [MarketingOperationsController::class, 'updateRequest'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('marketingRequest')
        ->name('marketing.requests.update');
    Route::post('marketing/requests/{marketingRequest}/comment', [MarketingOperationsController::class, 'comment'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('marketingRequest')
        ->name('marketing.requests.comment');
    Route::post('marketing/requests/{marketingRequest}/documents', [MarketingOperationsController::class, 'uploadDocument'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('marketingRequest')
        ->name('marketing.requests.documents.store');
    Route::get('marketing/requests/{marketingRequest}/documents/{document}', [MarketingOperationsController::class, 'downloadDocument'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('marketingRequest')
        ->whereNumber('document')
        ->name('marketing.requests.documents.download');
    Route::get('marketing/deliverables/workspace', [MarketingOperationsController::class, 'workspace'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->name('marketing.deliverables.workspace');
    Route::get('marketing/approvals', [MarketingOperationsController::class, 'approvals'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->name('marketing.approvals.index');
    Route::get('marketing/assets', [MarketingOperationsController::class, 'assets'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->name('marketing.assets.index');
    Route::get('marketing/publications', [MarketingOperationsController::class, 'publications'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->name('marketing.publications.index');
    Route::post('marketing/publications/import-metrics', [MarketingOperationsController::class, 'importMetrics'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->name('marketing.publications.import-metrics');
    Route::post('marketing/deliverables/{deliverable}/versions', [MarketingOperationsController::class, 'storeVersion'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('deliverable')
        ->name('marketing.deliverables.versions.store');
    Route::post('marketing/deliverables/{deliverable}/approve', [MarketingOperationsController::class, 'approveDeliverable'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('deliverable')
        ->name('marketing.deliverables.approve');
    Route::post('marketing/deliverables/{deliverable}/request-changes', [MarketingOperationsController::class, 'requestDeliverableChanges'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('deliverable')
        ->name('marketing.deliverables.request-changes');
    Route::post('marketing/assets/{asset}/publish', [MarketingOperationsController::class, 'publishAsset'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('asset')
        ->name('marketing.assets.publish');
    Route::post('marketing/assets/{asset}/archive', [MarketingOperationsController::class, 'archiveAsset'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('asset')
        ->name('marketing.assets.archive');
    Route::post('marketing/assets/{asset}/publish-to-vault', [MarketingOperationsController::class, 'publishAssetToVault'])
        ->middleware('auth')
        ->whereNumber('asset')
        ->name('marketing.assets.publish-to-vault');
    Route::get('marketing/jobs', [MarketingController::class, 'index'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->name('marketing.jobs.index');
    Route::get('marketing/jobs/create', [MarketingController::class, 'create'])
        ->middleware('permission:domain.marketing.manage')
        ->name('marketing.jobs.create');
    Route::get('marketing/jobs/{job}', [MarketingController::class, 'show'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('job')
        ->name('marketing.jobs.show');
    Route::post('marketing/jobs', [MarketingController::class, 'store'])
        ->middleware('permission:domain.marketing.manage')
        ->name('marketing.jobs.store');
    Route::post('marketing/jobs/{job}/status', [MarketingController::class, 'updateStatus'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('job')
        ->name('marketing.jobs.status');
    Route::post('marketing/jobs/{job}/submit-approval', [MarketingController::class, 'submitForApproval'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('job')
        ->name('marketing.jobs.submit-approval');
    Route::post('marketing/jobs/{job}/approve', [MarketingController::class, 'approve'])
        ->middleware('permission:domain.marketing.manage')
        ->whereNumber('job')
        ->name('marketing.jobs.approve');
    Route::post('marketing/jobs/{job}/request-amendments', [MarketingController::class, 'requestAmendments'])
        ->middleware('permission:domain.marketing.manage')
        ->whereNumber('job')
        ->name('marketing.jobs.request-amendments');
    Route::post('marketing/jobs/{job}/comment', [MarketingController::class, 'comment'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('job')
        ->name('marketing.jobs.comment');
    Route::post('marketing/jobs/{job}/documents', [MarketingController::class, 'uploadDocument'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('job')
        ->name('marketing.jobs.documents.store');
    Route::post('marketing/jobs/{job}/reassign', [MarketingController::class, 'reassign'])
        ->middleware('permission:domain.marketing.manage')
        ->whereNumber('job')
        ->name('marketing.jobs.reassign');
    Route::get('marketing/jobs/{job}/proof', [MarketingController::class, 'downloadProof'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('job')
        ->name('marketing.jobs.proof');
    Route::get('marketing/jobs/{job}/documents/{document}', [MarketingController::class, 'downloadDocument'])
        ->middleware('permission:domain.marketing.view|domain.marketing.manage')
        ->whereNumber('job')
        ->whereNumber('document')
        ->name('marketing.jobs.documents.download');
    Route::post('marketing/jobs/{job}/publish-to-vault', [MarketingController::class, 'publishToVault'])
        ->middleware('auth')
        ->whereNumber('job')
        ->name('marketing.jobs.publish-to-vault');
    Route::get('task-management/tasks', [WorkTaskController::class, 'index'])
        ->middleware('permission:domain.task-management.view|domain.task-management.manage')
        ->name('task-management.tasks.index');
    Route::get('task-management/tasks/{task}', [WorkTaskController::class, 'show'])
        ->middleware('permission:domain.task-management.view|domain.task-management.manage')
        ->whereNumber('task')
        ->name('task-management.tasks.show');
    Route::post('task-management/tasks', [WorkTaskController::class, 'store'])
        ->middleware('permission:domain.task-management.manage')
        ->name('task-management.tasks.store');
    Route::post('task-management/tasks/{task}/status', [WorkTaskController::class, 'updateStatus'])
        ->middleware('permission:domain.task-management.view|domain.task-management.manage')
        ->whereNumber('task')
        ->name('task-management.tasks.status');
    Route::post('task-management/tasks/{task}/submit-review', [WorkTaskController::class, 'submitForReview'])
        ->middleware('permission:domain.task-management.view|domain.task-management.manage')
        ->whereNumber('task')
        ->name('task-management.tasks.submit-review');
    Route::post('task-management/tasks/{task}/approve', [WorkTaskController::class, 'approveCompletion'])
        ->middleware('permission:domain.task-management.manage')
        ->whereNumber('task')
        ->name('task-management.tasks.approve');
    Route::post('task-management/tasks/{task}/return', [WorkTaskController::class, 'returnForAmendments'])
        ->middleware('permission:domain.task-management.manage')
        ->whereNumber('task')
        ->name('task-management.tasks.return');
    Route::post('task-management/tasks/{task}/comment', [WorkTaskController::class, 'comment'])
        ->middleware('permission:domain.task-management.view|domain.task-management.manage')
        ->whereNumber('task')
        ->name('task-management.tasks.comment');
    Route::post('task-management/tasks/{task}/documents', [WorkTaskController::class, 'uploadDocument'])
        ->middleware('permission:domain.task-management.view|domain.task-management.manage')
        ->whereNumber('task')
        ->name('task-management.tasks.documents.store');
    Route::post('task-management/tasks/{task}/reassign', [WorkTaskController::class, 'reassign'])
        ->middleware('permission:domain.task-management.manage')
        ->whereNumber('task')
        ->name('task-management.tasks.reassign');
    Route::get('task-management/tasks/{task}/proof', [WorkTaskController::class, 'downloadProof'])
        ->middleware('permission:domain.task-management.view|domain.task-management.manage')
        ->whereNumber('task')
        ->name('task-management.tasks.proof');
    Route::get('task-management/tasks/{task}/documents/{document}', [WorkTaskController::class, 'downloadDocument'])
        ->middleware('permission:domain.task-management.view|domain.task-management.manage')
        ->whereNumber('task')
        ->whereNumber('document')
        ->name('task-management.tasks.documents.download');
    Route::get('task-management/tickets', [SupportTicketController::class, 'index'])
        ->name('task-management.tickets.index');
    Route::post('task-management/tickets', [SupportTicketController::class, 'store'])
        ->name('task-management.tickets.store');
    Route::post('task-management/tickets/{ticket}/assign', [SupportTicketController::class, 'assign'])
        ->whereNumber('ticket')
        ->name('task-management.tickets.assign');
    Route::post('task-management/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])
        ->whereNumber('ticket')
        ->name('task-management.tickets.reply');
    Route::post('task-management/tickets/{ticket}/resolve', [SupportTicketController::class, 'resolve'])
        ->whereNumber('ticket')
        ->name('task-management.tickets.resolve');
    Route::post('task-management/tickets/{ticket}/close', [SupportTicketController::class, 'close'])
        ->whereNumber('ticket')
        ->name('task-management.tickets.close');
    Route::post('task-management/tickets/{ticket}/reopen', [SupportTicketController::class, 'reopen'])
        ->whereNumber('ticket')
        ->name('task-management.tickets.reopen');
    Route::get('business-development/applications', [BdsApplicationController::class, 'index'])
        ->middleware('permission:domain.business-development.view|domain.business-development.manage')
        ->name('business-development.applications.index');
    Route::get('business-development/applications/{bds_application}', [BdsApplicationController::class, 'show'])
        ->middleware('permission:domain.business-development.view|domain.business-development.manage')
        ->whereNumber('bds_application')
        ->name('business-development.applications.show');
    Route::post('business-development/applications/import', [BdsApplicationController::class, 'import'])
        ->middleware('permission:domain.business-development.manage')
        ->name('business-development.applications.import');
    Route::post('business-development/applications/{bds_application}/assess', [BdsApplicationController::class, 'assess'])
        ->middleware('permission:domain.business-development.manage')
        ->whereNumber('bds_application')
        ->name('business-development.applications.assess');
    Route::post('business-development/applications/{bds_application}/schedule-pitch', [BdsApplicationController::class, 'schedulePitch'])
        ->middleware('permission:domain.business-development.manage')
        ->whereNumber('bds_application')
        ->name('business-development.applications.schedule-pitch');
    Route::get('business-development/pitch-sessions', [BdsPitchSessionController::class, 'index'])
        ->middleware('permission:domain.business-development.view|domain.business-development.manage')
        ->name('business-development.pitch-sessions.index');
    Route::get('business-development/pitch-sessions/{pitch_session}', [BdsPitchSessionController::class, 'show'])
        ->middleware('permission:domain.business-development.view|domain.business-development.manage')
        ->whereNumber('pitch_session')
        ->name('business-development.pitch-sessions.show');
    Route::post('business-development/pitch-sessions', [BdsPitchSessionController::class, 'store'])
        ->middleware('permission:domain.business-development.manage')
        ->name('business-development.pitch-sessions.store');
    Route::post('business-development/pitch-sessions/{pitch_session}/start', [BdsPitchSessionController::class, 'start'])
        ->middleware('permission:domain.business-development.manage')
        ->whereNumber('pitch_session')
        ->name('business-development.pitch-sessions.start');
    Route::post('business-development/pitch-sessions/{pitch_session}/prospects/{prospect}/consolidate', [BdsPitchSessionController::class, 'consolidate'])
        ->middleware('permission:domain.business-development.manage')
        ->whereNumber('pitch_session')
        ->whereNumber('prospect')
        ->name('business-development.pitch-sessions.prospects.consolidate');
    Route::post('business-development/pitch-sessions/{pitch_session}/prospects/{prospect}/approve', [BdsPitchSessionController::class, 'approve'])
        ->middleware('permission:domain.business-development.manage')
        ->whereNumber('pitch_session')
        ->whereNumber('prospect')
        ->name('business-development.pitch-sessions.prospects.approve');
    Route::resource('business-development/incubatees', BdsIncubateeController::class)
        ->parameters(['incubatees' => 'incubatee'])
        ->middlewareFor(['index', 'show'], $viewPermission('business-development'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('business-development'));
    Route::post('business-development/incubatees/{incubatee}/kpis', [BdsIncubateeKpiController::class, 'assign'])
        ->middleware('permission:domain.business-development.manage')
        ->whereNumber('incubatee')
        ->name('business-development.incubatees.kpis.assign');
    Route::post('business-development/incubatee-kpis/{kpi}/reviews', [BdsIncubateeKpiController::class, 'review'])
        ->middleware('permission:domain.business-development.manage')
        ->whereNumber('kpi')
        ->name('business-development.incubatee-kpis.reviews.store');
    Route::resource('business-development/adjudications', AdjudicationAssessmentController::class)
        ->parameters(['adjudications' => 'assessment'])
        ->names('business-development.adjudications')
        ->middlewareFor(['index', 'show'], $adjudicationPermission)
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $adjudicationManagePermission);
    Route::post('business-development/adjudications/{assessment}/submit', [AdjudicationAssessmentController::class, 'submit'])
        ->middleware($adjudicationManagePermission)
        ->name('business-development.adjudications.submit');
    Route::post('business-development/adjudications/{assessment}/unlock', [AdjudicationAssessmentController::class, 'unlock'])
        ->middleware('permission:domain.business-development.manage')
        ->name('business-development.adjudications.unlock');

    Route::resource('stakeholders', StakeholderController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('stakeholders'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('stakeholders'));
    Route::post('stakeholders/{stakeholder}/contacts', [StakeholderController::class, 'storeContact'])
        ->middleware($managePermission('stakeholders'))
        ->whereNumber('stakeholder')
        ->name('stakeholders.contacts.store');
    Route::delete('stakeholders/{stakeholder}/contacts/{contact}', [StakeholderController::class, 'destroyContact'])
        ->middleware($managePermission('stakeholders'))
        ->whereNumber('stakeholder')
        ->whereNumber('contact')
        ->name('stakeholders.contacts.destroy');
    Route::get('organization', [OrganizationProfileController::class, 'show'])
        ->middleware($viewPermission('organization'))
        ->name('organization.show');
    Route::get('organization/registry', [OrganisationRegistryController::class, 'index'])
        ->middleware($viewPermission('organization'))
        ->name('organization.registry.index');
    Route::get('organization/compliance', [ComplianceRegistryController::class, 'index'])
        ->middleware('permission:domain.compliance.view|domain.compliance.manage|domain.organization.manage')
        ->name('organization.compliance.index');
    Route::put('organization', [OrganizationProfileController::class, 'update'])
        ->middleware($managePermission('organization'))
        ->name('organization.update');
    Route::post('organization/registry', [OrganisationRegistryController::class, 'store'])
        ->middleware($managePermission('organization'))
        ->name('organization.registry.store');
    Route::put('organization/registry/{organisation}', [OrganisationRegistryController::class, 'update'])
        ->middleware($managePermission('organization'))
        ->whereNumber('organisation')
        ->name('organization.registry.update');
    Route::post('organization/compliance', [ComplianceRegistryController::class, 'store'])
        ->middleware('permission:domain.compliance.manage|domain.organization.manage')
        ->name('organization.compliance.store');
    Route::put('organization/compliance/{record}', [ComplianceRegistryController::class, 'update'])
        ->middleware('permission:domain.compliance.manage|domain.organization.manage')
        ->whereNumber('record')
        ->name('organization.compliance.update');
    Route::post('organization/logos', [OrganizationProfileController::class, 'updateLogos'])
        ->middleware($managePermission('organization'))
        ->name('organization.logos.update');
    Route::get('organization/logos/{variant}', [OrganizationProfileController::class, 'showLogo'])
        ->middleware($viewPermission('organization'))
        ->name('organization.logos.show');
    Route::get('organization/documents', [OrganizationDocumentController::class, 'index'])
        ->middleware('auth')
        ->name('organization.documents.index');
    Route::post('organization/documents', [OrganizationDocumentController::class, 'store'])
        ->middleware('auth')
        ->name('organization.documents.store');
    Route::get('organization/documents/{document}/preview', [OrganizationDocumentController::class, 'preview'])
        ->middleware('auth')
        ->whereNumber('document')
        ->name('organization.documents.preview');
    Route::get('organization/documents/{document}', [OrganizationDocumentController::class, 'download'])
        ->middleware('auth')
        ->whereNumber('document')
        ->name('organization.documents.download');
    Route::post('organization/documents/{document}/lifecycle', [OrganizationDocumentController::class, 'updateLifecycle'])
        ->middleware('auth')
        ->whereNumber('document')
        ->name('organization.documents.lifecycle');
    Route::get('organization/document-library', [DocumentLibraryController::class, 'index'])
        ->middleware('auth')
        ->name('organization.document-library.index');
    Route::post('organization/document-library/folders', [DocumentLibraryController::class, 'storeFolder'])
        ->middleware('auth')
        ->name('organization.document-library.folders.store');
    Route::post('organization/document-library/root-folders', [DocumentLibraryController::class, 'storeRootFolder'])
        ->middleware('auth')
        ->name('organization.document-library.root-folders.store');
    Route::post('organization/document-library/folders/{folder}/rename', [DocumentLibraryController::class, 'renameFolder'])
        ->middleware('auth')
        ->whereNumber('folder')
        ->name('organization.document-library.folders.rename');
    Route::post('organization/document-library/folders/{folder}/move', [DocumentLibraryController::class, 'moveFolder'])
        ->middleware('auth')
        ->whereNumber('folder')
        ->name('organization.document-library.folders.move');
    Route::delete('organization/document-library/folders/{folder}', [DocumentLibraryController::class, 'destroyFolder'])
        ->middleware('auth')
        ->whereNumber('folder')
        ->name('organization.document-library.folders.destroy');
    Route::post('organization/document-library/files', [DocumentLibraryController::class, 'storeFile'])
        ->middleware('auth')
        ->name('organization.document-library.files.store');
    Route::post('organization/document-library/files/{file}/rename', [DocumentLibraryController::class, 'renameFile'])
        ->middleware('auth')
        ->whereNumber('file')
        ->name('organization.document-library.files.rename');
    Route::post('organization/document-library/files/{file}/move', [DocumentLibraryController::class, 'moveFile'])
        ->middleware('auth')
        ->whereNumber('file')
        ->name('organization.document-library.files.move');
    Route::delete('organization/document-library/files/{file}', [DocumentLibraryController::class, 'destroyFile'])
        ->middleware('auth')
        ->whereNumber('file')
        ->name('organization.document-library.files.destroy');
    Route::get('organization/document-library/files/{file}/download', [DocumentLibraryController::class, 'downloadFile'])
        ->middleware('auth')
        ->whereNumber('file')
        ->name('organization.document-library.files.download');
    Route::post('organization/document-library/files/{file}/publish-to-vault', [DocumentLibraryController::class, 'publishToVault'])
        ->middleware('auth')
        ->whereNumber('file')
        ->name('organization.document-library.files.publish-to-vault');
    Route::delete('organization/documents/{document}', [OrganizationDocumentController::class, 'destroy'])
        ->middleware('auth')
        ->whereNumber('document')
        ->name('organization.documents.destroy');
    Route::get('events/series/{seriesKey}', [EventController::class, 'series'])
        ->middleware($viewPermission('events'))
        ->name('events.series.show');
    Route::get('events/{event}/participants', [EventController::class, 'participants'])
        ->middleware($viewPermission('events'))
        ->whereNumber('event')
        ->name('events.participants.page');
    Route::get('events/{event}/registers', [EventController::class, 'registersPage'])
        ->middleware($viewPermission('events'))
        ->whereNumber('event')
        ->name('events.registers.page');
    Route::get('events/{event}/event-day', [EventController::class, 'eventDay'])
        ->middleware($viewPermission('events'))
        ->whereNumber('event')
        ->name('events.event-day');
    Route::get('events/{event}/workstreams/create', [EventController::class, 'createWorkstreamPage'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->name('events.workstreams.create');
    Route::get('events/{event}/workstreams/{workstream}/edit', [EventController::class, 'editWorkstreamPage'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('workstream')
        ->name('events.workstreams.edit');
    Route::get('events/{event}/tasks/create', [EventController::class, 'createTaskPage'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->name('events.tasks.create');
    Route::get('events/{event}/tasks/{task}/edit', [EventController::class, 'editTaskPage'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('task')
        ->name('events.tasks.edit');
    Route::resource('events', EventController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('events'))
        ->middlewareFor(['create', 'edit', 'store', 'update', 'destroy'], $managePermission('events'));
    Route::get('events/{event}/report/pdf', [EventController::class, 'reportPdf'])
        ->middleware($viewPermission('events'))
        ->whereNumber('event')
        ->name('events.report.pdf');
    Route::get('events/{event}/registers/{category?}/pdf', [EventController::class, 'registerPdf'])
        ->middleware($viewPermission('events'))
        ->whereNumber('event')
        ->name('events.registers.pdf');
    Route::get('events/{event}/registers/{category?}/csv', [EventController::class, 'registerCsv'])
        ->middleware($viewPermission('events'))
        ->whereNumber('event')
        ->name('events.registers.csv');
    Route::post('events/{event}/speakers', [EventController::class, 'storeSpeaker'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->name('events.speakers.store');
    Route::delete('events/{event}/speakers/{speaker}', [EventController::class, 'destroySpeaker'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('speaker')
        ->name('events.speakers.destroy');
    Route::post('events/{event}/attendees', [EventController::class, 'storeAttendee'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->name('events.attendees.store');
    Route::post('events/{event}/attendees/{attendee}/status', [EventController::class, 'updateAttendeeStatus'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('attendee')
        ->name('events.attendees.status');
    Route::delete('events/{event}/attendees/{attendee}', [EventController::class, 'destroyAttendee'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('attendee')
        ->name('events.attendees.destroy');
    Route::post('events/{event}/participants', [EventController::class, 'storeParticipant'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->name('events.participants.store');
    Route::put('events/{event}/participants/{participant}', [EventController::class, 'updateParticipant'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('participant')
        ->name('events.participants.update');
    Route::post('events/{event}/participants/{participant}/status', [EventController::class, 'updateParticipantStatus'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('participant')
        ->name('events.participants.status');
    Route::delete('events/{event}/participants/{participant}', [EventController::class, 'destroyParticipant'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('participant')
        ->name('events.participants.destroy');
    Route::post('events/{event}/participants/import', [EventController::class, 'importParticipants'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->name('events.participants.import');
    Route::post('events/{event}/outcome-report', [EventController::class, 'upsertOutcomeReport'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->name('events.outcome-report.upsert');
    Route::post('events/{event}/workstreams', [EventController::class, 'storeWorkstream'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->name('events.workstreams.store');
    Route::put('events/{event}/workstreams/{workstream}', [EventController::class, 'updateWorkstream'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('workstream')
        ->name('events.workstreams.update');
    Route::delete('events/{event}/workstreams/{workstream}', [EventController::class, 'destroyWorkstream'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('workstream')
        ->name('events.workstreams.destroy');
    Route::post('events/{event}/tasks', [EventController::class, 'storeTask'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->name('events.tasks.store');
    Route::put('events/{event}/tasks/{task}', [EventController::class, 'updateTask'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('task')
        ->name('events.tasks.update');
    Route::get('events/{event}/tasks/{task}/evidence', [EventController::class, 'downloadTaskEvidence'])
        ->middleware($viewPermission('events'))
        ->whereNumber('event')
        ->whereNumber('task')
        ->name('events.tasks.evidence');
    Route::delete('events/{event}/tasks/{task}', [EventController::class, 'destroyTask'])
        ->middleware($managePermission('events'))
        ->whereNumber('event')
        ->whereNumber('task')
        ->name('events.tasks.destroy');

    Route::resource('facilitators', FacilitatorController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('facilitators'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('facilitators'));

    Route::get('programs/list', [ProgramController::class, 'list'])
        ->middleware($viewPermission('programs'))
        ->name('programs.list');

    Route::resource('programs', ProgramController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('programs'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('programs'));

    Route::get('service-delivery', ServiceDeliveryDashboardController::class)
        ->middleware('permission:domain.service-delivery.view|domain.service-delivery.manage|domain.programs.view|domain.projects.view|domain.beneficiaries.view')
        ->name('service-delivery.dashboard');
    Route::get('service-delivery/activities', [ProjectActivityController::class, 'index'])
        ->middleware('permission:project-activities.view|project-activities.manage')
        ->name('service-delivery.activities.index');
    Route::post('service-delivery/activities', [ProjectActivityController::class, 'store'])
        ->middleware('permission:project-activities.manage')
        ->name('service-delivery.activities.store');
    Route::put('service-delivery/activities/{activity}', [ProjectActivityController::class, 'update'])
        ->middleware('permission:project-activities.manage')
        ->whereNumber('activity')
        ->name('service-delivery.activities.update');
    Route::get('service-delivery/attendance', [ServiceAttendanceController::class, 'index'])
        ->middleware('permission:domain.attendance.view|domain.attendance.manage|attendance.view|attendance.manage')
        ->name('service-delivery.attendance.index');
    Route::post('service-delivery/attendance', [ServiceAttendanceController::class, 'store'])
        ->middleware('permission:domain.attendance.manage|attendance.manage')
        ->name('service-delivery.attendance.store');
    Route::put('service-delivery/attendance/{attendance}', [ServiceAttendanceController::class, 'update'])
        ->middleware('permission:domain.attendance.manage|attendance.manage')
        ->whereNumber('attendance')
        ->name('service-delivery.attendance.update');
    Route::get('service-delivery/placements', [PlacementController::class, 'index'])
        ->middleware('permission:domain.placements.view|domain.placements.manage')
        ->name('service-delivery.placements.index');
    Route::post('service-delivery/placements', [PlacementController::class, 'store'])
        ->middleware('permission:domain.placements.manage')
        ->name('service-delivery.placements.store');
    Route::put('service-delivery/placements/{placement}', [PlacementController::class, 'update'])
        ->middleware('permission:domain.placements.manage')
        ->whereNumber('placement')
        ->name('service-delivery.placements.update');
    Route::get('service-delivery/partnerships', [PartnershipController::class, 'index'])
        ->middleware('permission:domain.partnerships.view|domain.partnerships.manage')
        ->name('service-delivery.partnerships.index');
    Route::post('service-delivery/partnerships', [PartnershipController::class, 'store'])
        ->middleware('permission:domain.partnerships.manage')
        ->name('service-delivery.partnerships.store');
    Route::put('service-delivery/partnerships/{partnership}', [PartnershipController::class, 'update'])
        ->middleware('permission:domain.partnerships.manage')
        ->whereNumber('partnership')
        ->name('service-delivery.partnerships.update');
    Route::get('service-delivery/outcomes', [ProgrammeOutcomeController::class, 'index'])
        ->middleware('permission:domain.outcomes.view|domain.outcomes.manage')
        ->name('service-delivery.outcomes.index');
    Route::post('service-delivery/outcomes', [ProgrammeOutcomeController::class, 'store'])
        ->middleware('permission:domain.outcomes.manage')
        ->name('service-delivery.outcomes.store');
    Route::put('service-delivery/outcomes/{outcome}', [ProgrammeOutcomeController::class, 'update'])
        ->middleware('permission:domain.outcomes.manage')
        ->whereNumber('outcome')
        ->name('service-delivery.outcomes.update');
    Route::get('service-delivery/documents', [ProgrammeDocumentController::class, 'index'])
        ->middleware('permission:domain.programs.view|domain.programs.manage|domain.projects.view|domain.projects.manage')
        ->name('programme-documents.index');
    Route::post('service-delivery/documents', [ProgrammeDocumentController::class, 'store'])
        ->middleware('permission:domain.programs.manage|domain.projects.manage')
        ->name('programme-documents.store');
    Route::get('service-delivery/documents/{programmeDocument}/download', [ProgrammeDocumentController::class, 'download'])
        ->middleware('permission:domain.programs.view|domain.programs.manage|domain.projects.view|domain.projects.manage')
        ->whereNumber('programmeDocument')
        ->name('programme-documents.download');

    Route::get('human-resources', [HumanResourcesController::class, 'dashboard'])
        ->middleware('permission:domain.human-resources.view|domain.human-resources.manage')
        ->name('human-resources.dashboard');
    Route::get('human-resources/attendance', [StaffAttendanceController::class, 'management'])
        ->middleware('permission:domain.human-resources.view|domain.human-resources.manage|domain.staff.view|domain.staff.manage')
        ->name('human-resources.attendance');
    Route::post('human-resources/attendance/late-overrides', [StaffAttendanceController::class, 'approveLateClockInRequest'])
        ->middleware('permission:domain.human-resources.manage|domain.staff.manage|domain.leave.manage')
        ->name('human-resources.attendance.late-overrides.store');
    Route::get('human-resources/attendance/report/pdf', [StaffAttendanceController::class, 'exportReportPdf'])
        ->middleware('permission:domain.human-resources.view|domain.human-resources.manage|domain.staff.view|domain.staff.manage')
        ->name('human-resources.attendance.report.pdf');

    Route::get('leave-requests', [LeaveRequestController::class, 'index'])
        ->middleware('permission:domain.leave.view|domain.leave.manage|domain.staff.view|domain.staff.manage')
        ->name('leave-requests.index');
    Route::get('leave-requests/{leave_request}', [LeaveRequestController::class, 'show'])
        ->middleware('permission:domain.leave.view|domain.leave.manage|domain.staff.view|domain.staff.manage|domain.human-resources.view|domain.human-resources.manage')
        ->whereNumber('leave_request')
        ->name('leave-requests.show');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])
        ->middleware('permission:domain.leave.view|domain.leave.manage')
        ->name('leave-requests.store');
    Route::post('leave-requests/{leave_request}/manager-approve', [LeaveRequestController::class, 'managerApprove'])
        ->middleware('permission:domain.leave.manage')
        ->whereNumber('leave_request')
        ->name('leave-requests.manager-approve');
    Route::post('leave-requests/{leave_request}/manager-reject', [LeaveRequestController::class, 'managerReject'])
        ->middleware('permission:domain.leave.manage')
        ->whereNumber('leave_request')
        ->name('leave-requests.manager-reject');
    Route::post('leave-requests/{leave_request}/revoke', [LeaveRequestController::class, 'revoke'])
        ->middleware('permission:domain.leave.view|domain.leave.manage')
        ->whereNumber('leave_request')
        ->name('leave-requests.revoke');
    Route::post('leave-requests/{leave_request}/hr-approve', [LeaveRequestController::class, 'hrApprove'])
        ->middleware('permission:domain.human-resources.manage')
        ->whereNumber('leave_request')
        ->name('leave-requests.hr-approve');
    Route::post('leave-requests/{leave_request}/hr-reject', [LeaveRequestController::class, 'hrReject'])
        ->middleware('permission:domain.human-resources.manage')
        ->whereNumber('leave_request')
        ->name('leave-requests.hr-reject');

    Route::get('assets', [AssetController::class, 'dashboard'])
        ->middleware('permission:domain.assets.view|domain.assets.manage')
        ->name('assets.dashboard');
    Route::get('assets/register', [AssetController::class, 'registerCategories'])
        ->middleware('permission:domain.assets.view|domain.assets.manage')
        ->name('assets.register.categories');
    Route::get('assets/register/{category}/models', [AssetController::class, 'registerModels'])
        ->middleware('permission:domain.assets.view|domain.assets.manage')
        ->whereNumber('category')
        ->name('assets.register.models');
    Route::get('assets/register/{category}/models/{model}', [AssetController::class, 'registerItems'])
        ->middleware('permission:domain.assets.view|domain.assets.manage')
        ->whereNumber('category')
        ->name('assets.register.items');
    Route::get('assets/manager-dashboard', [AssetController::class, 'managerDashboard'])
        ->middleware('permission:domain.assets.view|domain.assets.manage')
        ->name('assets.manager-dashboard');
    Route::get('assets/list', [AssetController::class, 'index'])
        ->middleware('permission:domain.assets.view|domain.assets.manage')
        ->name('assets.list');
    Route::get('assets/export', [AssetController::class, 'exportRegister'])
        ->middleware('permission:domain.assets.view|domain.assets.manage')
        ->name('assets.export');
    Route::resource('assets', AssetController::class)
        ->except(['index'])
        ->whereNumber('asset')
        ->middlewareFor('show', $viewPermission('assets'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('assets'));
    Route::post('assets/batches', [AssetController::class, 'storeBatch'])
        ->middleware('permission:domain.assets.manage')
        ->name('assets.batches.store');
    Route::put('assets/batches/{batch}', [AssetController::class, 'updateBatch'])
        ->middleware('permission:domain.assets.manage')
        ->whereNumber('batch')
        ->name('assets.batches.update');
    Route::delete('assets/batches/{batch}', [AssetController::class, 'destroyBatch'])
        ->middleware('permission:domain.assets.manage')
        ->whereNumber('batch')
        ->name('assets.batches.destroy');
    Route::post('assets/{asset}/assign', [AssetController::class, 'assign'])
        ->middleware('permission:domain.assets.manage')
        ->whereNumber('asset')
        ->name('assets.assign');
    Route::post('assets/{asset}/return', [AssetController::class, 'returnAsset'])
        ->middleware('permission:domain.assets.manage')
        ->whereNumber('asset')
        ->name('assets.return');
    Route::post('assets/{asset}/maintenance/start', [AssetController::class, 'startMaintenance'])
        ->middleware('permission:domain.assets.manage')
        ->whereNumber('asset')
        ->name('assets.maintenance.start');
    Route::post('assets/{asset}/maintenance/complete', [AssetController::class, 'completeMaintenance'])
        ->middleware('permission:domain.assets.manage')
        ->whereNumber('asset')
        ->name('assets.maintenance.complete');
    Route::post('assets/{asset}/decommission', [AssetController::class, 'decommission'])
        ->middleware('permission:domain.assets.manage')
        ->whereNumber('asset')
        ->name('assets.decommission');
    Route::post('assets/{asset}/report-fault', [AssetController::class, 'reportFault'])
        ->whereNumber('asset')
        ->name('assets.report-fault');
    Route::resource('asset-categories', AssetCategoryController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('assets'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('assets'));

    Route::get('projects', [ProjectController::class, 'dashboard'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->name('projects.dashboard');
    Route::get('projects/list', [ProjectController::class, 'index'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->name('projects.list');
    Route::resource('projects', ProjectController::class)
        ->except(['index'])
        ->whereNumber('project')
        ->middlewareFor('show', $viewPermission('projects'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('projects'));
    Route::get('projects/{project}/finalization', [ProjectController::class, 'finalization'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->whereNumber('project')
        ->name('projects.finalization');
    Route::post('projects/{project}/milestones', [ProjectController::class, 'addMilestone'])
        ->middleware('permission:domain.projects.manage')
        ->whereNumber('project')
        ->name('projects.milestones.store');
    Route::post('projects/{project}/milestones/sync', [ProjectController::class, 'syncMilestones'])
        ->middleware('permission:domain.projects.manage')
        ->whereNumber('project')
        ->name('projects.milestones.sync');
    Route::post('projects/{project}/conclude', [ProjectController::class, 'conclude'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->whereNumber('project')
        ->name('projects.conclude');
    Route::post('projects/{project}/reports', [ProjectController::class, 'createReport'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->whereNumber('project')
        ->name('projects.reports.store');
    Route::get('projects/{project}/reports/{report}/pdf', [ProjectController::class, 'downloadReport'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->whereNumber('project')
        ->whereNumber('report')
        ->name('projects.reports.pdf');
    Route::post('projects/{project}/closure-evidence', [ProjectController::class, 'uploadClosureEvidence'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->whereNumber('project')
        ->name('projects.closure-evidence.store');
    Route::get('projects/{project}/closure-evidence/{evidence}', [ProjectController::class, 'downloadClosureEvidence'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->whereNumber('project')
        ->whereNumber('evidence')
        ->name('projects.closure-evidence.download');
    Route::delete('projects/{project}/closure-evidence/{evidence}', [ProjectController::class, 'deleteClosureEvidence'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->whereNumber('project')
        ->whereNumber('evidence')
        ->name('projects.closure-evidence.destroy');
    Route::get('project-locations/dashboard', [ProjectLocationController::class, 'dashboard'])
        ->middleware('permission:domain.projects.view|domain.projects.manage|project-activities.view')
        ->name('project-locations.dashboard');
    Route::resource('project-locations', ProjectLocationController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('projects'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('projects'));
    Route::get('project-locations/{project_location}/progress', [ProjectLocationController::class, 'progress'])
        ->middleware('permission:domain.projects.view|domain.projects.manage|project-activities.view')
        ->whereNumber('project_location')
        ->name('project-locations.progress');
    Route::get('project-locations/{project_location}/attendance', [ProjectAttendanceController::class, 'locationRegister'])
        ->middleware('permission:domain.projects.view|domain.projects.manage|project-activities.view|project-activities.manage|attendance.view|attendance.manage')
        ->whereNumber('project_location')
        ->name('project-locations.attendance');
    Route::post('project-locations/{project_location}/attendance', [ProjectAttendanceController::class, 'saveLocationRegister'])
        ->middleware('permission:domain.projects.manage|project-activities.manage|attendance.manage')
        ->whereNumber('project_location')
        ->name('project-locations.attendance.save');
    Route::post('project-locations/{project_location}/attendance/holiday', [ProjectAttendanceController::class, 'markHoliday'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->whereNumber('project_location')
        ->name('project-locations.attendance.holiday');
    Route::get('attendance-registers/{attendance_register}/export/pdf', [ProjectAttendanceController::class, 'exportRegisterPdf'])
        ->middleware('permission:domain.projects.view|domain.projects.manage|project-activities.view|project-activities.manage|attendance.view|attendance.manage')
        ->whereNumber('attendance_register')
        ->name('attendance-registers.export.pdf');
    Route::post('project-locations/{project_location}/assessments', [ProjectMilestoneAssessmentController::class, 'store'])
        ->middleware('permission:domain.projects.manage|project-activities.manage')
        ->whereNumber('project_location')
        ->name('project-locations.assessments.store');
    Route::get('projects/attendance-summary', [ProjectAttendanceController::class, 'projectSummary'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->name('projects.attendance-summary');
    Route::resource('project-enrollments', ProjectEnrollmentController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('projects'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('projects'));
    Route::get('milestone-templates/programs/{program}', [MilestoneTemplateController::class, 'program'])
        ->middleware('permission:domain.projects.view|domain.projects.manage')
        ->whereNumber('program')
        ->name('milestone-templates.programs');
    Route::resource('milestone-templates', MilestoneTemplateController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('projects'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('projects'));

    Route::get('staff/{staff}/profile', [StaffController::class, 'profileShow'])
        ->middleware($viewPermission('staff'))
        ->whereNumber('staff')
        ->name('staff.profile');
    Route::post('staff/{staff}/promote-manager', [StaffController::class, 'promote'])
        ->middleware($managePermission('staff'))
        ->whereNumber('staff')
        ->name('staff.promote-manager');
    Route::post('staff/{staff}/reset-password', [StaffController::class, 'resetPassword'])
        ->middleware($managePermission('staff'))
        ->whereNumber('staff')
        ->name('staff.reset-password');
    Route::get('staff/dashboard', [StaffController::class, 'dashboard'])
        ->middleware($viewPermission('staff'))
        ->name('staff.dashboard');
    Route::get('staff/list', [StaffController::class, 'index'])
        ->middleware($viewPermission('staff'))
        ->name('staff.list');
    Route::resource('staff', StaffController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('staff'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('staff'));
    Route::resource('staff-departments', StaffDepartmentController::class)
        ->middlewareFor(['index', 'show'], $viewPermission('staff'))
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], $managePermission('staff'));

    Route::get('access-control', [AccessControlController::class, 'index'])
        ->name('access-control.index');
    Route::get('access-control/roles', [AccessControlController::class, 'rolesPage'])
        ->name('access-control.roles.index');
    Route::post('access-control/roles', [AccessControlController::class, 'storeRole'])
        ->name('access-control.roles.store');
    Route::put('access-control/roles/{role}', [AccessControlController::class, 'updateRole'])
        ->whereNumber('role')
        ->name('access-control.roles.update');
    Route::delete('access-control/roles/{role}', [AccessControlController::class, 'destroyRole'])
        ->whereNumber('role')
        ->name('access-control.roles.destroy');
    Route::get('access-control/permissions', [AccessControlController::class, 'permissionsPage'])
        ->name('access-control.permissions.index');
    Route::post('access-control/permissions', [AccessControlController::class, 'storePermission'])
        ->name('access-control.permissions.store');
    Route::put('access-control/permissions/{permission}', [AccessControlController::class, 'updatePermission'])
        ->whereNumber('permission')
        ->name('access-control.permissions.update');
    Route::delete('access-control/permissions/{permission}', [AccessControlController::class, 'destroyPermission'])
        ->whereNumber('permission')
        ->name('access-control.permissions.destroy');
    Route::get('access-control/assignments', [AccessControlController::class, 'assignmentsPage'])
        ->name('access-control.assignments.index');
    Route::post('access-control/users/{user}/roles', [AccessControlController::class, 'syncUserRoles'])
        ->whereNumber('user')
        ->name('access-control.users.roles.sync');
    Route::post('access-control/users/{user}/permissions', [AccessControlController::class, 'syncUserPermissions'])
        ->whereNumber('user')
        ->name('access-control.users.permissions.sync');
    Route::get('governance', [GovernanceDashboardController::class, 'index'])
        ->middleware($viewPermission('governance'))
        ->name('governance.index');
    Route::post('governance', [GovernanceDashboardController::class, 'store'])
        ->middleware($managePermission('governance'))
        ->name('governance.store');
    Route::put('governance/{structure}', [GovernanceDashboardController::class, 'update'])
        ->middleware($managePermission('governance'))
        ->whereNumber('structure')
        ->name('governance.update');

    Route::get('committees', [CommitteeController::class, 'index'])
        ->middleware($viewPermission('committees'))
        ->name('committees.index');
    Route::post('committees', [CommitteeController::class, 'store'])
        ->middleware($managePermission('committees'))
        ->name('committees.store');
    Route::put('committees/{committee}', [CommitteeController::class, 'update'])
        ->middleware($managePermission('committees'))
        ->whereNumber('committee')
        ->name('committees.update');

    Route::get('meetings', [MeetingController::class, 'index'])
        ->middleware($viewPermission('meetings'))
        ->name('meetings.index');
    Route::post('meetings', [MeetingController::class, 'store'])
        ->middleware($managePermission('meetings'))
        ->name('meetings.store');
    Route::put('meetings/{meeting}', [MeetingController::class, 'update'])
        ->middleware($managePermission('meetings'))
        ->whereNumber('meeting')
        ->name('meetings.update');

    Route::get('resolutions', [ResolutionController::class, 'index'])
        ->middleware($viewPermission('resolutions'))
        ->name('resolutions.index');
    Route::post('resolutions', [ResolutionController::class, 'store'])
        ->middleware($managePermission('resolutions'))
        ->name('resolutions.store');
    Route::put('resolutions/{resolution}', [ResolutionController::class, 'update'])
        ->middleware($managePermission('resolutions'))
        ->whereNumber('resolution')
        ->name('resolutions.update');

    Route::get('members', [MemberController::class, 'index'])
        ->middleware($viewPermission('members'))
        ->name('members.index');
    Route::get('members/create', [MemberController::class, 'create'])
        ->middleware($managePermission('members'))
        ->name('members.create');
    Route::post('members', [MemberController::class, 'store'])
        ->middleware($managePermission('members'))
        ->name('members.store');
    Route::get('members/{member}/edit', [MemberController::class, 'edit'])
        ->middleware($managePermission('members'))
        ->whereNumber('member')
        ->name('members.edit');
    Route::put('members/{member}', [MemberController::class, 'update'])
        ->middleware($managePermission('members'))
        ->whereNumber('member')
        ->name('members.update');

    Route::get('geography', [GeographicRegistryController::class, 'index'])
        ->middleware($viewPermission('geography'))
        ->name('geography.index');
    Route::post('geography', [GeographicRegistryController::class, 'store'])
        ->middleware($managePermission('geography'))
        ->name('geography.store');

    Route::get('human-capital/dashboard', [HumanCapitalDashboardController::class, 'dashboard'])
        ->middleware('permission:domain.human-capital.view|domain.human-capital.manage|domain.members.view|domain.members.manage')
        ->name('human-capital.dashboard');
    Route::get('human-capital/reports', [HumanCapitalDashboardController::class, 'reports'])
        ->middleware('permission:domain.human-capital.view|domain.human-capital.manage|domain.reporting.view|domain.reporting.manage')
        ->name('human-capital.reports');

    Route::get('intelligence', [IntelligenceWorkspaceController::class, 'index'])
        ->middleware('permission:domain.intelligence.view|domain.intelligence.manage')
        ->name('intelligence.index');
    Route::get('intelligence/agents', [AgentController::class, 'index'])
        ->middleware('permission:domain.intelligence.view|domain.intelligence.manage')
        ->name('intelligence.agents.index');
    Route::post('intelligence/agents', [AgentController::class, 'store'])
        ->middleware('permission:domain.intelligence.manage')
        ->name('intelligence.agents.store');
    Route::put('intelligence/agents/{agent}', [AgentController::class, 'update'])
        ->middleware('permission:domain.intelligence.manage')
        ->whereNumber('agent')
        ->name('intelligence.agents.update');

    Route::get('intelligence/prompts', [PromptTemplateController::class, 'index'])
        ->middleware('permission:domain.intelligence.view|domain.intelligence.manage')
        ->name('intelligence.prompts.index');
    Route::post('intelligence/prompts', [PromptTemplateController::class, 'store'])
        ->middleware('permission:domain.intelligence.manage')
        ->name('intelligence.prompts.store');
    Route::put('intelligence/prompts/{promptTemplate}', [PromptTemplateController::class, 'update'])
        ->middleware('permission:domain.intelligence.manage')
        ->whereNumber('promptTemplate')
        ->name('intelligence.prompts.update');
    Route::post('intelligence/prompts/{promptTemplate}/activate', [PromptTemplateController::class, 'activate'])
        ->middleware('permission:domain.intelligence.manage')
        ->whereNumber('promptTemplate')
        ->name('intelligence.prompts.activate');

    Route::get('intelligence/memory', [MemoryController::class, 'index'])
        ->middleware('permission:domain.intelligence.view|domain.intelligence.manage')
        ->name('intelligence.memory.index');
    Route::post('intelligence/memory', [MemoryController::class, 'store'])
        ->middleware('permission:domain.intelligence.manage')
        ->name('intelligence.memory.store');
    Route::put('intelligence/memory/{memory}', [MemoryController::class, 'update'])
        ->middleware('permission:domain.intelligence.manage')
        ->whereNumber('memory')
        ->name('intelligence.memory.update');
    Route::post('intelligence/memory/{memory}/review', [MemoryController::class, 'review'])
        ->middleware('permission:domain.intelligence.manage')
        ->whereNumber('memory')
        ->name('intelligence.memory.review');

    Route::get('intelligence/tools', [AiToolController::class, 'index'])
        ->middleware('permission:domain.intelligence.view|domain.intelligence.manage')
        ->name('intelligence.tools.index');
    Route::post('intelligence/tools', [AiToolController::class, 'store'])
        ->middleware('permission:domain.intelligence.manage')
        ->name('intelligence.tools.store');
    Route::put('intelligence/tools/{tool}', [AiToolController::class, 'update'])
        ->middleware('permission:domain.intelligence.manage')
        ->whereNumber('tool')
        ->name('intelligence.tools.update');

    Route::get('intelligence/tool-logs', [ToolExecutionLogController::class, 'index'])
        ->middleware('permission:domain.intelligence.view|domain.intelligence.manage')
        ->name('intelligence.logs.index');

    Route::get('intelligence/model-routing', [ModelRoutingController::class, 'index'])
        ->middleware('permission:domain.intelligence.view|domain.intelligence.manage')
        ->name('intelligence.routing.index');
    Route::post('intelligence/model-routing', [ModelRoutingController::class, 'store'])
        ->middleware('permission:domain.intelligence.manage')
        ->name('intelligence.routing.store');
    Route::put('intelligence/model-routing/{rule}', [ModelRoutingController::class, 'update'])
        ->middleware('permission:domain.intelligence.manage')
        ->whereNumber('rule')
        ->name('intelligence.routing.update');

    Route::post('intelligence/conversations', [ConversationController::class, 'store'])
        ->middleware('permission:domain.intelligence.view|domain.intelligence.manage')
        ->name('intelligence.conversations.store');
});

require __DIR__.'/settings.php';
