import { Head, router } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Citizen Access Admin', href: '/citizen-access/admin' }];

type Option = { id: number; name?: string; title?: string; label?: string; slug?: string; status?: string; program_id?: number; project_id?: number };
type Opportunity = {
    id: number;
    name: string;
    opportunity_type: string;
    service_stream_id: number;
    institution_id?: number | null;
    program_id?: number | null;
    project_id?: number | null;
    project_location_id?: number | null;
    requirement_template_id?: number | null;
    service_pathway_id?: number | null;
    service_pathway_version_id?: number | null;
    public_slug?: string | null;
    public_title?: string | null;
    public_summary?: string | null;
    public_help_text?: string | null;
    is_active: boolean;
    is_published: boolean;
    opens_on?: string | null;
    closes_on?: string | null;
    capacity?: number | null;
    display_order?: number;
    program?: { id: number; title: string } | null;
    project?: { id: number; name: string } | null;
    requirement_template?: { id: number; name: string } | null;
    service_pathway?: { id: number; name: string } | null;
    service_pathway_version?: { id: number; label: string; status: string } | null;
};
type Pathway = {
    id: number;
    name: string;
    recipient_type: string;
    status: string;
    category?: { id: number; name: string } | null;
    versions: Array<{
        id: number;
        label: string;
        version_number: number;
        status: string;
        stages: Array<{ id: number; name: string }>;
        outcome_definitions: Array<{ id: number; name: string; outcome_type: string }>;
    }>;
};
type Enterprise = {
    id: number;
    legal_name: string;
    trading_name?: string | null;
    registration_number?: string | null;
    enterprise_type?: string | null;
    sector?: string | null;
    primary_email?: string | null;
    people: Array<{ id: number; role: string; person_name?: string | null; beneficiary?: { name: string; surname: string } | null }>;
};

