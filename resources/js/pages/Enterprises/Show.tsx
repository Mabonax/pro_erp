import { Head, Link, router } from '@inertiajs/react';
import { FormEvent } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

type Enterprise = {
    id: number;
    legal_name: string;
    trading_name?: string | null;
    registration_number?: string | null;
    enterprise_type?: string | null;
    sector?: string | null;
    registration_status?: string | null;
    trading_status?: string | null;
    province?: string | null;
    municipality?: string | null;
    primary_email?: string | null;
    primary_telephone?: string | null;
    website?: string | null;
    physical_address?: string | null;
    notes?: string | null;
    is_active: boolean;
    people: Array<{ id: number; role: string; person_name?: string | null; person_email?: string | null; person_telephone?: string | null; is_primary_contact: boolean; is_authorised_representative: boolean }>;
    support_cases: Array<{ id: number; case_reference: string; service_stream?: string | null; service_offering?: string | null; service_pathway?: string | null; stage: string; readiness_state: string; readiness_percentage: number }>;
    evidence_items: Array<{ id: number; evidence_type: string; issuer?: string | null; issue_date?: string | null; expiry_date?: string | null; verification_status: string; document_file?: { title: string; original_name: string } | null }>;
};

export default function Show({ enterprise, beneficiaries, canManageEnterprise }: { enterprise: Enterprise; beneficiaries: Array<{ id: number; name: string }>; canManageEnterprise: boolean }) {
    const title = enterprise.trading_name || enterprise.legal_name;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Enterprises', href: '/enterprises' },
        { title, href: `/enterprises/${enterprise.id}` },
    ];

    function submitPerson(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.post(`/enterprises/${enterprise.id}/people`, {
            ...Object.fromEntries(form.entries()),
            is_primary_contact: form.has('is_primary_contact'),
            is_authorised_representative: form.has('is_authorised_representative'),
        }, { preserveScroll: true });
        event.currentTarget.reset();
    }

    function submitEvidence(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.post(`/enterprises/${enterprise.id}/evidence`, new FormData(event.currentTarget), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => event.currentTarget.reset(),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <main className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{title}</h1>
                        <p className="text-sm text-muted-foreground">{enterprise.legal_name}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={`/citizen-access/cases/create?enterprise_id=${enterprise.id}`} className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Open support case</Link>
                        {canManageEnterprise ? <Link href={`/enterprises/${enterprise.id}/edit`} className="rounded-md border px-4 py-2 text-sm">Edit</Link> : null}
                    </div>
                </div>

                <section className="grid gap-4 md:grid-cols-4">
                    <Summary label="Registration" value={enterprise.registration_number || 'Not recorded'} />
                    <Summary label="Compliance status" value={enterprise.registration_status || 'Not assessed'} />
                    <Summary label="Trading status" value={enterprise.trading_status || 'Not assessed'} />
                    <Summary label="Location" value={[enterprise.municipality, enterprise.province].filter(Boolean).join(', ') || 'Not recorded'} />
                </section>

                <section className="grid gap-4 lg:grid-cols-[1fr_1fr]">
                    <div className="rounded-lg border bg-card p-5">
                        <h2 className="font-semibold">People and roles</h2>
                        <div className="mt-4 space-y-3">
                            {enterprise.people.map((person) => (
                                <div key={person.id} className="rounded-md border p-3 text-sm">
                                    <div className="font-medium">{person.person_name || 'Linked person'}</div>
                                    <div className="text-muted-foreground">{person.role.replaceAll('_', ' ')}</div>
                                    <div className="text-muted-foreground">{[person.person_email, person.person_telephone].filter(Boolean).join(' · ')}</div>
                                </div>
                            ))}
                            {enterprise.people.length === 0 ? <p className="text-sm text-muted-foreground">No linked people yet.</p> : null}
                        </div>
                        {canManageEnterprise ? (
                            <form onSubmit={submitPerson} className="mt-5 grid gap-3 border-t pt-4">
                                <div className="grid gap-3 md:grid-cols-2">
                                    <Select name="beneficiary_id" label="Existing person" options={beneficiaries.map((item) => [item.id, item.name])} />
                                    <Select name="role" label="Role" options={[['owner', 'Owner'], ['director', 'Director'], ['primary_contact', 'Primary contact'], ['authorised_representative', 'Authorised representative'], ['employee', 'Employee'], ['mentor_advisor', 'Mentor or advisor']]} required />
                                    <Field name="person_name" label="Person name" />
                                    <Field name="person_email" label="Person email" type="email" />
                                    <Field name="person_telephone" label="Person phone" />
                                </div>
                                <div className="flex flex-wrap gap-4 text-sm">
                                    <label className="flex items-center gap-2"><input name="is_primary_contact" type="checkbox" /> Primary contact</label>
                                    <label className="flex items-center gap-2"><input name="is_authorised_representative" type="checkbox" /> Authorised representative</label>
                                </div>
                                <button className="w-fit rounded-md border px-3 py-2 text-sm">Link person role</button>
                            </form>
                        ) : null}
                    </div>

                    <div className="rounded-lg border bg-card p-5">
                        <h2 className="font-semibold">Evidence</h2>
                        <div className="mt-4 space-y-3">
                            {enterprise.evidence_items.map((item) => (
                                <div key={item.id} className="rounded-md border p-3 text-sm">
                                    <div className="font-medium">{item.evidence_type.replaceAll('_', ' ')}</div>
                                    <div className="text-muted-foreground">{item.verification_status.replaceAll('_', ' ')}</div>
                                    <div className="text-muted-foreground">{item.document_file?.title || item.document_file?.original_name || 'Document linked'}</div>
                                </div>
                            ))}
                            {enterprise.evidence_items.length === 0 ? <p className="text-sm text-muted-foreground">No enterprise evidence uploaded yet.</p> : null}
                        </div>
                        {canManageEnterprise ? (
                            <form onSubmit={submitEvidence} className="mt-5 grid gap-3 border-t pt-4">
                                <div className="grid gap-3 md:grid-cols-2">
                                    <Field name="evidence_type" label="Evidence type" required />
                                    <Field name="title" label="Document title" />
                                    <Field name="issuer" label="Issuer" />
                                    <Field name="issue_date" label="Issue date" type="date" />
                                    <Field name="expiry_date" label="Expiry date" type="date" />
                                    <Select name="verification_status" label="Verification status" options={[['pending', 'Pending'], ['awaiting_verification', 'Awaiting verification'], ['verified', 'Verified'], ['rejected', 'Rejected']]} />
                                    <label className="grid gap-1 md:col-span-2">
                                        <span className="text-xs font-semibold uppercase text-muted-foreground">File</span>
                                        <input name="file" type="file" required className="rounded-md border bg-background px-3 py-2 text-sm" />
                                    </label>
                                </div>
                                <button className="w-fit rounded-md border px-3 py-2 text-sm">Upload evidence</button>
                            </form>
                        ) : null}
                    </div>
                </section>

                <section className="rounded-lg border bg-card p-5">
                    <h2 className="font-semibold">Support cases</h2>
                    <div className="mt-4 overflow-hidden rounded-md border">
                        <table className="w-full text-left text-sm">
                            <tbody>
                                {enterprise.support_cases.map((caseRecord) => (
                                    <tr key={caseRecord.id} className="border-b last:border-0">
                                        <td className="px-4 py-3 font-medium"><Link href={`/citizen-access/cases/${caseRecord.id}`}>{caseRecord.case_reference}</Link></td>
                                        <td className="px-4 py-3">{caseRecord.service_pathway || caseRecord.service_stream || '-'}</td>
                                        <td className="px-4 py-3">{caseRecord.stage.replaceAll('_', ' ')}</td>
                                        <td className="px-4 py-3">{caseRecord.readiness_state.replaceAll('_', ' ')} · {caseRecord.readiness_percentage}%</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {enterprise.support_cases.length === 0 ? <p className="p-4 text-sm text-muted-foreground">No support cases yet.</p> : null}
                    </div>
                </section>
            </main>
        </AppLayout>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <div className="text-xs font-semibold uppercase text-muted-foreground">{label}</div>
            <div className="mt-2 text-sm font-medium">{value}</div>
        </div>
    );
}

function Field({ name, label, type = 'text', required = false }: { name: string; label: string; type?: string; required?: boolean }) {
    return (
        <label className="grid gap-1">
            <span className="text-xs font-semibold uppercase text-muted-foreground">{label}</span>
            <input name={name} type={type} required={required} className="h-10 rounded-md border bg-background px-3 text-sm" />
        </label>
    );
}

function Select({ name, label, options, required = false }: { name: string; label: string; options: Array<[number | string, string]>; required?: boolean }) {
    return (
        <label className="grid gap-1">
            <span className="text-xs font-semibold uppercase text-muted-foreground">{label}</span>
            <select name={name} required={required} className="h-10 rounded-md border bg-background px-3 text-sm">
                <option value="">Select</option>
                {options.map(([value, optionLabel]) => (
                    <option key={value} value={value}>{optionLabel}</option>
                ))}
            </select>
        </label>
    );
}
