import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

import { ConfirmDeleteModal } from '@/components/confirm-delete-modal';
import AppLayout from '@/layouts/app-layout';
import beneficiaries from '@/routes/beneficiaries';
import { type BreadcrumbItem } from '@/types';

function formatStatus(value?: string | null) {
    return value ? value.replace(/_/g, ' ') : '-';
}

export default function BeneficiaryShow({
    beneficiary,
    canManageBeneficiary,
}: {
    beneficiary: any;
    canManageBeneficiary: boolean;
}) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const evidenceForm = useForm({
        evidence_type: '',
        title: '',
        description: '',
        issuer: '',
        issue_date: '',
        expiry_date: '',
        verification_status: 'pending',
        sensitivity_classification: 'personal',
        file: null as File | null,
    });

    function submitEvidence(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        evidenceForm.post(`/beneficiaries/${beneficiary.id}/evidence`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => evidenceForm.reset(),
        });
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Beneficiaries', href: beneficiaries.index() },
        {
            title: beneficiary.full_name ?? 'Beneficiary File',
            href: `/beneficiaries/${beneficiary.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={beneficiary.full_name ?? 'Beneficiary File'} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-1">
                        <div className="text-sm text-muted-foreground">
                            <Link
                                href={beneficiaries.index().url}
                                className="hover:underline"
                            >
                                Back to beneficiaries
                            </Link>
                        </div>
                        <h1 className="text-2xl font-semibold">
                            {beneficiary.full_name ?? '-'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {beneficiary.program_title ?? 'No current program'}{' '}
                            | {beneficiary.project_name ?? 'No current project'}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {canManageBeneficiary ? (
                            <Link
                                href={beneficiaries.edit(beneficiary.id).url}
                                className="rounded-md border border-red-500 px-4 py-2 text-sm text-red-600 hover:bg-red-500 hover:text-white"
                            >
                                Edit Beneficiary
                            </Link>
                        ) : null}
                        {canManageBeneficiary ? (
                            <button
                                type="button"
                                onClick={() => setDeleteOpen(true)}
                                className="rounded-md border border-red-600 px-4 py-2 text-sm text-red-600 hover:bg-red-600 hover:text-white"
                            >
                                Delete Beneficiary
                            </button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <section className="rounded-xl border bg-card p-4 shadow-sm">
                        <div className="text-sm text-muted-foreground">
                            Current Program
                        </div>
                        <div className="mt-1 text-xl font-semibold">
                            {beneficiary.current_participation?.program_title ??
                                '-'}
                        </div>
                    </section>
                    <section className="rounded-xl border bg-card p-4 shadow-sm">
                        <div className="text-sm text-muted-foreground">
                            Current Project
                        </div>
                        <div className="mt-1 text-xl font-semibold">
                            {beneficiary.current_participation?.project_name ??
                                '-'}
                        </div>
                    </section>
                    <section className="rounded-xl border bg-card p-4 shadow-sm">
                        <div className="text-sm text-muted-foreground">
                            Current Site
                        </div>
                        <div className="mt-1 text-xl font-semibold">
                            {beneficiary.current_participation?.location_name ??
                                '-'}
                        </div>
                    </section>
                    <section className="rounded-xl border bg-card p-4 shadow-sm">
                        <div className="text-sm text-muted-foreground">
                            Attendance Status
                        </div>
                        <div className="mt-1 text-xl font-semibold capitalize">
                            {beneficiary.attendance_status ?? '-'}
                        </div>
                    </section>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <section className="rounded-xl border bg-card p-4 shadow-sm lg:col-span-2">
                        <h2 className="text-base font-semibold">
                            Beneficiary Profile
                        </h2>
                        <dl className="mt-3 grid gap-3 text-sm md:grid-cols-2">
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Full Name
                                </dt>
                                <dd>{beneficiary.full_name ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Date of Birth
                                </dt>
                                <dd>{beneficiary.dob ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Age</dt>
                                <dd>{beneficiary.age ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Gender
                                </dt>
                                <dd>{beneficiary.gender ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    ID Number
                                </dt>
                                <dd>{beneficiary.id_number ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Qualification
                                </dt>
                                <dd>
                                    {beneficiary.highest_qualification ?? '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Email</dt>
                                <dd>{beneficiary.email ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Phone</dt>
                                <dd>{beneficiary.phone ?? '-'}</dd>
                            </div>
                        </dl>
                    </section>

                    <section className="rounded-xl border bg-card p-4 shadow-sm">
                        <h2 className="text-base font-semibold">
                            Current Placement
                        </h2>
                        <dl className="mt-3 space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Program
                                </dt>
                                <dd>
                                    {beneficiary.current_participation
                                        ?.program_title ?? '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Project
                                </dt>
                                <dd>
                                    {beneficiary.current_participation
                                        ?.project_name ?? '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Location
                                </dt>
                                <dd>
                                    {beneficiary.current_participation
                                        ?.location_name ?? '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Enrollment Status
                                </dt>
                                <dd className="capitalize">
                                    {beneficiary.current_participation
                                        ?.status ?? '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Enrolled At
                                </dt>
                                <dd>
                                    {beneficiary.current_participation
                                        ?.enrolled_at ?? '-'}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-xl border bg-card p-4 shadow-sm">
                        <h2 className="text-base font-semibold">
                            Address and Contact
                        </h2>
                        <dl className="mt-3 space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Street Address
                                </dt>
                                <dd>{beneficiary.street_address ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Address Line 2
                                </dt>
                                <dd>{beneficiary.address_line_2 ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">City</dt>
                                <dd>{beneficiary.city ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Postal Code
                                </dt>
                                <dd>{beneficiary.postal_code ?? '-'}</dd>
                            </div>
                        </dl>
                    </section>

                    <section className="rounded-xl border bg-card p-4 shadow-sm">
                        <h2 className="text-base font-semibold">Next of Kin</h2>
                        <dl className="mt-3 space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Full Name
                                </dt>
                                <dd>
                                    {beneficiary.next_of_kin
                                        ? `${beneficiary.next_of_kin.name ?? ''} ${beneficiary.next_of_kin.surname ?? ''}`.trim()
                                        : '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Relationship
                                </dt>
                                <dd>
                                    {beneficiary.next_of_kin?.relationship ??
                                        '-'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Phone</dt>
                                <dd>{beneficiary.next_of_kin?.phone ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Email</dt>
                                <dd>{beneficiary.next_of_kin?.email ?? '-'}</dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <section className="rounded-xl border bg-card p-4 shadow-sm">
                    <h2 className="text-base font-semibold">
                        Participation History
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Historical participation across programs, project
                        iterations, and delivery sites.
                    </p>

                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="px-3 py-2 text-left">
                                        Program
                                    </th>
                                    <th className="px-3 py-2 text-left">
                                        Project
                                    </th>
                                    <th className="px-3 py-2 text-left">
                                        Location
                                    </th>
                                    <th className="px-3 py-2 text-left">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 text-left">
                                        Project Window
                                    </th>
                                    <th className="px-3 py-2 text-left">
                                        Enrolled At
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {(beneficiary.participation_history ?? []).map(
                                    (entry: any) => (
                                        <tr key={entry.id} className="border-b">
                                            <td className="px-3 py-2">
                                                {entry.program_title ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {entry.project_name ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {entry.location_name ?? '-'}
                                            </td>
                                            <td className="px-3 py-2 capitalize">
                                                {entry.status ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {entry.project_start_date ??
                                                    '-'}{' '}
                                                to{' '}
                                                {entry.project_end_date ??
                                                    'ongoing'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {entry.enrolled_at ?? '-'}
                                            </td>
                                        </tr>
                                    ),
                                )}
                                {(beneficiary.participation_history ?? [])
                                    .length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-3 py-3 text-muted-foreground"
                                        >
                                            No participation history recorded
                                            yet.
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-xl border bg-card p-4 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-base font-semibold">
                                Service Journey
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Cross-domain view of support cases, evidence,
                                and delivery outcomes attached to this
                                beneficiary.
                            </p>
                        </div>
                        <div className="grid grid-cols-3 gap-2 text-center text-xs">
                            <div className="rounded-md border px-3 py-2">
                                <div className="text-lg font-semibold">
                                    {beneficiary.service_journey_summary
                                        ?.open_support_case_count ?? 0}
                                </div>
                                <div className="text-muted-foreground">
                                    Open cases
                                </div>
                            </div>
                            <div className="rounded-md border px-3 py-2">
                                <div className="text-lg font-semibold">
                                    {beneficiary.service_journey_summary
                                        ?.evidence_item_count ?? 0}
                                </div>
                                <div className="text-muted-foreground">
                                    Evidence
                                </div>
                            </div>
                            <div className="rounded-md border px-3 py-2">
                                <div className="text-lg font-semibold">
                                    {beneficiary.service_journey_summary
                                        ?.completed_milestone_assessment_count ??
                                        0}
                                </div>
                                <div className="text-muted-foreground">
                                    Milestones
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 grid gap-4 xl:grid-cols-3">
                        <div className="rounded-lg border p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="text-sm font-semibold">
                                    Citizen Access Cases
                                </h3>
                                {canManageBeneficiary ? (
                                    <Link
                                        href={`/citizen-access/cases/create?beneficiary_id=${beneficiary.id}`}
                                        className="text-xs font-medium text-red-600 hover:underline"
                                    >
                                        Add case
                                    </Link>
                                ) : null}
                            </div>
                            <div className="mt-3 space-y-3">
                                {(beneficiary.support_cases ?? []).map(
                                    (caseRecord: any) => (
                                        <Link
                                            key={caseRecord.id}
                                            href={`/citizen-access/cases/${caseRecord.id}`}
                                            className="block rounded-md border px-3 py-2 hover:border-red-300"
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="font-medium">
                                                    {caseRecord.case_reference}
                                                </div>
                                                <div className="text-xs text-muted-foreground capitalize">
                                                    {formatStatus(
                                                        caseRecord.stage,
                                                    )}
                                                </div>
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {caseRecord.service_stream ??
                                                    'No stream'}{' '}
                                                | Readiness{' '}
                                                {caseRecord.readiness_percentage ??
                                                    0}
                                                %
                                            </div>
                                        </Link>
                                    ),
                                )}
                                {(beneficiary.support_cases ?? []).length ===
                                0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No Citizen Access case is linked yet.
                                        Create one when the beneficiary needs
                                        application, referral, or readiness
                                        support.
                                    </p>
                                ) : null}
                            </div>
                        </div>

                        <div className="rounded-lg border p-4">
                            <h3 className="text-sm font-semibold">
                                Milestone Outcomes
                            </h3>
                            <div className="mt-3 space-y-3">
                                {(beneficiary.milestone_assessments ?? []).map(
                                    (assessment: any) => (
                                        <div
                                            key={assessment.id}
                                            className="rounded-md border px-3 py-2"
                                        >
                                            <div className="font-medium">
                                                {assessment.milestone ??
                                                    'Milestone assessment'}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {assessment.project_name ??
                                                    'No project'}{' '}
                                                |{' '}
                                                {formatStatus(
                                                    assessment.status,
                                                )}
                                                {assessment.score !== null &&
                                                assessment.score !== undefined
                                                    ? ` | Score ${assessment.score}`
                                                    : ''}
                                            </div>
                                        </div>
                                    ),
                                )}
                                {(beneficiary.milestone_assessments ?? [])
                                    .length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No milestone assessments are recorded
                                        yet. Delivery outcomes will appear here
                                        after facilitator or project-location
                                        assessments are captured.
                                    </p>
                                ) : null}
                            </div>
                        </div>

                        <div className="rounded-lg border p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="text-sm font-semibold">
                                    Evidence Readiness
                                </h3>
                                <Link
                                    href={`/organization/document-library?owner_type=beneficiary&owner_id=${beneficiary.id}`}
                                    className="text-xs font-medium text-red-600 hover:underline"
                                >
                                    Open files
                                </Link>
                            </div>
                            {canManageBeneficiary ? (
                                <form
                                    onSubmit={submitEvidence}
                                    className="mt-3 space-y-3 rounded-md border bg-muted/20 p-3"
                                >
                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                        <label className="space-y-1 text-xs font-medium">
                                            <span>Evidence type</span>
                                            <select
                                                value={
                                                    evidenceForm.data
                                                        .evidence_type
                                                }
                                                onChange={(event) =>
                                                    evidenceForm.setData(
                                                        'evidence_type',
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            >
                                                <option value="">
                                                    Select evidence
                                                </option>
                                                <option value="identity_document">
                                                    Identity document
                                                </option>
                                                <option value="proof_of_residence">
                                                    Proof of residence
                                                </option>
                                                <option value="qualification_record">
                                                    Qualification record
                                                </option>
                                                <option value="application_confirmation">
                                                    Application confirmation
                                                </option>
                                                <option value="outcome_letter">
                                                    Outcome letter
                                                </option>
                                                <option value="other">
                                                    Other
                                                </option>
                                            </select>
                                            {evidenceForm.errors
                                                .evidence_type ? (
                                                <span className="text-red-600">
                                                    {
                                                        evidenceForm.errors
                                                            .evidence_type
                                                    }
                                                </span>
                                            ) : null}
                                        </label>

                                        <label className="space-y-1 text-xs font-medium">
                                            <span>Title</span>
                                            <input
                                                type="text"
                                                value={evidenceForm.data.title}
                                                onChange={(event) =>
                                                    evidenceForm.setData(
                                                        'title',
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                                placeholder="Certified ID copy"
                                            />
                                            {evidenceForm.errors.title ? (
                                                <span className="text-red-600">
                                                    {evidenceForm.errors.title}
                                                </span>
                                            ) : null}
                                        </label>
                                    </div>

                                    <label className="space-y-1 text-xs font-medium">
                                        <span>File</span>
                                        <input
                                            type="file"
                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp"
                                            onChange={(event) =>
                                                evidenceForm.setData(
                                                    'file',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                        />
                                        {evidenceForm.errors.file ? (
                                            <span className="text-red-600">
                                                {evidenceForm.errors.file}
                                            </span>
                                        ) : null}
                                    </label>

                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                        <label className="space-y-1 text-xs font-medium">
                                            <span>Issuer</span>
                                            <input
                                                type="text"
                                                value={evidenceForm.data.issuer}
                                                onChange={(event) =>
                                                    evidenceForm.setData(
                                                        'issuer',
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            />
                                        </label>

                                        <label className="space-y-1 text-xs font-medium">
                                            <span>Status</span>
                                            <select
                                                value={
                                                    evidenceForm.data
                                                        .verification_status
                                                }
                                                onChange={(event) =>
                                                    evidenceForm.setData(
                                                        'verification_status',
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            >
                                                <option value="pending">
                                                    Pending
                                                </option>
                                                <option value="awaiting_verification">
                                                    Awaiting verification
                                                </option>
                                                <option value="verified">
                                                    Verified
                                                </option>
                                                <option value="rejected">
                                                    Rejected
                                                </option>
                                            </select>
                                        </label>
                                    </div>

                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                        <label className="space-y-1 text-xs font-medium">
                                            <span>Issue date</span>
                                            <input
                                                type="date"
                                                value={
                                                    evidenceForm.data.issue_date
                                                }
                                                onChange={(event) =>
                                                    evidenceForm.setData(
                                                        'issue_date',
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            />
                                        </label>

                                        <label className="space-y-1 text-xs font-medium">
                                            <span>Expiry date</span>
                                            <input
                                                type="date"
                                                value={
                                                    evidenceForm.data
                                                        .expiry_date
                                                }
                                                onChange={(event) =>
                                                    evidenceForm.setData(
                                                        'expiry_date',
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                            />
                                            {evidenceForm.errors.expiry_date ? (
                                                <span className="text-red-600">
                                                    {
                                                        evidenceForm.errors
                                                            .expiry_date
                                                    }
                                                </span>
                                            ) : null}
                                        </label>
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={evidenceForm.processing}
                                        className="w-full rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {evidenceForm.processing
                                            ? 'Uploading...'
                                            : 'Upload evidence'}
                                    </button>
                                </form>
                            ) : null}
                            <div className="mt-3 space-y-3">
                                {(beneficiary.evidence_items ?? []).map(
                                    (item: any) => (
                                        <div
                                            key={item.id}
                                            className="rounded-md border px-3 py-2"
                                        >
                                            <div className="font-medium capitalize">
                                                {formatStatus(
                                                    item.evidence_type,
                                                )}
                                            </div>
                                            {item.document_title ||
                                            item.document_original_name ? (
                                                <div className="mt-1 text-xs">
                                                    {item.download_url ? (
                                                        <a
                                                            href={
                                                                item.download_url
                                                            }
                                                            className="font-medium text-red-600 hover:underline"
                                                        >
                                                            {item.document_title ??
                                                                item.document_original_name}
                                                        </a>
                                                    ) : (
                                                        <span>
                                                            {item.document_title ??
                                                                item.document_original_name}
                                                        </span>
                                                    )}
                                                </div>
                                            ) : null}
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {formatStatus(
                                                    item.verification_status,
                                                )}{' '}
                                                | Expires{' '}
                                                {item.expiry_date ?? '-'}
                                            </div>
                                        </div>
                                    ),
                                )}
                                {(beneficiary.evidence_items ?? []).length ===
                                0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No evidence items are linked yet. This
                                        is a blocker for requirements-driven
                                        readiness workflows.
                                    </p>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </section>

                <ConfirmDeleteModal
                    open={deleteOpen}
                    onOpenChange={setDeleteOpen}
                    title="Delete Beneficiary"
                    submitRoute={beneficiaries.destroy}
                    routeParams={beneficiary.id}
                />
            </div>
        </AppLayout>
    );
}