function SimpleForm({ title, action, fields }: { title: string; action: string; fields: Array<{ name: string; label: string; type?: string }> }) {
    const [data, setData] = useState<Record<string, string>>({});
    function submit(event: FormEvent) {
        event.preventDefault();
        router.post(action, data, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-card p-4">
            <h2 className="font-semibold">{title}</h2>
            {fields.map((field) => (
                <input key={field.name} type={field.type ?? 'text'} placeholder={field.label} onChange={(event) => setData((current) => ({ ...current, [field.name]: event.target.value }))} className="h-10 rounded-md border bg-background px-3 text-sm" />
            ))}
            <button className="rounded-md border px-3 py-2 text-sm">Save</button>
        </form>
    );
}

function OfferingForm({
    opportunity,
    serviceStreams,
    institutions,
    programs,
    projects,
    projectLocations,
    templates,
    servicePathways,
    servicePathwayVersions,
}: {
    opportunity?: Opportunity;
    serviceStreams: Option[];
    institutions: Option[];
    programs: Option[];
    projects: Option[];
    projectLocations: Option[];
    templates: Option[];
    servicePathways: Option[];
    servicePathwayVersions: Option[];
}) {
    const [programId, setProgramId] = useState(String(opportunity?.program_id ?? ''));
    const [projectId, setProjectId] = useState(String(opportunity?.project_id ?? ''));
    const filteredProjects = projects.filter((project) => !programId || String(project.program_id) === programId);
    const filteredLocations = projectLocations.filter((location) => !projectId || String(location.project_id) === projectId);
    const incomplete = !opportunity?.is_published && Boolean(opportunity?.public_slug || opportunity?.public_title || opportunity?.program_id || opportunity?.project_id);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const payload = Object.fromEntries(form.entries());
        const data = {
            ...payload,
            is_active: form.has('is_active'),
            is_published: form.has('is_published'),
        };

        if (opportunity) {
            router.put(`/citizen-access/admin/opportunities/${opportunity.id}`, data, { preserveScroll: true });
        } else {
            router.post('/citizen-access/admin/opportunities', data, { preserveScroll: true });
        }
    }

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold">{opportunity ? opportunity.name : 'New access offering'}</h2>
                    <p className="text-xs text-muted-foreground">{opportunity ? 'Edit public bridge configuration.' : 'Create a configured public offering.'}</p>
                </div>
                {opportunity?.is_published ? <span className="rounded bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">Published</span> : null}
                {incomplete ? <span className="rounded bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Incomplete</span> : null}
            </div>
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <Select name="service_stream_id" label="Service stream" options={serviceStreams.map((item) => [item.id, item.name ?? item.slug ?? String(item.id)])} defaultValue={opportunity?.service_stream_id} required />
                <Select name="institution_id" label="Institution" options={institutions.map((item) => [item.id, item.name ?? String(item.id)])} defaultValue={opportunity?.institution_id ?? ''} />
                <Select name="program_id" label="Programme" options={programs.map((item) => [item.id, item.title ?? String(item.id)])} defaultValue={opportunity?.program_id ?? ''} onChange={setProgramId} />
                <Select name="project_id" label="Project" options={filteredProjects.map((item) => [item.id, item.name ?? String(item.id)])} defaultValue={opportunity?.project_id ?? ''} onChange={setProjectId} />
                <Select name="project_location_id" label="Project location" options={filteredLocations.map((item) => [item.id, `${item.name ?? 'Location'} #${item.id}`])} defaultValue={opportunity?.project_location_id ?? ''} />
                <Select name="requirement_template_id" label="Requirement template" options={templates.map((item) => [item.id, item.name ?? String(item.id)])} defaultValue={opportunity?.requirement_template_id ?? ''} />
                <Select name="service_pathway_id" label="Service pathway" options={servicePathways.map((item) => [item.id, item.name ?? String(item.id)])} defaultValue={opportunity?.service_pathway_id ?? ''} />
                <Select name="service_pathway_version_id" label="Pathway version" options={servicePathwayVersions.map((item) => [item.id, item.label ?? item.name ?? String(item.id)])} defaultValue={opportunity?.service_pathway_version_id ?? ''} />
                <Field name="name" label="Internal name" defaultValue={opportunity?.name} required />
                <Field name="opportunity_type" label="Type" defaultValue={opportunity?.opportunity_type ?? 'access_offering'} required />
                <Field name="public_slug" label="Public slug" defaultValue={opportunity?.public_slug ?? ''} />
                <Field name="public_title" label="Public title" defaultValue={opportunity?.public_title ?? ''} />
                <Field name="display_order" label="Display order" type="number" defaultValue={String(opportunity?.display_order ?? 0)} />
                <Field name="opens_on" label="Opens on" type="date" defaultValue={opportunity?.opens_on ?? ''} />
                <Field name="closes_on" label="Closes on" type="date" defaultValue={opportunity?.closes_on ?? ''} />
                <Field name="capacity" label="Capacity" type="number" defaultValue={String(opportunity?.capacity ?? '')} />
                <Field name="official_url" label="Official URL" defaultValue={(opportunity as any)?.official_url ?? ''} />
            </div>
            <label className="grid gap-1">
                <span className="text-xs font-semibold uppercase text-muted-foreground">Public summary</span>
                <textarea name="public_summary" defaultValue={opportunity?.public_summary ?? ''} className="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" />
            </label>
            <label className="grid gap-1">
                <span className="text-xs font-semibold uppercase text-muted-foreground">Eligibility or help text</span>
                <textarea name="public_help_text" defaultValue={opportunity?.public_help_text ?? ''} className="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" />
            </label>
            <div className="flex flex-wrap items-center gap-4">
                <label className="flex items-center gap-2 text-sm font-medium">
                    <input name="is_active" type="checkbox" defaultChecked={opportunity?.is_active ?? true} /> Active
                </label>
                <label className="flex items-center gap-2 text-sm font-medium">
                    <input name="is_published" type="checkbox" defaultChecked={opportunity?.is_published ?? false} /> Publish
                </label>
                <button className="rounded-md border px-3 py-2 text-sm">Save offering</button>
                {opportunity?.program_id ? <a className="text-sm font-semibold text-primary underline" href={`/programs/${opportunity.program_id}`}>Open programme</a> : null}
                {opportunity?.project_id ? <a className="text-sm font-semibold text-primary underline" href={`/projects/${opportunity.project_id}`}>Open project</a> : null}
            </div>
        </form>
    );
}

