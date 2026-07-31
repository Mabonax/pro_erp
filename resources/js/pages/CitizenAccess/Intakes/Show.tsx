import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

type Option = { id: number; name: string };
type Intake = {
    id: number;
    public_reference: string;
    status: string;
    priority: string;
    name: string;
    mobile_number: string;
    email?: string;
    province?: string;
    municipality?: string;
    assistance_description?: string;
    needs: Array<{ label: string }>;
    duplicate_candidates: Array<{ beneficiary_id: number; name: string; match_basis: string }>;
};

export default function Show({ intake, users, projects, possibleBeneficiaries }: { intake: Intake; users: Option[]; projects: Array<Option & { program_id?: number }>; possibleBeneficiaries: Option[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Citizen Access Intakes', href: '/citizen-access/intakes' },
        { title: intake.public_reference, href: `/citizen-access/intakes/${intake.id}` },
    ];
    const [assignedTo, setAssignedTo] = useState('');
    const [status, setStatus] = useState(intake.status);
    const [projectId, setProjectId] = useState('');
    const [beneficiaryId, setBeneficiaryId] = useState('');

    function post(url: string, data: Record<string, string>) {
        router.post(url, data, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={intake.public_reference} />
            <main className="grid gap-6 p-6 lg:grid-cols-[1fr_360px]">
                <section className="space-y-6">
                    <div>
                        <h1 className="text-2xl font-semibold">{intake.public_reference}</h1>
                        <p className="text-sm text-muted-foreground">{intake.name} · {intake.mobile_number} · {intake.email ?? 'No email'}</p>
                    </div>
                    <div className="rounded-lg border bg-card p-5">
                        <h2 className="font-semibold">Submission information</h2>
                        <dl className="mt-4 grid gap-3 text-sm md:grid-cols-2">
                            <div><dt className="text-muted-foreground">Status</dt><dd>{intake.status.replaceAll('_', ' ')}</dd></div>
                            <div><dt className="text-muted-foreground">Priority</dt><dd>{intake.priority}</dd></div>
                            <div><dt className="text-muted-foreground">Province</dt><dd>{intake.province ?? '-'}</dd></div>
                            <div><dt className="text-muted-foreground">Municipality</dt><dd>{intake.municipality ?? '-'}</dd></div>
                        </dl>
                        <div className="mt-4">
                            <p className="text-sm font-medium">Selected needs</p>
                            <p className="text-sm text-muted-foreground">{intake.needs.map((need) => need.label).join(', ')}</p>
                        </div>
                        <div className="mt-4">
                            <p className="text-sm font-medium">Assistance description</p>
                            <p className="text-sm text-muted-foreground">{intake.assistance_description ?? 'No free-text description supplied.'}</p>
                        </div>
                    </div>
                    <div className="rounded-lg border bg-card p-5">
                        <h2 className="font-semibold">Duplicate review</h2>
                        <p className="mt-2 text-sm text-muted-foreground">Possible matches are shown for officer decision only. Records are not merged automatically.</p>
                        <ul className="mt-4 space-y-2 text-sm">
                            {[...intake.duplicate_candidates, ...possibleBeneficiaries.map((item) => ({ beneficiary_id: item.id, name: item.name, match_basis: 'contact_search' }))].map((candidate) => (
                                <li key={`${candidate.beneficiary_id}-${candidate.match_basis}`} className="rounded-md border p-3">{candidate.name} · beneficiary #{candidate.beneficiary_id} · {candidate.match_basis}</li>
                            ))}
                        </ul>
                    </div>
                </section>
                <aside className="space-y-4">
                    <form onSubmit={(event: FormEvent) => { event.preventDefault(); post(`/citizen-access/intakes/${intake.id}/assign`, { assigned_to_user_id: assignedTo }); }} className="rounded-lg border bg-card p-4">
                        <h2 className="font-semibold">Assignment</h2>
                        <select value={assignedTo} onChange={(event) => setAssignedTo(event.target.value)} className="mt-3 h-10 w-full rounded-md border bg-background px-3 text-sm">
                            <option value="">Unassigned</option>
                            {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                        </select>
                        <button className="mt-3 w-full rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground">Save assignment</button>
                    </form>
                    <form onSubmit={(event: FormEvent) => { event.preventDefault(); post(`/citizen-access/intakes/${intake.id}/status`, { status }); }} className="rounded-lg border bg-card p-4">
                        <h2 className="font-semibold">Screening status</h2>
                        <select value={status} onChange={(event) => setStatus(event.target.value)} className="mt-3 h-10 w-full rounded-md border bg-background px-3 text-sm">
                            {['acknowledged', 'under_review', 'contact_attempted', 'screening_completed', 'consent_pending', 'qualified_for_support', 'duplicate', 'closed_without_conversion', 'withdrawn'].map((value) => <option key={value} value={value}>{value.replaceAll('_', ' ')}</option>)}
                        </select>
                        <button className="mt-3 w-full rounded-md border px-3 py-2 text-sm">Update status</button>
                    </form>
                    <form onSubmit={(event: FormEvent) => { event.preventDefault(); post(`/citizen-access/intakes/${intake.id}/convert`, { project_id: projectId }); }} className="rounded-lg border bg-card p-4">
                        <h2 className="font-semibold">Convert to beneficiary</h2>
                        <select value={projectId} onChange={(event) => setProjectId(event.target.value)} className="mt-3 h-10 w-full rounded-md border bg-background px-3 text-sm" required>
                            <option value="">Choose project</option>
                            {projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}
                        </select>
                        <button className="mt-3 w-full rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground">Convert</button>
                    </form>
                    <form onSubmit={(event: FormEvent) => { event.preventDefault(); post(`/citizen-access/intakes/${intake.id}/link`, { beneficiary_id: beneficiaryId }); }} className="rounded-lg border bg-card p-4">
                        <h2 className="font-semibold">Link existing beneficiary</h2>
                        <input value={beneficiaryId} onChange={(event) => setBeneficiaryId(event.target.value)} placeholder="Beneficiary ID" className="mt-3 h-10 w-full rounded-md border bg-background px-3 text-sm" required />
                        <button className="mt-3 w-full rounded-md border px-3 py-2 text-sm">Link</button>
                    </form>
                </aside>
            </main>
        </AppLayout>
    );
}
