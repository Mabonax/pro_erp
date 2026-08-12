import { Head, Link, useForm } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Briefcase,
    CalendarClock,
    ChevronRight,
    ClipboardList,
    Download,
    Eye,
    File,
    FileImage,
    FileText,
    Folder,
    HeartPulse,
    MapPin,
    PenLine,
    Pin,
    Trash2,
    User,
    Users,
} from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';

import { ConfirmDeleteModal } from '@/components/confirm-delete-modal';
import AppLayout from '@/layouts/app-layout';
import beneficiaries from '@/routes/beneficiaries';
import { type BreadcrumbItem } from '@/types';

type CurrentParticipation = {
    program_title?: string | null;
    project_name?: string | null;
    location_name?: string | null;
    status?: string | null;
    enrolled_at?: string | null;
};

type ParticipationEntry = CurrentParticipation & {
    id: number;
    project_start_date?: string | null;
    project_end_date?: string | null;
};

type SupportCase = {
    id: number;
    case_reference?: string | null;
    service_stream?: string | null;
    stage?: string | null;
    readiness_percentage?: number | null;
};

type MilestoneAssessment = {
    id: number;
    milestone?: string | null;
    project_name?: string | null;
    status?: string | null;
    score?: number | null;
};

type EvidenceItem = {
    id: number;
    evidence_type?: string | null;
    document_title?: string | null;
    document_original_name?: string | null;
    document_mime_type?: string | null;
    document_size_bytes?: number | null;
    download_url?: string | null;
    preview_url?: string | null;
    verification_status?: string | null;
    issuer?: string | null;
    issue_date?: string | null;
    expiry_date?: string | null;
};

type ServiceJourneySummary = {
    open_support_case_count?: number | null;
    evidence_item_count?: number | null;
    completed_milestone_assessment_count?: number | null;
};

type NextOfKin = {
    name?: string | null;
    surname?: string | null;
    relationship?: string | null;
    phone?: string | null;
    email?: string | null;
};

type Beneficiary = {
    id: number;
    full_name?: string | null;
    program_title?: string | null;
    project_name?: string | null;
    project_location?: string | null;
    member_province?: string | null;
    attendance_status?: string | null;
    dob?: string | null;
    age?: number | null;
    gender?: string | null;
    id_number?: string | null;
    highest_qualification?: string | null;
    email?: string | null;
    phone?: string | null;
    street_address?: string | null;
    address_line_2?: string | null;
    city?: string | null;
    postal_code?: string | null;
    current_participation?: CurrentParticipation | null;
    participation_history?: ParticipationEntry[];
    support_cases?: SupportCase[];
    milestone_assessments?: MilestoneAssessment[];
    evidence_items?: EvidenceItem[];
    next_of_kin?: NextOfKin | null;
    service_journey_summary?: ServiceJourneySummary;
};

function formatStatus(value?: string | null) {
    return value ? value.replace(/_/g, ' ') : '-';
}

function display(value?: string | number | null) {
    return value === null || value === undefined || value === '' ? '-' : value;
}

function initials(name?: string | null) {
    const parts = (name ?? '')
        .split(' ')
        .map((part) => part.trim())
        .filter(Boolean);

    if (parts.length === 0) {
        return 'B';
    }

    return parts
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function SoftIcon({
    children,
    className = '',
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`flex size-12 shrink-0 items-center justify-center rounded-full bg-[#FDECEE] text-[#D20A1E] ${className}`}
        >
            {children}
        </div>
    );
}

function SummaryCard({
    icon,
    label,
    value,
    children,
}: {
    icon: ReactNode;
    label: string;
    value: ReactNode;
    children?: ReactNode;
}) {
    return (
        <section className="flex min-h-28 items-center gap-5 rounded-lg border border-[#E8E8E8] bg-white px-5 py-5 shadow-[0_10px_24px_rgba(15,23,42,0.06)]">
            <SoftIcon>{icon}</SoftIcon>
            <div className="min-w-0">
                <p className="text-xs font-medium text-[#667085]">{label}</p>
                <div className="mt-1 text-base leading-snug font-semibold text-[#111827]">
                    {value}
                </div>
                {children}
            </div>
        </section>
    );
}