export default function Index({
    serviceStreams,
    institutions,
    programs,
    projects,
    projectLocations,
    opportunities,
    templates,
    programCategories,
    servicePathways,
    servicePathwayVersions,
    enterprises,
}: {
    serviceStreams: Option[];
    institutions: Option[];
    programs: Option[];
    projects: Option[];
    projectLocations: Option[];
    opportunities: Opportunity[];
    templates: Option[];
    programCategories: Option[];
    servicePathways: Pathway[];
    servicePathwayVersions: Option[];
    enterprises: Enterprise[];
}) {
    const publishedCount = useMemo(() => opportunities.filter((item) => item.is_published).length, [opportunities]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Citizen Access Admin" />
            <main className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Institutional requirements administration</h1>
                    <p className="text-sm text-muted-foreground">Configure streams, institutions, access offerings and requirement templates. Published offerings feed the public assistance page.</p>
                    <a href="/citizen-access/admin/offerings" className="mt-3 inline-flex rounded-md border px-3 py-2 text-sm font-medium">Manage offerings</a>
                </div>
                <section className="grid gap-4 lg:grid-cols-3">
                    <SimpleForm title="Program category" action="/citizen-access/admin/program-categories" fields={[{ name: 'name', label: 'Name' }, { name: 'slug', label: 'Slug' }, { name: 'description', label: 'Description' }]} />
                    <SimpleForm title="Service stream" action="/citizen-access/admin/service-streams" fields={[{ name: 'name', label: 'Name' }, { name: 'slug', label: 'Slug' }, { name: 'description', label: 'Description' }]} />
                    <SimpleForm title="Institution" action="/citizen-access/admin/institutions" fields={[{ name: 'name', label: 'Name' }, { name: 'institution_type', label: 'Type' }, { name: 'official_website', label: 'Official URL' }]} />
                    <SimpleForm title="Template" action="/citizen-access/admin/templates" fields={[{ name: 'service_stream_id', label: 'Service stream ID' }, { name: 'name', label: 'Name' }, { name: 'source_reference', label: 'Source reference' }, { name: 'source_url', label: 'Source URL' }]} />
                </section>
                <section className="grid gap-4">
                    <div className="rounded-lg border bg-card p-4">
                        <h2 className="font-semibold">Service pathways</h2>
                        <p className="text-sm text-muted-foreground">Define reusable service blueprints such as NSFAS Application Support or Business Compliance Readiness.</p>
                    </div>
                    <PathwayForm programCategories={programCategories} serviceStreams={serviceStreams} />
                    {servicePathways.map((pathway) => (
                        <PathwayVersionForm key={pathway.id} pathway={pathway} templates={templates} />
                    ))}
                </section>
                <section className="grid gap-4">
                    <div className="rounded-lg border bg-card p-4">
                        <h2 className="font-semibold">Enterprises</h2>
                        <p className="text-sm text-muted-foreground">Register business recipients separately from natural-person beneficiaries.</p>
                    </div>
                    <EnterpriseForm />
                    {enterprises.map((enterprise) => (
                        <EnterprisePeopleForm key={enterprise.id} enterprise={enterprise} />
                    ))}
                </section>
                <section className="grid gap-4">
                    <div className="rounded-lg border bg-card p-4">
                        <h2 className="font-semibold">Access offerings</h2>
                        <p className="text-sm text-muted-foreground">{publishedCount} published offering(s). Publishing requires an active stream, programme, project, project location, requirement template, public slug and public title.</p>
                    </div>
                    <OfferingForm serviceStreams={serviceStreams} institutions={institutions} programs={programs} projects={projects} projectLocations={projectLocations} templates={templates} servicePathways={servicePathways} servicePathwayVersions={servicePathwayVersions} />
                    {opportunities.map((opportunity) => (
                        <OfferingForm key={opportunity.id} opportunity={opportunity} serviceStreams={serviceStreams} institutions={institutions} programs={programs} projects={projects} projectLocations={projectLocations} templates={templates} servicePathways={servicePathways} servicePathwayVersions={servicePathwayVersions} />
                    ))}
                </section>
            </main>
        </AppLayout>
    );
}

function PathwayForm({ programCategories, serviceStreams }: { programCategories: Option[]; serviceStreams: Option[] }) {
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.post('/citizen-access/admin/service-pathways', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-card p-4">
            <h3 className="font-semibold">New pathway</h3>
            <div className="grid gap-3 md:grid-cols-3">
                <Select name="program_category_id" label="Category" options={programCategories.map((item) => [item.id, item.name ?? String(item.id)])} />
                <Select name="service_stream_id" label="Service stream" options={serviceStreams.map((item) => [item.id, item.name ?? String(item.id)])} />
                <Select name="recipient_type" label="Recipient type" options={[['person', 'Person'], ['enterprise', 'Enterprise'], ['both', 'Both']]} required />
                <Field name="name" label="Name" required />
                <Field name="slug" label="Slug" />
                <Field name="purpose" label="Purpose" />
            </div>
            <button className="rounded-md border px-3 py-2 text-sm">Save pathway</button>
        </form>
    );
}

