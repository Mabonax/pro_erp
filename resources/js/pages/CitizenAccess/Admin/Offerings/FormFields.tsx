import { useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';

import { Offering, OfferingOptions } from './types';

type FormData = {
    service_stream_id: string;
    institution_id: string;
    program_id: string;
    project_id: string;
    project_location_id: string;
    requirement_template_id: string;
    service_pathway_id: string;
    service_pathway_version_id: string;
    owner_staff_id: string;
    facilitator_id: string;
    name: string;
    opportunity_type: string;
    status: string;
    description: string;
    delivery_channel: string;
    delivery_mode: string;
    target_audience: string;
    province: string;
    municipality: string;
    official_url: string;
    external_provider: string;
    contact_reference: string;
    public_slug: string;
    public_title: string;
    public_summary: string;
    public_help_text: string;
    is_active: boolean;
    opens_on: string;
    closes_on: string;
    capacity: string;
    display_order: string;
    notes: string;
    metadata: { canonical_code: string; source: string; reference: string };
};

export function OfferingFormFields({ offering, options }: { offering?: Offering | null; options: OfferingOptions }) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        service_stream_id: String(offering?.service_stream_id ?? ''),
        institution_id: String(offering?.institution_id ?? ''),
        program_id: String(offering?.program_id ?? ''),
        project_id: String(offering?.project_id ?? ''),
        project_location_id: String(offering?.project_location_id ?? ''),
        requirement_template_id: String(offering?.requirement_template_id ?? ''),
        service_pathway_id: String(offering?.service_pathway_id ?? ''),
        service_pathway_version_id: String(offering?.service_pathway_version_id ?? ''),
        owner_staff_id: String(offering?.owner_staff_id ?? ''),
        facilitator_id: String(offering?.facilitator_id ?? ''),
        name: offering?.name ?? '',
        opportunity_type: offering?.opportunity_type ?? 'access_offering',
        status: offering?.status ?? 'draft',
        description: offering?.description ?? '',
        delivery_channel: offering?.delivery_channel ?? 'assisted_access',
        delivery_mode: offering?.delivery_mode ?? 'hybrid',
        target_audience: offering?.target_audience ?? '',
        province: offering?.province ?? '',
        municipality: offering?.municipality ?? '',
        official_url: offering?.official_url ?? '',
        external_provider: offering?.external_provider ?? '',
        contact_reference: offering?.contact_reference ?? '',
        public_slug: offering?.public_slug ?? '',
        public_title: offering?.public_title ?? '',
        public_summary: offering?.public_summary ?? '',
        public_help_text: offering?.public_help_text ?? '',
        is_active: offering?.is_active ?? true,
        opens_on: offering?.opens_on ?? '',
        closes_on: offering?.closes_on ?? '',
        capacity: String(offering?.capacity ?? ''),
        display_order: String(offering?.display_order ?? 0),
        notes: offering?.notes ?? '',
        metadata: {
            canonical_code: offering?.metadata?.canonical_code ?? '',
            source: offering?.metadata?.source ?? '',
            reference: offering?.metadata?.reference ?? '',
        },
    });

    const projects = useMemo(() => options.projects.filter((project) => !data.program_id || String(project.program_id) === data.program_id), [data.program_id, options.projects]);
    const locations = useMemo(() => options.projectLocations.filter((location) => !data.project_id || String(location.project_id) === data.project_id), [data.project_id, options.projectLocations]);
    const versions = useMemo(() => options.servicePathwayVersions.filter((version) => !data.service_pathway_id || String(version.service_pathway_id) === data.service_pathway_id), [data.service_pathway_id, options.servicePathwayVersions]);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (offering) {
            put(`/citizen-access/admin/offerings/${offering.id}`);
            return;
        }

        post('/citizen-access/admin/offerings');
    }

    return (
        <form onSubmit={submit} className="grid gap-6">
            <section className="grid gap-4">
                <h2 className="text-base font-semibold">Offering</h2>
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <Field label="Name" value={data.name} error={errors.name} onChange={(value) => setData('name', value)} required />
                    <Field label="Public title" value={data.public_title} error={errors.public_title} onChange={(value) => setData('public_title', value)} />
                    <Field label="Slug / code" value={data.public_slug} error={errors.public_slug} onChange={(value) => setData('public_slug', value)} />
                    <Field label="Type" value={data.opportunity_type} error={errors.opportunity_type} onChange={(value) => setData('opportunity_type', value)} required />
                    <Select label="Status" value={data.status} error={errors.status} onChange={(value) => setData('status', value)} options={[['draft', 'Draft'], ['ready', 'Ready'], ['published', 'Published'], ['unpublished', 'Unpublished'], ['archived', 'Archived']]} />
                    <Select label="Delivery mode" value={data.delivery_mode} error={errors.delivery_mode} onChange={(value) => setData('delivery_mode', value)} options={[['physical', 'Physical'], ['online', 'Online'], ['hybrid', 'Hybrid']]} />
                    <Field label="Delivery channel" value={data.delivery_channel} error={errors.delivery_channel} onChange={(value) => setData('delivery_channel', value)} />
                    <Field label="Display order" type="number" value={data.display_order} error={errors.display_order} onChange={(value) => setData('display_order', value)} />
                    <label className="flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} />
                        Active
                    </label>
                </div>
            </section>

            <section className="grid gap-4">
                <h2 className="text-base font-semibold">Relationships</h2>
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <Select label="Service stream" value={data.service_stream_id} error={errors.service_stream_id} onChange={(value) => setData('service_stream_id', value)} required options={options.serviceStreams.map((item) => [item.id, item.name ?? item.slug ?? String(item.id)])} />
                    <Select label="Institution" value={data.institution_id} error={errors.institution_id} onChange={(value) => setData('institution_id', value)} options={options.institutions.map((item) => [item.id, item.name ?? String(item.id)])} />
                    <Select label="Programme" value={data.program_id} error={errors.program_id} onChange={(value) => { setData('program_id', value); setData('project_id', ''); setData('project_location_id', ''); }} options={options.programs.map((item) => [item.id, item.title ?? item.name ?? String(item.id)])} />
                    <Select label="Project" value={data.project_id} error={errors.project_id} onChange={(value) => { setData('project_id', value); setData('project_location_id', ''); }} options={projects.map((item) => [item.id, item.name ?? String(item.id)])} />
                    <Select label="Location" value={data.project_location_id} error={errors.project_location_id} onChange={(value) => setData('project_location_id', value)} options={locations.map((item) => [item.id, item.province?.name ? `${item.province.name} - ${item.training_venue_address ?? `Location ${item.id}`}` : (item.training_venue_address ?? `Location ${item.id}`)])} />
                    <Select label="Requirement template" value={data.requirement_template_id} error={errors.requirement_template_id} onChange={(value) => setData('requirement_template_id', value)} options={options.templates.map((item) => [item.id, item.name ?? String(item.id)])} />
                    <Select label="Service pathway" value={data.service_pathway_id} error={errors.service_pathway_id} onChange={(value) => { setData('service_pathway_id', value); setData('service_pathway_version_id', ''); }} options={options.servicePathways.map((item) => [item.id, item.name ?? String(item.id)])} />
                    <Select label="Pathway version" value={data.service_pathway_version_id} error={errors.service_pathway_version_id} onChange={(value) => setData('service_pathway_version_id', value)} options={versions.map((item) => [item.id, `${item.pathway?.name ?? 'Pathway'} - ${item.label ?? item.status ?? item.id}`])} />
                    <Select label="Owner" value={data.owner_staff_id} error={errors.owner_staff_id} onChange={(value) => setData('owner_staff_id', value)} options={options.staffOwners.map((item) => [item.id, `${item.first_name ?? ''} ${item.last_name ?? ''}`.trim() || item.email || String(item.id)])} />
                    <Select label="Facilitator" value={data.facilitator_id} error={errors.facilitator_id} onChange={(value) => setData('facilitator_id', value)} options={options.facilitators.map((item) => [item.id, `${item.name ?? ''} ${item.surname ?? ''}`.trim() || item.email || String(item.id)])} />
                </div>
            </section>

            <section className="grid gap-4">
                <h2 className="text-base font-semibold">Public Details</h2>
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <Field label="Audience" value={data.target_audience} error={errors.target_audience} onChange={(value) => setData('target_audience', value)} />
                    <Field label="Province" value={data.province} error={errors.province} onChange={(value) => setData('province', value)} />
                    <Field label="Municipality" value={data.municipality} error={errors.municipality} onChange={(value) => setData('municipality', value)} />
                    <Field label="External URL" value={data.official_url} error={errors.official_url} onChange={(value) => setData('official_url', value)} />
                    <Field label="External provider" value={data.external_provider} error={errors.external_provider} onChange={(value) => setData('external_provider', value)} />
                    <Field label="Contact/reference" value={data.contact_reference} error={errors.contact_reference} onChange={(value) => setData('contact_reference', value)} />
                    <Field label="Opening date" type="date" value={data.opens_on} error={errors.opens_on} onChange={(value) => setData('opens_on', value)} />
                    <Field label="Closing date" type="date" value={data.closes_on} error={errors.closes_on} onChange={(value) => setData('closes_on', value)} />
                    <Field label="Capacity" type="number" value={data.capacity} error={errors.capacity} onChange={(value) => setData('capacity', value)} />
                </div>
                <TextArea label="Public summary" value={data.public_summary} error={errors.public_summary} onChange={(value) => setData('public_summary', value)} />
                <TextArea label="Public help text" value={data.public_help_text} error={errors.public_help_text} onChange={(value) => setData('public_help_text', value)} />
                <TextArea label="Internal description" value={data.description} error={errors.description} onChange={(value) => setData('description', value)} />
                <TextArea label="Internal notes" value={data.notes} error={errors.notes} onChange={(value) => setData('notes', value)} />
            </section>

            <section className="grid gap-4">
                <h2 className="text-base font-semibold">Metadata</h2>
                <div className="grid gap-3 md:grid-cols-3">
                    <Field label="Canonical code" value={data.metadata.canonical_code} onChange={(value) => setData('metadata', { ...data.metadata, canonical_code: value })} />
                    <Field label="Source" value={data.metadata.source} onChange={(value) => setData('metadata', { ...data.metadata, source: value })} />
                    <Field label="Reference" value={data.metadata.reference} onChange={(value) => setData('metadata', { ...data.metadata, reference: value })} />
                </div>
            </section>

            <div className="flex items-center gap-3">
                <button disabled={processing} className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50">
                    Save offering
                </button>
                <a href="/citizen-access/admin/offerings" className="rounded-md border px-4 py-2 text-sm font-medium">
                    Back
                </a>
            </div>
        </form>
    );
}