function Panel({
    icon,
    title,
    children,
    className = '',
}: {
    icon: ReactNode;
    title: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={`rounded-lg border border-[#E8E8E8] bg-white px-5 py-5 shadow-[0_10px_24px_rgba(15,23,42,0.05)] ${className}`}
        >
            <div className="mb-5 flex items-center gap-3">
                <SoftIcon className="size-10">{icon}</SoftIcon>
                <h2 className="text-base font-semibold text-[#111827]">
                    {title}
                </h2>
            </div>
            {children}
        </section>
    );
}

function DetailRows({
    rows,
    columns = 2,
}: {
    rows: Array<[string, ReactNode]>;
    columns?: 1 | 2;
}) {
    return (
        <dl
            className={`grid gap-x-9 gap-y-4 text-sm ${
                columns === 2 ? 'md:grid-cols-2' : ''
            }`}
        >
            {rows.map(([label, value]) => (
                <div
                    key={label}
                    className="grid grid-cols-[minmax(8rem,1fr)_minmax(0,1.25fr)] items-start gap-4"
                >
                    <dt className="text-[#667085]">{label}</dt>
                    <dd className="min-w-0 text-right font-medium text-[#1F2937]">
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function StatusPill({ value }: { value?: string | null }) {
    if (!value) {
        return <span>-</span>;
    }

    return (
        <span className="inline-flex items-center rounded-md bg-[#DFF6E6] px-2.5 py-1 text-xs font-semibold text-[#14813B] capitalize">
            {formatStatus(value)}
        </span>
    );
}

function evidenceName(item: EvidenceItem) {
    return item.document_title ?? item.document_original_name ?? 'Document';
}

function formatBytes(value?: number | null) {
    if (!value) {
        return null;
    }

    if (value < 1024 * 1024) {
        return `${Math.round(value / 1024)} KB`;
    }

    return `${(value / 1024 / 1024).toFixed(1)} MB`;
}

function isPreviewable(item?: EvidenceItem | null) {
    const mime = item?.document_mime_type ?? '';

    return mime === 'application/pdf' || mime.startsWith('image/');
}

function isImageEvidence(item?: EvidenceItem | null) {
    return (item?.document_mime_type ?? '').startsWith('image/');
}

export default function BeneficiaryShow({
    beneficiary,
    canManageBeneficiary,
}: {
    beneficiary: Beneficiary;
    canManageBeneficiary: boolean;
}) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [selectedEvidenceId, setSelectedEvidenceId] = useState<number | null>(
        () =>
            (beneficiary.evidence_items ?? []).find((item) =>
                isPreviewable(item),
            )?.id ?? null,
    );
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

    const current = beneficiary.current_participation ?? {};
    const fullName = beneficiary.full_name ?? '-';
    const evidenceItems = beneficiary.evidence_items ?? [];
    const selectedEvidence =
        evidenceItems.find((item) => item.id === selectedEvidenceId) ??
        evidenceItems.find((item) => isPreviewable(item)) ??
        null;
    const projectWindow = (entry: ParticipationEntry) =>
        `${entry.project_start_date ?? '-'} to ${entry.project_end_date ?? 'ongoing'}`;
    const nextOfKinName = beneficiary.next_of_kin
        ? `${beneficiary.next_of_kin.name ?? ''} ${beneficiary.next_of_kin.surname ?? ''}`.trim()
        : '-';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Beneficiaries', href: beneficiaries.index() },
        {
            title: fullName,
            href: `/beneficiaries/${beneficiary.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={fullName} />

            <div className="min-h-full bg-[#FAFAFB] px-5 py-6 text-[#111827] md:px-6 lg:px-8">
                <div className="mx-auto max-w-[1520px] space-y-5">
                    <div className="flex flex-wrap items-start justify-between gap-6">
                        <div className="min-w-0">
                            <Link
                                href={beneficiaries.index().url}
                                className="inline-flex items-center gap-2 text-sm font-medium text-[#D20A1E] transition hover:text-[#A90817]"
                            >
                                <ArrowLeft className="size-4" />
                                Back to beneficiaries
                            </Link>

                            <div className="mt-7 flex items-center gap-7">
                                <div className="flex size-24 shrink-0 items-center justify-center rounded-full bg-[#FDECEE] text-2xl font-bold text-[#C8102E]">
                                    {initials(fullName)}
                                </div>
                                <div className="min-w-0">
                                    <h1 className="truncate text-3xl font-bold tracking-normal text-[#111827]">
                                        {fullName}
                                    </h1>
                                    <p className="mt-2 text-base text-[#667085]">
                                        {display(
                                            current.program_title ??
                                                beneficiary.program_title,
                                        )}{' '}
                                        |{' '}
                                        {display(
                                            current.project_name ??
                                                beneficiary.project_name,
                                        )}
                                    </p>
                                    <p className="mt-3 inline-flex items-center gap-2 text-sm text-[#667085]">
                                        <MapPin className="size-4" />
                                        {display(
                                            current.location_name ??
                                                beneficiary.project_location ??
                                                beneficiary.member_province,
                                        )}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {canManageBeneficiary ? (
                            <div className="mt-16 flex flex-wrap items-center gap-3">
                                <Link
                                    href={
                                        beneficiaries.edit(beneficiary.id).url
                                    }
                                    className="inline-flex h-12 items-center gap-3 rounded-md border border-[#D20A1E] bg-white px-5 text-sm font-semibold text-[#D20A1E] transition hover:bg-[#D20A1E] hover:text-white"
                                >
                                    <PenLine className="size-5" />
                                    Edit Beneficiary
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => setDeleteOpen(true)}
                                    className="inline-flex h-12 items-center gap-3 rounded-md border border-[#D20A1E] bg-white px-5 text-sm font-semibold text-[#D20A1E] transition hover:bg-[#D20A1E] hover:text-white"
                                >
                                    <Trash2 className="size-5" />
                                    Delete Beneficiary
                                </button>
                            </div>
                        ) : null}
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <SummaryCard
                            icon={<FileText className="size-7" />}
                            label="Current Program"
                            value={display(current.program_title)}
                        />
                        <SummaryCard
                            icon={<Folder className="size-7" />}
                            label="Current Project"
                            value={display(current.project_name)}
                        />
                        <SummaryCard
                            icon={<Pin className="size-7" />}
                            label="Current Site"
                            value={display(current.location_name)}
                        />
                        <SummaryCard
                            icon={<Activity className="size-7" />}
                            label="Attendance Status"
                            value={
                                <span className="inline-flex items-center gap-2 capitalize">
                                    {display(beneficiary.attendance_status)}
                                    {beneficiary.attendance_status ? (
                                        <span className="size-2 rounded-full bg-[#28A745]" />
                                    ) : null}
                                </span>
                            }
                        />
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <Panel
                            icon={<User className="size-5" />}
                            title="Beneficiary Profile"
                        >
                            <DetailRows
                                rows={[
                                    ['Full Name', display(fullName)],
                                    ['Date of Birth', display(beneficiary.dob)],
                                    ['Age', display(beneficiary.age)],
                                    ['Gender', display(beneficiary.gender)],
                                    [
                                        'ID Number',
                                        display(beneficiary.id_number),
                                    ],
                                    [
                                        'Qualification',
                                        display(
                                            beneficiary.highest_qualification,
                                        ),
                                    ],
                                    ['Email', display(beneficiary.email)],
                                    ['Phone', display(beneficiary.phone)],
                                ]}
                            />
                        </Panel>

                        <Panel
                            icon={<Briefcase className="size-5" />}
                            title="Current Placement"
                        >
                            <DetailRows
                                columns={1}
                                rows={[
                                    ['Program', display(current.program_title)],
                                    ['Project', display(current.project_name)],
                                    [
                                        'Location',
                                        display(current.location_name),
                                    ],
                                    [
                                        'Enrollment Status',
                                        <StatusPill value={current.status} />,
                                    ],
                                    [
                                        'Enrolled At',
                                        display(current.enrolled_at),
                                    ],
                                ]}
                            />
                        </Panel>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <Panel
                            icon={<MapPin className="size-5" />}
                            title="Address and Contact"
                        >
                            <DetailRows
                                rows={[
                                    [
                                        'Street Address',
                                        display(beneficiary.street_address),
                                    ],
                                    ['City', display(beneficiary.city)],
                                    [
                                        'Address Line 2',
                                        display(beneficiary.address_line_2),
                                    ],
                                    [
                                        'Postal Code',
                                        display(beneficiary.postal_code),
                                    ],
                                ]}
                            />
                        </Panel>

                        <Panel
                            icon={<Users className="size-5" />}
                            title="Next of Kin"
                        >
                            <DetailRows
                                rows={[
                                    ['Full Name', display(nextOfKinName)],
                                    [
                                        'Phone',
                                        display(beneficiary.next_of_kin?.phone),
                                    ],
                                    [
                                        'Relationship',
                                        display(
                                            beneficiary.next_of_kin
                                                ?.relationship,
                                        ),
                                    ],
                                    [
                                        'Email',
                                        display(beneficiary.next_of_kin?.email),
                                    ],
                                ]}
                            />
                        </Panel>
                    </div>

                    <Panel
                        icon={<CalendarClock className="size-5" />}
                        title="Participation History"
                    >
                        <p className="-mt-3 mb-6 text-sm text-[#667085]">
                            Historical participation across programs, project
                            iterations, and delivery sites.
                        </p>

                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b border-[#E8E8E8] text-left text-xs font-semibold text-[#111827]">
                                        <th className="px-2 py-3">Program</th>
                                        <th className="px-2 py-3">Project</th>
                                        <th className="px-2 py-3">Location</th>
                                        <th className="px-2 py-3">Status</th>
                                        <th className="px-2 py-3">
                                            Project Window
                                        </th>
                                        <th className="px-2 py-3">
                                            Enrolled At
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(
                                        beneficiary.participation_history ?? []
                                    ).map((entry: ParticipationEntry) => (
                                        <tr
                                            key={entry.id}
                                            className="border-b border-[#ECECEC] text-[#344054]"
                                        >
                                            <td className="px-2 py-4">
                                                {display(entry.program_title)}
                                            </td>
                                            <td className="px-2 py-4">
                                                {display(entry.project_name)}
                                            </td>
                                            <td className="px-2 py-4">
                                                {display(entry.location_name)}
                                            </td>
                                            <td className="px-2 py-4">
                                                <StatusPill
                                                    value={entry.status}
                                                />
                                            </td>
                                            <td className="px-2 py-4">
                                                {projectWindow(entry)}
                                            </td>
                                            <td className="px-2 py-4">
                                                {display(entry.enrolled_at)}
                                            </td>
                                        </tr>
                                    ))}
                                    {(beneficiary.participation_history ?? [])
                                        .length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-2 py-4 text-[#667085]"
                                            >
                                                No participation history
                                                recorded yet.
                                            </td>
                                        </tr>
                                    ) : null}
                                </tbody>
                            </table>
                        </div>
                    </Panel>

                    <Panel
                        icon={<HeartPulse className="size-5" />}
                        title="Service Journey"
                    >
                        <div className="-mt-3 mb-6 flex flex-wrap items-start justify-between gap-4">
                            <p className="max-w-2xl text-sm text-[#667085]">
                                Cross-domain view of support cases, evidence,
                                and delivery outcomes attached to this
                                beneficiary.
                            </p>
                            <div className="grid grid-cols-3 gap-2 text-center">
                                <div className="min-w-24 rounded-md border border-[#E8E8E8] bg-white px-4 py-3">
                                    <div className="text-xl font-bold text-[#111827]">
                                        {beneficiary.service_journey_summary
                                            ?.open_support_case_count ?? 0}
                                    </div>
                                    <div className="mt-1 text-xs text-[#667085]">
                                        Open cases
                                    </div>
                                </div>
                                <div className="min-w-24 rounded-md border border-[#E8E8E8] bg-white px-4 py-3">
                                    <div className="text-xl font-bold text-[#111827]">
                                        {beneficiary.service_journey_summary
                                            ?.evidence_item_count ?? 0}
                                    </div>
                                    <div className="mt-1 text-xs text-[#667085]">
                                        Evidence
                                    </div>
                                </div>
                                <div className="min-w-24 rounded-md border border-[#E8E8E8] bg-white px-4 py-3">
                                    <div className="text-xl font-bold text-[#111827]">
                                        {beneficiary.service_journey_summary
                                            ?.completed_milestone_assessment_count ??
                                            0}
                                    </div>
                                    <div className="mt-1 text-xs text-[#667085]">
                                        Milestones
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-4 xl:grid-cols-3">
                            <div className="rounded-lg border border-[#E8E8E8] p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <h3 className="text-sm font-semibold">
                                        Citizen Access Cases
                                    </h3>
                                    {canManageBeneficiary ? (
                                        <Link
                                            href={`/citizen-access/cases/create?beneficiary_id=${beneficiary.id}`}
                                            className="text-xs font-semibold text-[#D20A1E] hover:underline"
                                        >
                                            Add case
                                        </Link>
                                    ) : null}
                                </div>
                                <div className="mt-3 space-y-3">
                                    {(beneficiary.support_cases ?? []).map(
                                        (caseRecord: SupportCase) => (
                                            <Link
                                                key={caseRecord.id}
                                                href={`/citizen-access/cases/${caseRecord.id}`}
                                                className="block rounded-md border border-[#ECECEC] bg-white px-3 py-3 hover:border-[#D20A1E]"
                                            >
                                                <div className="flex items-center justify-between gap-3">
                                                    <div className="font-medium">
                                                        {
                                                            caseRecord.case_reference
                                                        }
                                                    </div>
                                                    <StatusPill
                                                        value={caseRecord.stage}
                                                    />
                                                </div>
                                                <div className="mt-3 flex items-center justify-between gap-3 text-xs text-[#667085]">
                                                    <span>
                                                        {caseRecord.service_stream ??
                                                            'No stream'}{' '}
                                                        | Readiness{' '}
                                                        {caseRecord.readiness_percentage ??
                                                            0}
                                                        %
                                                    </span>
                                                    <ChevronRight className="size-4 text-[#111827]" />
                                                </div>
                                            </Link>
                                        ),
                                    )}
                                    {(beneficiary.support_cases ?? [])
                                        .length === 0 ? (
                                        <p className="text-sm text-[#667085]">
                                            No Citizen Access case is linked
                                            yet.
                                        </p>
                                    ) : null}
                                </div>
                            </div>

                            <div className="rounded-lg border border-[#E8E8E8] p-4">
                                <h3 className="text-sm font-semibold">
                                    Milestone Outcomes
                                </h3>
                                <div className="mt-3 flex min-h-[20rem] flex-col space-y-3">
                                    {(
                                        beneficiary.milestone_assessments ?? []
                                    ).map((assessment: MilestoneAssessment) => (
                                        <div
                                            key={assessment.id}
                                            className="rounded-md border border-[#ECECEC] px-3 py-2"
                                        >
                                            <div className="font-medium">
                                                {assessment.milestone ??
                                                    'Milestone assessment'}
                                            </div>
                                            <div className="mt-1 text-xs text-[#667085]">
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
                                    ))}
                                    {(beneficiary.milestone_assessments ?? [])
                                        .length === 0 ? (
                                        <div className="flex flex-1 flex-col items-center justify-center text-center">
                                            <p className="max-w-xs text-sm leading-6 text-[#667085]">
                                                No milestone assessments are
                                                recorded yet. Delivery outcomes
                                                will appear here after
                                                facilitator or project-location
                                                assessments are captured.
                                            </p>
                                            <div className="mt-5 flex size-24 items-center justify-center rounded-full bg-[#FAFAFB] text-[#C9CED6]">
                                                <ClipboardList className="size-12" />
                                            </div>
                                        </div>
                                    ) : null}
                                </div>
                            </div>

                            <div className="rounded-lg border border-[#E8E8E8] p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <h3 className="text-sm font-semibold">
                                        Evidence Readiness
                                    </h3>
                                    <Link
                                        href={`/organization/document-library?owner_type=beneficiary&owner_id=${beneficiary.id}`}
                                        className="text-xs font-semibold text-[#D20A1E] hover:underline"
                                    >
                                        Open files
                                    </Link>
                                </div>
                                {canManageBeneficiary ? (
                                    <form
                                        onSubmit={submitEvidence}
                                        className="mt-3 space-y-3"
                                    >
                                        <div className="grid gap-3 md:grid-cols-2">
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
                                                    className="w-full rounded-md border border-[#D0D5DD] bg-white px-3 py-2 text-sm"
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
                                                    <span className="text-[#D20A1E]">
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
                                                    value={
                                                        evidenceForm.data.title
                                                    }
                                                    onChange={(event) =>
                                                        evidenceForm.setData(
                                                            'title',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded-md border border-[#D0D5DD] bg-white px-3 py-2 text-sm"
                                                    placeholder="Certified ID copy"
                                                />
                                                {evidenceForm.errors.title ? (
                                                    <span className="text-[#D20A1E]">
                                                        {
                                                            evidenceForm.errors
                                                                .title
                                                        }
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
                                                        event.target
                                                            .files?.[0] ?? null,
                                                    )
                                                }
                                                className="w-full rounded-md border border-[#D0D5DD] bg-white px-3 py-2 text-sm"
                                            />
                                            {evidenceForm.errors.file ? (
                                                <span className="text-[#D20A1E]">
                                                    {evidenceForm.errors.file}
                                                </span>
                                            ) : null}
                                        </label>

                                        <div className="grid gap-3 md:grid-cols-2">
                                            <label className="space-y-1 text-xs font-medium">
                                                <span>Issuer</span>
                                                <input
                                                    type="text"
                                                    value={
                                                        evidenceForm.data.issuer
                                                    }
                                                    onChange={(event) =>
                                                        evidenceForm.setData(
                                                            'issuer',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded-md border border-[#D0D5DD] bg-white px-3 py-2 text-sm"
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
                                                    className="w-full rounded-md border border-[#D0D5DD] bg-white px-3 py-2 text-sm"
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

                                        <div className="grid gap-3 md:grid-cols-2">
                                            <label className="space-y-1 text-xs font-medium">
                                                <span>Issue date</span>
                                                <input
                                                    type="date"
                                                    value={
                                                        evidenceForm.data
                                                            .issue_date
                                                    }
                                                    onChange={(event) =>
                                                        evidenceForm.setData(
                                                            'issue_date',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded-md border border-[#D0D5DD] bg-white px-3 py-2 text-sm"
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
                                                    className="w-full rounded-md border border-[#D0D5DD] bg-white px-3 py-2 text-sm"
                                                />
                                                {evidenceForm.errors
                                                    .expiry_date ? (
                                                    <span className="text-[#D20A1E]">
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
                                            className="inline-flex w-full items-center justify-center gap-2 rounded-md bg-[#D20A1E] px-3 py-2 text-sm font-semibold text-white hover:bg-[#A90817] disabled:cursor-not-allowed disabled:opacity-60"
                                        >
                                            <FileText className="size-4" />
                                            {evidenceForm.processing
                                                ? 'Uploading...'
                                                : 'Upload evidence'}
                                        </button>
                                    </form>
                                ) : null}

                                <div className="mt-4 border-t border-[#ECECEC] pt-4">
                                    <div className="mb-3 flex items-center justify-between gap-3">
                                        <div>
                                            <h4 className="text-sm font-semibold">
                                                Supporting Documents
                                            </h4>
                                            <p className="mt-1 text-xs text-[#667085]">
                                                Preview PDFs and images here.
                                                Other file types can be opened
                                                or downloaded.
                                            </p>
                                        </div>
                                        <span className="rounded-md bg-[#FAFAFB] px-2 py-1 text-xs font-semibold text-[#667085]">
                                            {evidenceItems.length}
                                        </span>
                                    </div>

                                    {selectedEvidence?.preview_url &&
                                    isPreviewable(selectedEvidence) ? (
                                        <div className="mb-3 overflow-hidden rounded-md border border-[#E8E8E8] bg-[#FAFAFB]">
                                            <div className="flex items-center justify-between gap-3 border-b border-[#E8E8E8] px-3 py-2">
                                                <div className="min-w-0">
                                                    <div className="truncate text-xs font-semibold">
                                                        {evidenceName(
                                                            selectedEvidence,
                                                        )}
                                                    </div>
                                                    <div className="text-[11px] text-[#667085]">
                                                        {selectedEvidence.document_mime_type ??
                                                            'Preview'}
                                                    </div>
                                                </div>
                                                <a
                                                    href={
                                                        selectedEvidence.preview_url
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1 text-xs font-semibold text-[#D20A1E] hover:underline"
                                                >
                                                    <Eye className="size-3.5" />
                                                    Open
                                                </a>
                                            </div>
                                            {isImageEvidence(
                                                selectedEvidence,
                                            ) ? (
                                                <img
                                                    src={
                                                        selectedEvidence.preview_url
                                                    }
                                                    alt={evidenceName(
                                                        selectedEvidence,
                                                    )}
                                                    className="h-52 w-full object-contain"
                                                />
                                            ) : (
                                                <iframe
                                                    src={
                                                        selectedEvidence.preview_url
                                                    }
                                                    title={evidenceName(
                                                        selectedEvidence,
                                                    )}
                                                    className="h-64 w-full bg-white"
                                                />
                                            )}
                                        </div>
                                    ) : null}

                                    <div className="space-y-2">
                                        {evidenceItems.map(
                                            (item: EvidenceItem) => (
                                                <div
                                                    key={item.id}
                                                    className={`rounded-md border px-3 py-3 ${
                                                        selectedEvidence?.id ===
                                                        item.id
                                                            ? 'border-[#D20A1E] bg-[#FFF8F9]'
                                                            : 'border-[#ECECEC] bg-white'
                                                    }`}
                                                >
                                                    <div className="flex items-start gap-3">
                                                        <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-[#FDECEE] text-[#D20A1E]">
                                                            {isImageEvidence(
                                                                item,
                                                            ) ? (
                                                                <FileImage className="size-4" />
                                                            ) : (
                                                                <File className="size-4" />
                                                            )}
                                                        </div>
                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div className="min-w-0">
                                                                    <div className="truncate text-sm font-semibold">
                                                                        {evidenceName(
                                                                            item,
                                                                        )}
                                                                    </div>
                                                                    <div className="mt-1 text-xs text-[#667085] capitalize">
                                                                        {formatStatus(
                                                                            item.evidence_type,
                                                                        )}{' '}
                                                                        |{' '}
                                                                        {formatStatus(
                                                                            item.verification_status,
                                                                        )}
                                                                    </div>
                                                                </div>
                                                                <StatusPill
                                                                    value={
                                                                        item.verification_status
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[#667085]">
                                                                {item.issuer ? (
                                                                    <span>
                                                                        Issuer:{' '}
                                                                        {
                                                                            item.issuer
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                                {item.issue_date ? (
                                                                    <span>
                                                                        Issued:{' '}
                                                                        {
                                                                            item.issue_date
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                                <span>
                                                                    Expires:{' '}
                                                                    {item.expiry_date ??
                                                                        '-'}
                                                                </span>
                                                                {formatBytes(
                                                                    item.document_size_bytes,
                                                                ) ? (
                                                                    <span>
                                                                        {formatBytes(
                                                                            item.document_size_bytes,
                                                                        )}
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                                                {item.preview_url &&
                                                                isPreviewable(
                                                                    item,
                                                                ) ? (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            setSelectedEvidenceId(
                                                                                item.id,
                                                                            )
                                                                        }
                                                                        className="inline-flex items-center gap-1.5 rounded-md border border-[#E8E8E8] px-2.5 py-1.5 text-xs font-semibold text-[#111827] hover:border-[#D20A1E] hover:text-[#D20A1E]"
                                                                    >
                                                                        <Eye className="size-3.5" />
                                                                        Preview
                                                                    </button>
                                                                ) : null}
                                                                {item.preview_url ? (
                                                                    <a
                                                                        href={
                                                                            item.preview_url
                                                                        }
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="inline-flex items-center gap-1.5 rounded-md border border-[#E8E8E8] px-2.5 py-1.5 text-xs font-semibold text-[#111827] hover:border-[#D20A1E] hover:text-[#D20A1E]"
                                                                    >
                                                                        <Eye className="size-3.5" />
                                                                        Open
                                                                    </a>
                                                                ) : null}
                                                                {item.download_url ? (
                                                                    <a
                                                                        href={
                                                                            item.download_url
                                                                        }
                                                                        className="inline-flex items-center gap-1.5 rounded-md border border-[#E8E8E8] px-2.5 py-1.5 text-xs font-semibold text-[#111827] hover:border-[#D20A1E] hover:text-[#D20A1E]"
                                                                    >
                                                                        <Download className="size-3.5" />
                                                                        Download
                                                                    </a>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                        {evidenceItems.length === 0 ? (
                                            <p className="rounded-md border border-[#ECECEC] bg-[#FAFAFB] px-3 py-3 text-sm text-[#667085]">
                                                No evidence items are linked
                                                yet. This is a blocker for
                                                requirements-driven readiness
                                                workflows.
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Panel>
                </div>

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
