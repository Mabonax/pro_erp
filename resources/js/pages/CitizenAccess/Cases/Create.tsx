import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Citizen Access Cases', href: '/citizen-access/cases' },
    { title: 'New Case', href: '/citizen-access/cases/create' },
];

export default function Create({ beneficiaries, serviceStreams, institutions, opportunities, cycles, templateVersions }: { beneficiaries: Array<{ id: number; name: string; surname: string }>; serviceStreams: Array<{ id: number; name: string }>; institutions: Array<{ id: number; name: string }>; opportunities: Array<{ id: number; name: string }>; cycles: Array<{ id: number; name: string }>; templateVersions: Array<{ id: number; version_number: number; template?: { name: string } }> }) {
    const [data, setData] = useState<Record<string, string>>({});
    const set = (key: string, value: string) => setData((current) => ({ ...current, [key]: value }));
    function submit(event: FormEvent) {
        event.preventDefault();
        router.post('/citizen-access/cases', data);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New Citizen Access Case" />
            <main className="max-w-3xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Create support case</h1>
                <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5">
                    <select required onChange={(event) => set('beneficiary_id', event.target.value)} className="h-10 rounded-md border bg-background px-3"><option value="">Beneficiary</option>{beneficiaries.map((item) => <option key={item.id} value={item.id}>{item.name} {item.surname}</option>)}</select>
                    <select required onChange={(event) => set('service_stream_id', event.target.value)} className="h-10 rounded-md border bg-background px-3"><option value="">Service stream</option>{serviceStreams.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                    <select onChange={(event) => set('institution_id', event.target.value)} className="h-10 rounded-md border bg-background px-3"><option value="">Institution</option>{institutions.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                    <select onChange={(event) => set('opportunity_id', event.target.value)} className="h-10 rounded-md border bg-background px-3"><option value="">Opportunity</option>{opportunities.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                    <select onChange={(event) => set('application_cycle_id', event.target.value)} className="h-10 rounded-md border bg-background px-3"><option value="">Application cycle</option>{cycles.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                    <select onChange={(event) => set('template_version_id', event.target.value)} className="h-10 rounded-md border bg-background px-3"><option value="">Requirement template version</option>{templateVersions.map((item) => <option key={item.id} value={item.id}>{item.template?.name} v{item.version_number}</option>)}</select>
                    <button className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Create case</button>
                </form>
            </main>
        </AppLayout>
    );
}