function Field({ label, value, onChange, error, type = 'text', required = false }: { label: string; value: string; onChange: (value: string) => void; error?: string; type?: string; required?: boolean }) {
    return (
        <label className="grid gap-1">
            <span className="text-xs font-semibold uppercase text-muted-foreground">{label}</span>
            <input type={type} value={value} required={required} onChange={(event) => onChange(event.target.value)} className="h-10 rounded-md border bg-background px-3 text-sm" />
            {error ? <span className="text-xs text-destructive">{error}</span> : null}
        </label>
    );
}

function Select({ label, value, onChange, options, error, required = false }: { label: string; value: string; onChange: (value: string) => void; options: Array<[number | string, string]>; error?: string; required?: boolean }) {
    return (
        <label className="grid gap-1">
            <span className="text-xs font-semibold uppercase text-muted-foreground">{label}</span>
            <select value={value} required={required} onChange={(event) => onChange(event.target.value)} className="h-10 rounded-md border bg-background px-3 text-sm">
                <option value="">Select</option>
                {options.map(([optionValue, labelText]) => (
                    <option key={optionValue} value={optionValue}>
                        {labelText}
                    </option>
                ))}
            </select>
            {error ? <span className="text-xs text-destructive">{error}</span> : null}
        </label>
    );
}

function TextArea({ label, value, onChange, error }: { label: string; value: string; onChange: (value: string) => void; error?: string }) {
    return (
        <label className="grid gap-1">
            <span className="text-xs font-semibold uppercase text-muted-foreground">{label}</span>
            <textarea value={value} onChange={(event) => onChange(event.target.value)} className="min-h-24 rounded-md border bg-background px-3 py-2 text-sm" />
            {error ? <span className="text-xs text-destructive">{error}</span> : null}
        </label>
    );
}
