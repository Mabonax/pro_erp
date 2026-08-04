import { Head, Link, router } from '@inertiajs/react';

import {
    ComparisonBarsChart,
    HorizontalBarChart,
    LineTrendChart,
    StackedCompositionChart,
} from '@/components/charts/dashboard-charts';
import { DomainNav } from '@/components/domain-nav';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { projectNavItems } from '@/config/domain-nav/projects';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Projects', href: '/projects' },
    { title: 'Project View', href: '#' },
];

const readinessTone = (ready: boolean) =>
    ready
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : 'border-amber-200 bg-amber-50 text-amber-700';

export default function ProjectShow({
    project,
    milestones,
    progress,
    locations,
    beneficiaryJourney,
    attendanceTrend,
    history,
    canManageProjects,
    finalization,
}: {
    project: any;
    milestones: any[];
    progress: any;
    locations: any[];
    beneficiaryJourney: any;
    attendanceTrend: { date: string; attendance_rate: number }[];
    history: any[];
    canManageProjects: boolean;
    finalization: {
        href: string;
        is_concluded: boolean;
        closure_date: string | null;
        evidence_count: number;
        report_count: number;
        can_manage: boolean;
    };
}) {
    const projectData = project?.data ?? project;
    const statusSummary = projectData.status_summary;
    const summary = progress?.summary ?? {};
    const journeySummary = beneficiaryJourney?.summary ?? {};
    const journeyLocations = beneficiaryJourney?.locations ?? [];

    const handleSyncMilestones = (e: React.FormEvent) => {
        e.preventDefault();

        router.post(`/projects/${projectData.id}/milestones/sync`, {});
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project View" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-xl font-semibold">
                            {projectData.name}
                        </h1>
                        {canManageProjects ? (
                            <div className="flex flex-wrap gap-2">
                                <Link
                                    href={finalization.href}
                                    className="rounded-md border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
                                >
                                    Project Finalization
                                </Link>
                                <Link
                                    href={`/projects/${projectData.id}/edit`}
                                    className="rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                                >
                                    Edit Project
                                </Link>
                            </div>
                        ) : finalization.can_manage ? (
                            <Link
                                href={finalization.href}
                                className="rounded-md border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
                            >
                                Project Finalization
                            </Link>
                        ) : null}
                    </div>
                    <DomainNav items={projectNavItems} />
                </div>

                <div className="flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2">
                    <Link
                        href={`/projects/${projectData.id}`}
                        className="rounded-md bg-white px-4 py-2 text-sm font-medium text-slate-900 shadow-sm"
                    >
                        Overview
                    </Link>
                    <Link
                        href={finalization.href}
                        className="rounded-md px-4 py-2 text-sm font-medium text-slate-600 hover:bg-white hover:text-slate-900"
                    >
                        Finalization
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                            <CardDescription>Current</CardDescription>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {projectData.status_label ??
                                projectData.status ??
                                '-'}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Start Date</CardTitle>
                            <CardDescription>Project start</CardDescription>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {projectData.start_date ?? '-'}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Locations</CardTitle>
                            <CardDescription>Delivery sites</CardDescription>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {summary.total_locations ?? locations.length}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Milestones</CardTitle>
                            <CardDescription>Delivery units</CardDescription>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {summary.total_milestones ?? milestones.length}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Active Beneficiaries</CardTitle>
                            <CardDescription>In delivery</CardDescription>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {summary.active_beneficiaries ?? 0}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>Milestone Delivery</CardTitle>
                            <CardDescription>
                                Completed assessments
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {summary.milestone_completion_rate ?? 0}%
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Beneficiary Completion</CardTitle>
                            <CardDescription>
                                Completed all milestones
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {summary.beneficiary_completion_rate ?? 0}%
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Attendance Health</CardTitle>
                            <CardDescription>
                                Captured attendance
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {summary.attendance_rate ?? 0}%
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Blocked Sites</CardTitle>
                            <CardDescription>Need intervention</CardDescription>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {summary.blocked_locations ?? 0}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Project Manager</CardTitle>
                            <CardDescription>Assigned lead</CardDescription>
                        </CardHeader>
                        <CardContent className="text-base font-semibold">
                            {summary.project_manager_name ??
                                projectData.project_manager_name ??
                                '-'}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.55fr,1fr]">
                    <ComparisonBarsChart
                        title="Location Delivery Comparison"
                        description="Compares milestone delivery, beneficiary completion, and attendance health across project sites."
                        rows={locations}
                        rowLabel={(location) =>
                            location.location ?? 'Unnamed location'
                        }
                        metrics={[
                            {
                                label: 'Milestones',
                                colorClass: 'bg-red-500',
                                value: (location) =>
                                    location.milestone_completion_rate ?? 0,
                            },
                            {
                                label: 'Completion',
                                colorClass: 'bg-amber-500',
                                value: (location) =>
                                    location.beneficiary_completion_rate ?? 0,
                            },
                            {
                                label: 'Attendance',
                                colorClass: 'bg-sky-500',
                                value: (location) =>
                                    location.attendance_rate ?? 0,
                            },
                        ]}
                        emptyMessage="No project locations are available yet."
                        maxRows={10}
                    />

                    <StackedCompositionChart
                        title="Beneficiary Movement"
                        description="Shows how the tracked beneficiary population is split between active delivery, completed beneficiaries, and dropped beneficiaries."
                        segments={[
                            {
                                label: 'Active',
                                value: summary.active_beneficiaries ?? 0,
                                colorClass: 'bg-sky-500',
                            },
                            {
                                label: 'Completed',
                                value: summary.completed_beneficiaries ?? 0,
                                colorClass: 'bg-emerald-500',
                            },
                            {
                                label: 'Dropped',
                                value: summary.dropped_beneficiaries ?? 0,
                                colorClass: 'bg-amber-500',
                            },
                        ]}
                        emptyMessage="No beneficiary movement data is available yet."
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.25fr,1fr]">
                    <LineTrendChart
                        title="Attendance Trend"
                        description="Attendance rate by register date across the project, using captured attendance entries only."
                        points={attendanceTrend.map((point) => ({
                            label: point.date.slice(5),
                            value: point.attendance_rate,
                        }))}
                        colorClass="bg-sky-500"
                        emptyMessage="No attendance history has been captured for this project yet."
                    />

                    <HorizontalBarChart
                        title="Completed Assessments by Location"
                        description="Highlights which delivery sites have progressed furthest through expected assessments."
                        items={locations
                            .map((location) => ({
                                label: location.location ?? 'Unnamed location',
                                value:
                                    location.expected_assessments > 0
                                        ? Math.round(
                                              ((location.completed_assessments ??
                                                  0) /
                                                  location.expected_assessments) *
                                                  100,
                                          )
                                        : 0,
                                hint: `${location.completed_assessments ?? 0}/${location.expected_assessments ?? 0} assessments`,
                                colorClass: location.is_blocked
                                    ? 'bg-amber-500'
                                    : 'bg-emerald-500',
                            }))
                            .sort((a, b) => b.value - a.value)}
                        emptyMessage="No assessment data is available for project locations yet."
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Beneficiary Journey Rollup</CardTitle>
                        <CardDescription>
                            Cross-domain view of support cases, evidence gaps,
                            readiness actions, milestone progress, and
                            attendance risk by location.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="rounded-md border p-3">
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Open cases
                                </div>
                                <div className="mt-1 text-2xl font-semibold">
                                    {journeySummary.open_support_cases ?? 0}
                                </div>
                            </div>
                            <div className="rounded-md border p-3">
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Evidence gaps
                                </div>
                                <div className="mt-1 text-2xl font-semibold">
                                    {journeySummary.evidence_gaps ?? 0}
                                </div>
                            </div>
                            <div className="rounded-md border p-3">
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Readiness actions
                                </div>
                                <div className="mt-1 text-2xl font-semibold">
                                    {journeySummary.open_readiness_actions ?? 0}
                                </div>
                            </div>
                            <div className="rounded-md border p-3">
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Locations with risk
                                </div>
                                <div className="mt-1 text-2xl font-semibold">
                                    {journeySummary.locations_with_risks ?? 0}
                                </div>
                            </div>
                        </div>

                        {journeyLocations.length === 0 ? (
                            <p className="rounded-md border p-3 text-sm text-muted-foreground">
                                No beneficiary journey data is available for
                                this project yet.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {journeyLocations.map((location: any) => (
                                    <div
                                        key={location.location_id}
                                        className="rounded-md border p-4"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div className="font-semibold">
                                                    {location.location ??
                                                        'Unnamed location'}
                                                </div>
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {location.active_beneficiaries ??
                                                        0}{' '}
                                                    active beneficiaries |
                                                    Attendance{' '}
                                                    {location.attendance_rate ??
                                                        0}
                                                    %
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-3 gap-2 text-center text-xs">
                                                <div className="rounded-md border px-2 py-1.5">
                                                    <div className="font-semibold">
                                                        {location.open_support_cases ??
                                                            0}
                                                    </div>
                                                    <div className="text-muted-foreground">
                                                        Cases
                                                    </div>
                                                </div>
                                                <div className="rounded-md border px-2 py-1.5">
                                                    <div className="font-semibold">
                                                        {location.evidence_gaps ??
                                                            0}
                                                    </div>
                                                    <div className="text-muted-foreground">
                                                        Gaps
                                                    </div>
                                                </div>
                                                <div className="rounded-md border px-2 py-1.5">
                                                    <div className="font-semibold">
                                                        {location.open_readiness_actions ??
                                                            0}
                                                    </div>
                                                    <div className="text-muted-foreground">
                                                        Actions
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {location.at_risk_beneficiaries
                                            ?.length ? (
                                            <div className="mt-4 overflow-x-auto">
                                                <table className="min-w-full text-sm">
                                                    <thead>
                                                        <tr className="border-b text-left text-xs text-muted-foreground">
                                                            <th className="px-3 py-2 font-medium">
                                                                Beneficiary
                                                            </th>
                                                            <th className="px-3 py-2 font-medium">
                                                                Cases
                                                            </th>
                                                            <th className="px-3 py-2 font-medium">
                                                                Evidence
                                                            </th>
                                                            <th className="px-3 py-2 font-medium">
                                                                Actions
                                                            </th>
                                                            <th className="px-3 py-2 font-medium">
                                                                Milestones
                                                            </th>
                                                            <th className="px-3 py-2 font-medium">
                                                                Attendance
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {location.at_risk_beneficiaries.map(
                                                            (
                                                                beneficiary: any,
                                                            ) => (
                                                                <tr
                                                                    key={
                                                                        beneficiary.beneficiary_id
                                                                    }
                                                                    className="border-b"
                                                                >
                                                                    <td className="px-3 py-2">
                                                                        <Link
                                                                            href={`/beneficiaries/${beneficiary.beneficiary_id}`}
                                                                            className="font-medium text-red-600 hover:underline"
                                                                        >
                                                                            {
                                                                                beneficiary.beneficiary_name
                                                                            }
                                                                        </Link>
                                                                        {beneficiary
                                                                            .missing_evidence
                                                                            ?.length ? (
                                                                            <div className="mt-1 text-xs text-muted-foreground">
                                                                                Missing:{' '}
                                                                                {beneficiary.missing_evidence.join(
                                                                                    ', ',
                                                                                )}
                                                                            </div>
                                                                        ) : null}
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        {beneficiary.open_support_cases ??
                                                                            0}
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        {beneficiary.evidence_count ??
                                                                            0}{' '}
                                                                        files /{' '}
                                                                        {beneficiary.evidence_gap_count ??
                                                                            0}{' '}
                                                                        gaps
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        {beneficiary.open_readiness_actions ??
                                                                            0}
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        {beneficiary.completed_milestone_assessments ??
                                                                            0}
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        {beneficiary.attendance_rate ??
                                                                            0}
                                                                        %
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <p className="mt-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                                                No beneficiary-level journey
                                                risks are currently flagged for
                                                this location.
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Commercial Structure</CardTitle>
                            <CardDescription>
                                Sponsor and implementation partners
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <div>
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Sponsor
                                </div>
                                <div className="mt-1 font-medium text-slate-900">
                                    {projectData.sponsor_name ??
                                        'No sponsor assigned'}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Implementation partners
                                </div>
                                {projectData.partner_names?.length ? (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {projectData.partner_names.map(
                                            (partnerName: string) => (
                                                <span
                                                    key={partnerName}
                                                    className="rounded-full border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700"
                                                >
                                                    {partnerName}
                                                </span>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="mt-1 text-muted-foreground">
                                        No implementation partners assigned.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Governance Metadata</CardTitle>
                            <CardDescription>
                                Funding and reporting obligations
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Contract Reference
                                </div>
                                <div className="mt-1 font-medium text-slate-900">
                                    {projectData.contract_reference ??
                                        'Not recorded'}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Funding Amount
                                </div>
                                <div className="mt-1 font-medium text-slate-900">
                                    {projectData.funding_amount !== null &&
                                    projectData.funding_amount !== undefined
                                        ? Number(
                                              projectData.funding_amount,
                                          ).toLocaleString(undefined, {
                                              minimumFractionDigits: 2,
                                              maximumFractionDigits: 2,
                                          })
                                        : 'Not recorded'}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Reporting Cadence
                                </div>
                                <div className="mt-1 font-medium text-slate-900 capitalize">
                                    {projectData.reporting_cadence
                                        ? String(
                                              projectData.reporting_cadence,
                                          ).replaceAll('_', ' ')
                                        : 'Not recorded'}
                                </div>
                            </div>
                            <div className="sm:col-span-2">
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Reporting Obligations
                                </div>
                                <div className="mt-1 whitespace-pre-wrap text-slate-700">
                                    {projectData.reporting_obligations ??
                                        'No reporting obligations recorded.'}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Transition Readiness</CardTitle>
                            <CardDescription>
                                Current workflow state and blockers
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Allowed transitions
                                </div>
                                {statusSummary?.allowed_transitions?.length ? (
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {statusSummary.allowed_transitions.map(
                                            (transition: any) => (
                                                <span
                                                    key={transition.status}
                                                    className={`rounded-full border px-2.5 py-1 text-xs font-medium ${readinessTone(transition.ready)}`}
                                                >
                                                    {transition.label}
                                                    {!transition.ready
                                                        ? ` (${transition.blockers.length} blocker${transition.blockers.length === 1 ? '' : 's'})`
                                                        : ''}
                                                </span>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        No further transitions are allowed for
                                        this project.
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-3">
                                {(['active', 'completed'] as const).map(
                                    (statusKey) => {
                                        const readiness =
                                            statusSummary?.readiness?.[
                                                statusKey
                                            ];

                                        if (!readiness) return null;

                                        return (
                                            <div
                                                key={statusKey}
                                                className={`rounded-lg border p-3 ${readinessTone(readiness.ready)}`}
                                            >
                                                <div className="text-xs font-semibold tracking-wide uppercase">
                                                    {statusKey === 'active'
                                                        ? 'Activation readiness'
                                                        : 'Completion readiness'}
                                                </div>
                                                <div className="mt-1 text-sm font-medium">
                                                    {readiness.ready
                                                        ? 'Ready'
                                                        : `${readiness.blockers.length} blocker${readiness.blockers.length === 1 ? '' : 's'}`}
                                                </div>
                                                {!readiness.ready && (
                                                    <ul className="mt-2 space-y-1 text-xs">
                                                        {readiness.blockers.map(
                                                            (
                                                                blocker: string,
                                                            ) => (
                                                                <li
                                                                    key={
                                                                        blocker
                                                                    }
                                                                >
                                                                    {blocker}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                            </div>
                                        );
                                    },
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Locations</CardTitle>
                            <CardDescription>Progress by site</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {locations.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No locations added yet.
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    {locations.map((loc) => (
                                        <div
                                            key={loc.id}
                                            className="rounded-md border p-3"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-medium">
                                                        {loc.location}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Facilitator:{' '}
                                                        {loc.facilitator_name ??
                                                            '-'}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Venue:{' '}
                                                        {loc.training_venue_address ??
                                                            '-'}
                                                    </div>
                                                </div>
                                                <div
                                                    className={`rounded-full border px-2.5 py-1 text-xs font-medium ${loc.is_blocked ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`}
                                                >
                                                    {loc.is_blocked
                                                        ? 'Needs intervention'
                                                        : 'On track'}
                                                </div>
                                            </div>

                                            <div className="mt-3 grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                                                <div>
                                                    <div className="text-muted-foreground">
                                                        Active beneficiaries
                                                    </div>
                                                    <div className="font-semibold">
                                                        {loc.active_beneficiaries ??
                                                            0}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="text-muted-foreground">
                                                        Milestone delivery
                                                    </div>
                                                    <div className="font-semibold">
                                                        {loc.milestone_completion_rate ??
                                                            0}
                                                        %
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="text-muted-foreground">
                                                        Beneficiary completion
                                                    </div>
                                                    <div className="font-semibold">
                                                        {loc.beneficiary_completion_rate ??
                                                            0}
                                                        %
                                                    </div>
                                                </div>
                                                <div>
                                                    <div className="text-muted-foreground">
                                                        Attendance health
                                                    </div>
                                                    <div className="font-semibold">
                                                        {loc.attendance_rate ??
                                                            0}
                                                        %
                                                    </div>
                                                </div>
                                            </div>

                                            {loc.blockers?.length ? (
                                                <ul className="mt-3 space-y-1 text-xs text-amber-700">
                                                    {loc.blockers.map(
                                                        (blocker: string) => (
                                                            <li key={blocker}>
                                                                {blocker}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            ) : null}

                                            <div className="mt-3 text-xs text-muted-foreground">
                                                Completed assessments:{' '}
                                                {loc.completed_assessments ?? 0}
                                                /{loc.expected_assessments ?? 0}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Project Risks</CardTitle>
                            <CardDescription>
                                Current blockers and intervention points
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            {summary.blockers?.length ? (
                                <ul className="space-y-2">
                                    {summary.blockers.map((blocker: string) => (
                                        <li
                                            key={blocker}
                                            className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800"
                                        >
                                            {blocker}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800">
                                    No project-level blockers are currently
                                    flagged.
                                </div>
                            )}

                            <div className="rounded-md border p-3">
                                <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Beneficiary movement
                                </div>
                                <div className="mt-2 grid gap-2 sm:grid-cols-3">
                                    <div>
                                        <div className="text-muted-foreground">
                                            Total
                                        </div>
                                        <div className="font-semibold">
                                            {summary.total_beneficiaries ?? 0}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground">
                                            Completed
                                        </div>
                                        <div className="font-semibold">
                                            {summary.completed_beneficiaries ??
                                                0}
                                        </div>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground">
                                            Dropped
                                        </div>
                                        <div className="font-semibold">
                                            {summary.dropped_beneficiaries ?? 0}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Project Finalization</CardTitle>
                            <CardDescription>
                                Run project closure in a dedicated section, not
                                on this page
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div className="font-semibold text-slate-900">
                                    {finalization.is_concluded
                                        ? 'Project already finalized'
                                        : 'Finalization is managed separately'}
                                </div>
                                <p className="mt-2 text-slate-700">
                                    Use the dedicated finalization workspace to
                                    upload closure evidence, upload registers,
                                    generate reports, capture the closing date,
                                    and complete sign-off in order.
                                </p>
                                <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div className="rounded-md border bg-white p-3">
                                        <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            Closure status
                                        </div>
                                        <div className="mt-1 font-medium text-slate-900">
                                            {finalization.is_concluded
                                                ? `Closed ${finalization.closure_date ?? ''}`.trim()
                                                : 'Open'}
                                        </div>
                                    </div>
                                    <div className="rounded-md border bg-white p-3">
                                        <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            Evidence files
                                        </div>
                                        <div className="mt-1 font-medium text-slate-900">
                                            {finalization.evidence_count}
                                        </div>
                                    </div>
                                    <div className="rounded-md border bg-white p-3">
                                        <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            Reports
                                        </div>
                                        <div className="mt-1 font-medium text-slate-900">
                                            {finalization.report_count}
                                        </div>
                                    </div>
                                </div>
                                <Link
                                    href={finalization.href}
                                    className="mt-4 inline-flex rounded-md border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
                                >
                                    Open finalization workspace
                                </Link>
                            </div>
                            {!finalization.can_manage ? (
                                <p className="text-sm text-muted-foreground">
                                    You can view the finalization workspace, but
                                    only project managers and project
                                    administrators can update it.
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Milestone Register</CardTitle>
                            <CardDescription>
                                Attached to project
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {canManageProjects ? (
                                <form
                                    onSubmit={handleSyncMilestones}
                                    className="mb-4"
                                >
                                    <button
                                        type="submit"
                                        className="rounded-md bg-red-600 px-3 py-2 text-sm text-white hover:bg-red-700"
                                    >
                                        Attach program milestones
                                    </button>
                                </form>
                            ) : null}

                            {milestones.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No milestones attached yet.
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {milestones.map((m) => (
                                        <li
                                            key={m.id}
                                            className="flex items-center justify-between"
                                        >
                                            <span>{m.title}</span>
                                            <span className="text-muted-foreground">
                                                Max: {m.max_score ?? '-'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Project History</CardTitle>
                            <CardDescription>
                                Audit trail for governance and delivery actions
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {history.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No project history has been recorded yet.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {history.map((item) => (
                                        <div
                                            key={item.id}
                                            className="rounded-lg border p-3"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-medium text-slate-900">
                                                        {item.summary}
                                                    </div>
                                                    <div className="text-xs tracking-wide text-muted-foreground uppercase">
                                                        {String(
                                                            item.action,
                                                        ).replaceAll('_', ' ')}
                                                    </div>
                                                </div>
                                                <div className="text-right text-xs text-muted-foreground">
                                                    <div>
                                                        {item.actor_name ??
                                                            'System'}
                                                    </div>
                                                    <div>
                                                        {item.created_at ?? '-'}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
