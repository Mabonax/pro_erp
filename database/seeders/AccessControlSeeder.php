<?php

namespace Database\Seeders;

use App\Domains\Facilitators\Models\Facilitator;
use App\Domains\Staff\Models\StaffDepartment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('access_control.guard', 'web');
        $domains = config('access_control.domains', []);

        $allDomainPermissions = [];
        $viewPermissions = [];
        $managePermissions = [];

        foreach ($domains as $domain) {
            $view = "domain.{$domain}.view";
            $manage = "domain.{$domain}.manage";

            $viewPermissions[] = $view;
            $managePermissions[] = $manage;
            $allDomainPermissions[] = $view;
            $allDomainPermissions[] = $manage;
        }

        $accessControlPermissions = [
            'access-control.view',
            'roles.create',
            'roles.view',
            'roles.update',
            'roles.delete',
            'permissions.create',
            'permissions.view',
            'permissions.update',
            'permissions.delete',
            'assignments.manage',
        ];

        $projectActivityPermissions = [
            'project-activities.view',
            'project-activities.manage',
        ];

        $attendancePermissions = [
            'attendance.view',
            'attendance.manage',
        ];

        $businessDevelopmentWorkflowPermissions = [
            'business-development.adjudications.score',
        ];

        $citizenAccessOfferingPermissions = [
            'citizen-access.offerings.view',
            'citizen-access.offerings.create',
            'citizen-access.offerings.update',
            'citizen-access.offerings.publish',
            'citizen-access.offerings.archive',
            'citizen-access.offerings.delete',
        ];

        $technicalTicketPermissions = [
            'technical-tickets.respond',
        ];

        $travelClaimPermissions = [
            'travel-claims.submit',
        ];

        $marketingWorkflowPermissions = [
            'marketing.requests.create',
            'marketing.deliverables.assign',
            'marketing.deliverables.approve',
            'marketing.publications.manage',
            'marketing.metrics.import',
            'marketing.assets.archive',
            'marketing.dashboard.performance.view',
        ];

        $programmeOfActionPermissions = [
            'governance.approve',
            'compliance.calendar.manage',
            'compliance.submissions.manage',
            'funding.reports.generate',
            'volunteers.certificates.issue',
            'monitoring-evaluation.reports.generate',
        ];

        $allPermissions = array_values(array_unique([
            ...$allDomainPermissions,
            ...$accessControlPermissions,
            ...$projectActivityPermissions,
            ...$attendancePermissions,
            ...$businessDevelopmentWorkflowPermissions,
            ...$citizenAccessOfferingPermissions,
            ...$technicalTicketPermissions,
            ...$travelClaimPermissions,
            ...$marketingWorkflowPermissions,
            ...$programmeOfActionPermissions,
        ]));

        foreach ($allPermissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guard,
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => $guard,
        ]);
        $superAdmin->syncPermissions($allPermissions);

        $adminPermissions = array_values(array_diff(
            $allPermissions,
            $accessControlPermissions,
            ['domain.task-management.manage', 'technical-tickets.respond']
        ));

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);
        $admin->syncPermissions($adminPermissions);

        $viewerCrossDomain = Role::firstOrCreate([
            'name' => 'viewer-cross-domain',
            'guard_name' => $guard,
        ]);
        $viewerCrossDomain->syncPermissions(array_values(array_unique([
            ...$viewPermissions,
            'domain.settings.view',
            'domain.leave.view',
            'attendance.view',
        ])));

        $facilitatorRole = Role::firstOrCreate([
            'name' => 'facilitator',
            'guard_name' => $guard,
        ]);
        $facilitatorRole->syncPermissions(array_values(array_unique([
            ...$projectActivityPermissions,
            ...$attendancePermissions,
        ])));

        $technicalResponderRole = Role::firstOrCreate([
            'name' => 'technical-responder',
            'guard_name' => $guard,
        ]);
        $technicalResponderRole->syncPermissions([
            'domain.task-management.view',
            'technical-tickets.respond',
        ]);

        Role::firstOrCreate([
            'name' => 'marketing_staff',
            'guard_name' => $guard,
        ])->syncPermissions([
            'domain.marketing.view',
        ]);

        Role::firstOrCreate([
            'name' => 'graphics_staff',
            'guard_name' => $guard,
        ])->syncPermissions([
            'domain.marketing.view',
        ]);

        Role::firstOrCreate([
            'name' => 'marketing_manager',
            'guard_name' => $guard,
        ])->syncPermissions([
            'domain.marketing.view',
            'domain.marketing.manage',
            'marketing.requests.create',
            'marketing.deliverables.assign',
            'marketing.deliverables.approve',
            'marketing.publications.manage',
            'marketing.metrics.import',
            'marketing.assets.archive',
            'marketing.dashboard.performance.view',
        ]);

        Role::firstOrCreate([
            'name' => 'communications_manager',
            'guard_name' => $guard,
        ])->syncPermissions([
            'domain.marketing.view',
            'domain.marketing.manage',
            'marketing.requests.create',
            'marketing.deliverables.assign',
            'marketing.deliverables.approve',
            'marketing.publications.manage',
            'marketing.metrics.import',
            'marketing.assets.archive',
            'marketing.dashboard.performance.view',
        ]);

        Role::firstOrCreate([
            'name' => 'executive_approver',
            'guard_name' => $guard,
        ])->syncPermissions([
            'domain.marketing.view',
            'domain.marketing.manage',
            'marketing.deliverables.approve',
            'marketing.dashboard.performance.view',
        ]);

        $programmeOfActionRoles = [
            'super-administrator' => $allPermissions,
            'executive-director' => [
                'domain.organization.view',
                'domain.organization.manage',
                'domain.governance.view',
                'domain.governance.manage',
                'domain.compliance.view',
                'domain.compliance.manage',
                'domain.funding.view',
                'domain.funding.manage',
                'domain.reporting.view',
                'domain.reporting.manage',
                'domain.projects.view',
                'domain.programs.view',
                'governance.approve',
                'compliance.calendar.manage',
                'funding.reports.generate',
                'monitoring-evaluation.reports.generate',
            ],
            'board-chairperson' => [
                'domain.organization.view',
                'domain.governance.view',
                'domain.governance.manage',
                'domain.committees.view',
                'domain.meetings.view',
                'domain.resolutions.view',
                'domain.policies.view',
                'domain.compliance.view',
                'domain.reporting.view',
                'governance.approve',
            ],
            'board-member' => [
                'domain.organization.view',
                'domain.governance.view',
                'domain.committees.view',
                'domain.meetings.view',
                'domain.resolutions.view',
                'domain.policies.view',
                'domain.reporting.view',
            ],
            'programme-manager' => [
                'domain.programs.view',
                'domain.programs.manage',
                'domain.projects.view',
                'domain.projects.manage',
                'domain.beneficiaries.view',
                'domain.beneficiaries.manage',
                'domain.citizen-access.view',
                'domain.citizen-access.manage',
                ...$citizenAccessOfferingPermissions,
                'domain.volunteers.view',
                'domain.volunteers.manage',
                'domain.monitoring-evaluation.view',
                'domain.monitoring-evaluation.manage',
                'domain.reporting.view',
                'monitoring-evaluation.reports.generate',
            ],
            'project-manager' => [
                'domain.projects.view',
                'domain.projects.manage',
                'domain.beneficiaries.view',
                'domain.beneficiaries.manage',
                'domain.volunteers.view',
                'domain.reporting.view',
            ],
            'compliance-officer' => [
                'domain.organization.view',
                'domain.compliance.view',
                'domain.compliance.manage',
                'domain.public-benefit-organisation.view',
                'domain.public-benefit-organisation.manage',
                'domain.reporting.view',
                'compliance.calendar.manage',
                'compliance.submissions.manage',
            ],
            'finance-officer' => [
                'domain.finance.view',
                'domain.finance.manage',
                'domain.funding.view',
                'domain.funding.manage',
                'domain.donors.view',
                'domain.grants.view',
                'domain.reporting.view',
                'funding.reports.generate',
            ],
            'volunteer-coordinator' => [
                'domain.volunteers.view',
                'domain.volunteers.manage',
                'domain.projects.view',
                'domain.programs.view',
                'domain.reporting.view',
                'volunteers.certificates.issue',
            ],
            'monitoring-officer' => [
                'domain.monitoring-evaluation.view',
                'domain.monitoring-evaluation.manage',
                'domain.reporting.view',
                'domain.beneficiaries.view',
                'domain.projects.view',
                'domain.programs.view',
                'monitoring-evaluation.reports.generate',
            ],
        ];

        foreach ($programmeOfActionRoles as $roleName => $permissions) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guard,
            ])->syncPermissions(array_values(array_unique(array_intersect($allPermissions, $permissions))));
        }

        $departmentMap = config('access_control.department_domain_map', []);

        $departments = StaffDepartment::query()->orderBy('name')->get();
        foreach ($departments as $department) {
            $departmentName = strtolower((string) $department->name);
            $departmentSlug = Str::slug($department->name);
            $mappedDomains = $departmentMap[$departmentName] ?? $departmentMap['default'] ?? [];

            $domainAdminPermissions = [];
            $departmentManagerPermissions = [];
            $departmentUserPermissions = [];

            foreach ($mappedDomains as $domain) {
                $domainAdminPermissions[] = "domain.{$domain}.view";
                $domainAdminPermissions[] = "domain.{$domain}.manage";
                $departmentManagerPermissions[] = "domain.{$domain}.view";
                $departmentManagerPermissions[] = "domain.{$domain}.manage";
                $departmentUserPermissions[] = "domain.{$domain}.view";
            }

            if (in_array('business-development', $mappedDomains, true)) {
                $domainAdminPermissions[] = 'business-development.adjudications.score';
                $departmentManagerPermissions[] = 'business-development.adjudications.score';
            }

            if ($departmentName === 'technical') {
                $domainAdminPermissions[] = 'technical-tickets.respond';
                $departmentManagerPermissions[] = 'technical-tickets.respond';
                $departmentUserPermissions[] = 'technical-tickets.respond';
            }

            $departmentManagerPermissions[] = 'domain.leave.view';
            $departmentManagerPermissions[] = 'domain.leave.manage';
            $departmentManagerPermissions[] = 'domain.human-resources.view';
            $departmentManagerPermissions[] = 'domain.settings.view';
            $departmentManagerPermissions[] = 'domain.staff.view';
            $departmentManagerPermissions[] = 'attendance.view';
            $departmentManagerPermissions[] = 'attendance.manage';
            $departmentManagerPermissions[] = 'travel-claims.submit';

            $departmentUserPermissions[] = 'domain.leave.view';
            $departmentUserPermissions[] = 'domain.settings.view';
            $departmentUserPermissions[] = 'attendance.view';

            $domainAdminRole = Role::firstOrCreate([
                'name' => "domain-admin-{$departmentSlug}",
                'guard_name' => $guard,
            ]);
            $domainAdminRole->syncPermissions(array_values(array_unique($domainAdminPermissions)));

            $departmentManagerRole = Role::firstOrCreate([
                'name' => "department-manager-{$departmentSlug}",
                'guard_name' => $guard,
            ]);
            $departmentManagerRole->syncPermissions(array_values(array_unique($departmentManagerPermissions)));

            $departmentUserRole = Role::firstOrCreate([
                'name' => "department-user-{$departmentSlug}",
                'guard_name' => $guard,
            ]);
            $departmentUserRole->syncPermissions(array_values(array_unique($departmentUserPermissions)));
        }

        $facilitatorUserIds = Facilitator::query()
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (! empty($facilitatorUserIds)) {
            User::query()
                ->whereIn('id', $facilitatorUserIds)
                ->get()
                ->each(function (User $user): void {
                    if (! $user->hasRole('facilitator')) {
                        $user->assignRole('facilitator');
                    }
                });
        }

        $legacyFacilitatorEmails = Facilitator::query()
            ->whereNull('user_id')
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->values()
            ->all();

        $users = User::query()->with(['staffMember.department', 'staffMember.directReports'])->get();
        foreach ($users as $user) {
            if ($user->roles()->exists()) {
                continue;
            }

            $staff = $user->staffMember;
            if (! $staff) {
                $isFacilitatorUser = in_array(
                    strtolower(trim((string) $user->email)),
                    $legacyFacilitatorEmails,
                    true
                );

                if ($isFacilitatorUser) {
                    $user->syncRoles(['facilitator']);

                    continue;
                }

                $user->syncRoles(['viewer-cross-domain']);

                continue;
            }

            if ((bool) $staff->is_ceo) {
                $user->syncRoles(['super-admin']);

                continue;
            }

            $departmentSlug = $staff->department ? Str::slug($staff->department->name) : null;
            $hasReports = $staff->directReports()->exists();

            $rolesToAssign = [];
            if ($departmentSlug && $hasReports) {
                $rolesToAssign[] = "department-manager-{$departmentSlug}";
            } elseif ($departmentSlug) {
                $rolesToAssign[] = "department-user-{$departmentSlug}";
            } else {
                $rolesToAssign[] = 'viewer-cross-domain';
            }

            if ((bool) $staff->is_board_member) {
                $rolesToAssign[] = 'viewer-cross-domain';
            }

            $user->syncRoles(array_values(array_unique($rolesToAssign)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