function PathwayVersionForm({ pathway, templates }: { pathway: Pathway; templates: Option[] }) {
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const payload = { ...Object.fromEntries(form.entries()), activate: form.has('activate') };
        router.post(`/citizen-access/admin/service-pathways/${pathway.id}/versions`, payload, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold">{pathway.name}</h3>
                    <p className="text-xs text-muted-foreground">{pathway.recipient_type} pathway, {pathway.versions.length} version(s)</p>
                </div>
            </div>
            <div className="grid gap-3 md:grid-cols-3">
                <Field name="label" label="Version label" required />
                <Select name="requirement_template_version_id" label="Requirement version" options={templates.flatMap((template: any) => (template.versions ?? []).map((version: any) => [version.id, `${template.name} v${version.version_number}`]))} />
                <label className="flex items-center gap-2 text-sm"><input name="activate" type="checkbox" /> Activate</label>
            </div>
            <label className="grid gap-1">
                <span className="text-xs font-semibold uppercase text-muted-foreground">Stages, one per line</span>
                <textarea name="stages" className="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" placeholder="Intake&#10;Eligibility screening&#10;Evidence collection&#10;Outcome capture" />
            </label>
            <label className="grid gap-1">
                <span className="text-xs font-semibold uppercase text-muted-foreground">Outcomes, one per line. Use name | type</span>
                <textarea name="outcomes" className="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" placeholder="Application submitted | service_output&#10;Funding approved | immediate_outcome&#10;Funded | longer_term_impact" />
            </label>
            <button className="rounded-md border px-3 py-2 text-sm">Create version</button>
        </form>
    );
}

function EnterpriseForm() {
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.post('/citizen-access/admin/enterprises', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-card p-4">
            <h3 className="font-semibold">New enterprise</h3>
            <div className="grid gap-3 md:grid-cols-3">
                <Field name="legal_name" label="Legal name" required />
                <Field name="trading_name" label="Trading name" />
                <Field name="registration_number" label="Registration number" />
                <Field name="enterprise_type" label="Enterprise type" />
                <Field name="sector" label="Sector" />
                <Field name="primary_email" label="Primary email" type="email" />
                <Field name="primary_telephone" label="Primary telephone" />
                <Field name="province" label="Province" />
                <Field name="municipality" label="Municipality" />
            </div>
            <button className="rounded-md border px-3 py-2 text-sm">Save enterprise</button>
        </form>
    );
}

function EnterprisePeopleForm({ enterprise }: { enterprise: Enterprise }) {
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const payload = { ...Object.fromEntries(form.entries()), is_primary_contact: form.has('is_primary_contact'), is_authorised_representative: form.has('is_authorised_representative') };
        router.post(`/citizen-access/admin/enterprises/${enterprise.id}/people`, payload, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-card p-4">
            <h3 className="font-semibold">{enterprise.trading_name || enterprise.legal_name}</h3>
            <p className="text-xs text-muted-foreground">{enterprise.people.length} linked person role(s)</p>
            <div className="grid gap-3 md:grid-cols-4">
                <Field name="person_name" label="Person name" />
                <Field name="person_email" label="Person email" type="email" />
                <Field name="person_telephone" label="Person phone" />
                <Select name="role" label="Role" options={[['owner', 'Owner'], ['director', 'Director'], ['primary_contact', 'Primary contact'], ['authorised_representative', 'Authorised representative'], ['employee', 'Employee'], ['mentor_advisor', 'Mentor or advisor']]} required />
                <label className="flex items-center gap-2 text-sm"><input name="is_primary_contact" type="checkbox" /> Primary contact</label>
                <label className="flex items-center gap-2 text-sm"><input name="is_authorised_representative" type="checkbox" /> Authorised representative</label>
            </div>
            <button className="rounded-md border px-3 py-2 text-sm">Link person</button>
        </form>
    );
}

function Field({ name, label, type = 'text', defaultValue = '', required = false }: { name: string; label: string; type?: string; defaultValue?: string; required?: boolean }) {
    return (
        <label className="grid gap-1">
            <span className="text-xs font-semibold uppercase text-muted-foreground">{label}</span>
            <input name={name} type={type} defaultValue={defaultValue} required={required} className="h-10 rounded-md border bg-background px-3 text-sm" />
        </label>
    );
}

function Select({
    name,
    label,
    options,
    defaultValue = '',
    required = false,
    onChange,
}: {
    name: string;
    label: string;
    options: Array<[number | string, string]>;
    defaultValue?: number | string | null;
    required?: boolean;
    onChange?: (value: string) => void;
}) {
    return (
        <label className="grid gap-1">
            <span className="text-xs font-semibold uppercase text-muted-foreground">{label}</span>
            <select name={name} defaultValue={defaultValue ?? ''} required={required} onChange={(event) => onChange?.(event.target.value)} className="h-10 rounded-md border bg-background px-3 text-sm">
                <option value="">Select</option>
                {options.map(([value, labelText]) => (
                    <option key={value} value={value}>
                        {labelText}
                    </option>
                ))}
            </select>
        </label>
    );
}
