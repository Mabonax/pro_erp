import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Citizen Access Admin', href: '/citizen-access/admin' }];

function SimpleForm({ title, action, fields }: { title: string; action: string; fields: Array<{ name: string; label: string; type?: string }> }) {
    const [data, setData] = useState<Record<string, string>>({});
    function submit(event: FormEvent) {
        event.preventDefault();
        router.post(action, data, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-card p-4">
            <h2 className="font-semibold">{title}</h2>
            {fields.map((field) => <input key={field.name} type={field.type ?? 'text'} placeholder={field.label} onChange={(event) => setData((current) => ({ ...current, [field.name]: event.target.value }))} className="h-10 rounded-md border bg-background px-3 text-sm" />)}
            <button className="rounded-md border px-3 py-2 text-sm">Save</button>
        </form>
    );
}

export default function Index({ serviceStreams, institutions, opportunities, templates }: { serviceStreams: Array<{ id: number; name: string; slug: string }>; institutions: Array<{ id: number; name: string }>; opportunities: Array<{ id: number; name: string }>; templates: Array<{ id: number; name: string; status: string }> }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Citizen Access Admin" />
            <main className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Institutional requirements administration</h1>
                    <p className="text-sm text-muted-foreground">Configure service streams, institutions, opportunities and versioned requirement templates.</p>
                </div>
                <section className="grid gap-4 lg:grid-cols-4">
                    <SimpleForm title="Service stream" action="/citizen-access/admin/service-streams" fields={[{ name: 'name', label: 'Name' }, { name: 'slug', label: 'Slug' }, { name: 'description', label: 'Description' }]} />
                    <SimpleForm title="Institution" action="/citizen-access/admin/institutions" fields={[{ name: 'name', label: 'Name' }, { name: 'institution_type', label: 'Type' }, { name: 'official_website', label: 'Official URL' }]} />
                    <SimpleForm title="Opportunity" action="/citizen-access/admin/opportunities" fields={[{ name: 'service_stream_id', label: 'Service stream ID' }, { name: 'name', label: 'Name' }, { name: 'opportunity_type', label: 'Type' }, { name: 'official_url', label: 'Official URL' }]} />
                    <SimpleForm title="Template" action="/citizen-access/admin/templates" fields={[{ name: 'service_stream_id', label: 'Service stream ID' }, { name: 'name', label: 'Name' }, { name: 'source_reference', label: 'Source reference' }, { name: 'source_url', label: 'Source URL' }]} />
                </section>
                <section className="grid gap-4 lg:grid-cols-4">
                    <div className="rounded-lg border bg-card p-4"><h2 className="font-semibold">Streams</h2><ul className="mt-3 text-sm">{serviceStreams.map((item) => <li key={item.id}>{item.id}. {item.name}</li>)}</ul></div>
                    <div className="rounded-lg border bg-card p-4"><h2 className="font-semibold">Institutions</h2><ul className="mt-3 text-sm">{institutions.map((item) => <li key={item.id}>{item.id}. {item.name}</li>)}</ul></div>
                    <div className="rounded-lg border bg-card p-4"><h2 className="font-semibold">Opportunities</h2><ul className="mt-3 text-sm">{opportunities.map((item) => <li key={item.id}>{item.id}. {item.name}</li>)}</ul></div>
                    <div className="rounded-lg border bg-card p-4"><h2 className="font-semibold">Templates</h2><ul className="mt-3 text-sm">{templates.map((item) => <li key={item.id}>{item.id}. {item.name}</li>)}</ul></div>
                </section>
            </main>
        </AppLayout>
    );
}
