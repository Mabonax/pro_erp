<?php

namespace App\Providers;

use App\Domains\Assets\Repositories\AssetCategoryRepository;
use App\Domains\Assets\Repositories\AssetCategoryRepositoryInterface;
use App\Domains\Assets\Repositories\AssetRepository;
use App\Domains\Assets\Repositories\AssetRepositoryInterface;
use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\Beneficiaries\Policies\BeneficiaryPolicy;
use App\Domains\Beneficiaries\Repositories\BeneficiaryRepository;
use App\Domains\Beneficiaries\Repositories\BeneficiaryRepositoryInterface;
use App\Domains\BusinessDevelopment\Adjudication\Models\AdjudicationAssessment;
use App\Domains\BusinessDevelopment\Adjudication\Policies\AdjudicationAssessmentPolicy;
use App\Domains\BusinessDevelopment\Adjudication\Repositories\AdjudicationAssessmentRepositoryInterface;
use App\Domains\BusinessDevelopment\Adjudication\Repositories\EloquentAdjudicationAssessmentRepository;
use App\Domains\BusinessDevelopment\Models\BdsApplication;
use App\Domains\BusinessDevelopment\Models\BdsIncubateeKpi;
use App\Domains\BusinessDevelopment\Models\BdsPitchSession;
use App\Domains\BusinessDevelopment\Policies\BdsApplicationPolicy;
use App\Domains\BusinessDevelopment\Policies\BdsIncubateeKpiPolicy;
use App\Domains\BusinessDevelopment\Policies\BdsPitchSessionPolicy;
use App\Domains\BusinessDevelopment\Repositories\BdsApplicationRepository;
use App\Domains\BusinessDevelopment\Repositories\BdsApplicationRepositoryInterface;
use App\Domains\BusinessDevelopment\Repositories\BdsIncubateeRepository;
use App\Domains\BusinessDevelopment\Repositories\BdsIncubateeRepositoryInterface;
use App\Domains\Committees\Interfaces\CommitteeRepositoryInterface;
use App\Domains\Committees\Models\Committee;
use App\Domains\Committees\Policies\CommitteePolicy;
use App\Domains\Committees\Repositories\CommitteeRepository;
use App\Domains\Compliance\Interfaces\ComplianceRepositoryInterface;
use App\Domains\Compliance\Models\ComplianceRecord;
use App\Domains\Compliance\Policies\ComplianceRecordPolicy;
use App\Domains\Compliance\Repositories\ComplianceRepository;
use App\Domains\Documents\Models\DocumentFile;
use App\Domains\Documents\Models\DocumentFolder;
use App\Domains\Documents\Policies\DocumentFilePolicy;
use App\Domains\Documents\Policies\DocumentFolderPolicy;
use App\Domains\Documents\Repositories\DocumentFileRepository;
use App\Domains\Documents\Repositories\DocumentFileRepositoryInterface;
use App\Domains\Documents\Repositories\DocumentFolderRepository;
use App\Domains\Documents\Repositories\DocumentFolderRepositoryInterface;
use App\Domains\Events\Models\Event;
use App\Domains\Events\Policies\EventPolicy;
use App\Domains\Events\Repositories\EventRepository;
use App\Domains\Events\Repositories\EventRepositoryInterface;
use App\Domains\Facilitators\Models\Facilitator;
use App\Domains\Facilitators\Policies\FacilitatorPolicy;
use App\Domains\Facilitators\Repositories\FacilitatorRepository;
use App\Domains\Facilitators\Repositories\FacilitatorRepositoryInterface;
use App\Domains\Finance\Models\TravelClaim;
use App\Domains\Finance\Policies\TravelClaimPolicy;
use App\Domains\Geography\Interfaces\GeographyRepositoryInterface;
use App\Domains\Geography\Repositories\GeographyRepository;
use App\Domains\Governance\Interfaces\GovernanceStructureRepositoryInterface;
use App\Domains\Governance\Models\GovernanceStructure;
use App\Domains\Governance\Policies\GovernanceStructurePolicy;
use App\Domains\Governance\Repositories\GovernanceStructureRepository;
use App\Domains\Intelligence\Models\Agent;
use App\Domains\Intelligence\Models\AiTool;
use App\Domains\Intelligence\Models\MemoryRecord;
use App\Domains\Intelligence\Models\ModelRoutingRule;
use App\Domains\Intelligence\Models\PromptTemplate;
use App\Domains\Intelligence\Policies\AgentPolicy;
use App\Domains\Intelligence\Policies\AiToolPolicy;
use App\Domains\Intelligence\Policies\MemoryRecordPolicy;
use App\Domains\Intelligence\Policies\ModelRoutingRulePolicy;
use App\Domains\Intelligence\Policies\PromptTemplatePolicy;
use App\Domains\Marketing\Models\MarketingAsset;
use App\Domains\Marketing\Models\MarketingDeliverable;
use App\Domains\Marketing\Models\MarketingJob;
use App\Domains\Marketing\Models\MarketingRequest;
use App\Domains\Marketing\Policies\MarketingAssetPolicy;
use App\Domains\Marketing\Policies\MarketingDeliverablePolicy;
use App\Domains\Marketing\Policies\MarketingJobPolicy;
use App\Domains\Marketing\Policies\MarketingRequestPolicy;
use App\Domains\Marketing\Repositories\MarketingJobRepository;
use App\Domains\Marketing\Repositories\MarketingJobRepositoryInterface;
use App\Domains\Marketing\Repositories\MarketingRequestRepository;
use App\Domains\Marketing\Repositories\MarketingRequestRepositoryInterface;
use App\Domains\Meetings\Interfaces\MeetingRepositoryInterface;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Policies\MeetingPolicy;
use App\Domains\Meetings\Repositories\MeetingRepository;
use App\Domains\Members\Interfaces\MemberRepositoryInterface;
use App\Domains\Members\Models\Member;
use App\Domains\Members\Policies\MemberPolicy;
use App\Domains\Members\Repositories\MemberRepository;
use App\Domains\Organisation\Events\OrganisationRegistered;
use App\Domains\Organisation\Interfaces\OrganisationRepositoryInterface;
use App\Domains\Organisation\Listeners\SyncOrganisationBranding;
use App\Domains\Organisation\Models\Organisation;
use App\Domains\Organisation\Policies\OrganisationPolicy;
use App\Domains\Organisation\Repositories\OrganisationRepository;
use App\Domains\Organization\Models\OrganizationDocument;
use App\Domains\Organization\Models\OrganizationProfile;
use App\Domains\Organization\Policies\OrganizationDocumentPolicy;
use App\Domains\Organization\Policies\OrganizationProfilePolicy;
use App\Domains\Organization\Repositories\OrganizationProfileRepository;
use App\Domains\Organization\Repositories\OrganizationProfileRepositoryInterface;
use App\Domains\Programs\Repositories\ProgramRepository;
use App\Domains\Programs\Repositories\ProgramRepositoryInterface;
use App\Domains\Projects\Models\AttendanceRegister;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMilestoneAssessment;
use App\Domains\Projects\Policies\AttendanceRegisterPolicy;
use App\Domains\Projects\Policies\ProjectMilestoneAssessmentPolicy;
use App\Domains\Projects\Policies\ProjectPolicy;
use App\Domains\Projects\Repositories\ProjectEnrollmentRepository;
use App\Domains\Projects\Repositories\ProjectEnrollmentRepositoryInterface;
use App\Domains\Projects\Repositories\ProjectLocationRepository;
use App\Domains\Projects\Repositories\ProjectLocationRepositoryInterface;
use App\Domains\Projects\Repositories\ProjectRepository;
use App\Domains\Projects\Repositories\ProjectRepositoryInterface;
use App\Domains\Resolutions\Interfaces\ResolutionRepositoryInterface;
use App\Domains\Resolutions\Models\Resolution;
use App\Domains\Resolutions\Policies\ResolutionPolicy;
use App\Domains\Resolutions\Repositories\ResolutionRepository;
use App\Domains\Staff\Events\StaffMemberCreated;
use App\Domains\Staff\Listeners\SendStaffSystemAccessEmail;
use App\Domains\Staff\Repositories\StaffDepartmentRepository;
use App\Domains\Staff\Repositories\StaffDepartmentRepositoryInterface;
use App\Domains\Staff\Repositories\StaffRepository;
use App\Domains\Staff\Repositories\StaffRepositoryInterface;
use App\Domains\StaffAttendance\Models\StaffAttendanceRecord;
use App\Domains\StaffAttendance\Policies\StaffAttendanceRecordPolicy;
use App\Domains\StaffAttendance\Repositories\StaffAttendanceRepository;
use App\Domains\StaffAttendance\Repositories\StaffAttendanceRepositoryInterface;
use App\Domains\Stakeholders\Repositories\StakeholderRepository;
use App\Domains\Stakeholders\Repositories\StakeholderRepositoryInterface;
use App\Domains\TaskManagement\Models\SupportTicket;
use App\Domains\TaskManagement\Models\WorkTask;
use App\Domains\TaskManagement\Policies\SupportTicketPolicy;
use App\Domains\TaskManagement\Policies\WorkTaskPolicy;
use App\Domains\TaskManagement\Repositories\SupportTicketRepository;
use App\Domains\TaskManagement\Repositories\SupportTicketRepositoryInterface;
use App\Domains\TaskManagement\Repositories\WorkTaskRepository;
use App\Domains\TaskManagement\Repositories\WorkTaskRepositoryInterface;
use App\Support\Branding\BrandingService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            BeneficiaryRepositoryInterface::class,
            BeneficiaryRepository::class
        );

        $this->app->bind(
            StakeholderRepositoryInterface::class,
            StakeholderRepository::class
        );

        $this->app->bind(
            FacilitatorRepositoryInterface::class,
            FacilitatorRepository::class
        );

        $this->app->bind(
            ProgramRepositoryInterface::class,
            ProgramRepository::class
        );

        $this->app->bind(
            AssetRepositoryInterface::class,
            AssetRepository::class
        );

        $this->app->bind(
            AssetCategoryRepositoryInterface::class,
            AssetCategoryRepository::class
        );

        $this->app->bind(
            ProjectRepositoryInterface::class,
            ProjectRepository::class
        );

        $this->app->bind(
            ProjectLocationRepositoryInterface::class,
            ProjectLocationRepository::class
        );

        $this->app->bind(
            ProjectEnrollmentRepositoryInterface::class,
            ProjectEnrollmentRepository::class
        );

        $this->app->bind(
            StaffRepositoryInterface::class,
            StaffRepository::class
        );

        $this->app->bind(
            StaffDepartmentRepositoryInterface::class,
            StaffDepartmentRepository::class
        );

        $this->app->bind(
            StaffAttendanceRepositoryInterface::class,
            StaffAttendanceRepository::class
        );

        $this->app->bind(
            BdsApplicationRepositoryInterface::class,
            BdsApplicationRepository::class
        );

        $this->app->bind(
            BdsIncubateeRepositoryInterface::class,
            BdsIncubateeRepository::class
        );

        $this->app->bind(
            AdjudicationAssessmentRepositoryInterface::class,
            EloquentAdjudicationAssessmentRepository::class
        );

        $this->app->bind(
            WorkTaskRepositoryInterface::class,
            WorkTaskRepository::class
        );

        $this->app->bind(
            SupportTicketRepositoryInterface::class,
            SupportTicketRepository::class
        );

        $this->app->bind(
            MarketingJobRepositoryInterface::class,
            MarketingJobRepository::class
        );

        $this->app->bind(
            MarketingRequestRepositoryInterface::class,
            MarketingRequestRepository::class
        );

        $this->app->bind(
            OrganizationProfileRepositoryInterface::class,
            OrganizationProfileRepository::class
        );

        $this->app->bind(
            EventRepositoryInterface::class,
            EventRepository::class
        );

        $this->app->bind(
            OrganisationRepositoryInterface::class,
            OrganisationRepository::class
        );

        $this->app->bind(
            ComplianceRepositoryInterface::class,
            ComplianceRepository::class
        );

        $this->app->bind(
            GovernanceStructureRepositoryInterface::class,
            GovernanceStructureRepository::class
        );

        $this->app->bind(
            CommitteeRepositoryInterface::class,
            CommitteeRepository::class
        );

        $this->app->bind(
            MeetingRepositoryInterface::class,
            MeetingRepository::class
        );

        $this->app->bind(
            ResolutionRepositoryInterface::class,
            ResolutionRepository::class
        );

        $this->app->bind(
            DocumentFolderRepositoryInterface::class,
            DocumentFolderRepository::class
        );

        $this->app->bind(
            DocumentFileRepositoryInterface::class,
            DocumentFileRepository::class
        );

        $this->app->bind(
            MemberRepositoryInterface::class,
            MemberRepository::class
        );

        $this->app->bind(
            GeographyRepositoryInterface::class,
            GeographyRepository::class
        );
    }

    public function boot(): void
    {
        $this->configureDefaults();

        Gate::policy(Beneficiary::class, BeneficiaryPolicy::class);
        Gate::policy(Facilitator::class, FacilitatorPolicy::class);
        Gate::policy(OrganizationProfile::class, OrganizationProfilePolicy::class);
        Gate::policy(OrganizationDocument::class, OrganizationDocumentPolicy::class);
        Gate::policy(Organisation::class, OrganisationPolicy::class);
        Gate::policy(ComplianceRecord::class, ComplianceRecordPolicy::class);
        Gate::policy(GovernanceStructure::class, GovernanceStructurePolicy::class);
        Gate::policy(Committee::class, CommitteePolicy::class);
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(Resolution::class, ResolutionPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(BdsApplication::class, BdsApplicationPolicy::class);
        Gate::policy(BdsPitchSession::class, BdsPitchSessionPolicy::class);
        Gate::policy(BdsIncubateeKpi::class, BdsIncubateeKpiPolicy::class);
        Gate::policy(AttendanceRegister::class, AttendanceRegisterPolicy::class);
        Gate::policy(ProjectMilestoneAssessment::class, ProjectMilestoneAssessmentPolicy::class);
        Gate::policy(AdjudicationAssessment::class, AdjudicationAssessmentPolicy::class);
        Gate::policy(WorkTask::class, WorkTaskPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
        Gate::policy(MarketingJob::class, MarketingJobPolicy::class);
        Gate::policy(MarketingRequest::class, MarketingRequestPolicy::class);
        Gate::policy(MarketingDeliverable::class, MarketingDeliverablePolicy::class);
        Gate::policy(MarketingAsset::class, MarketingAssetPolicy::class);
        Gate::policy(TravelClaim::class, TravelClaimPolicy::class);
        Gate::policy(StaffAttendanceRecord::class, StaffAttendanceRecordPolicy::class);
        Gate::policy(DocumentFolder::class, DocumentFolderPolicy::class);
        Gate::policy(DocumentFile::class, DocumentFilePolicy::class);
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(Agent::class, AgentPolicy::class);
        Gate::policy(PromptTemplate::class, PromptTemplatePolicy::class);
        Gate::policy(MemoryRecord::class, MemoryRecordPolicy::class);
        Gate::policy(AiTool::class, AiToolPolicy::class);
        Gate::policy(ModelRoutingRule::class, ModelRoutingRulePolicy::class);

        EventFacade::listen(StaffMemberCreated::class, SendStaffSystemAccessEmail::class);
        EventFacade::listen(OrganisationRegistered::class, SyncOrganisationBranding::class);

        View::share('brand', app(BrandingService::class)->payload());

        Gate::before(function ($user, string $ability) {
            return method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super-admin', 'super admin'])
                ? true
                : null;
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        RateLimiter::for('public-intakes', function (Request $request) {
            return [
                Limit::perMinute(12)->by($request->ip()),
                Limit::perHour(60)->by('public-intakes:'.$request->ip()),
            ];
        });

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
