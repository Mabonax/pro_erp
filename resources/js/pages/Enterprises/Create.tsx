import { Head, router } from '@inertiajs/react';
import { FormEvent } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Enterprises', href: '/enterprises' },
    { title: 'New Enterprise', href: '/enterprises/create' },
];

export default function Create() {
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.post('/enterprises', Object.fromEntries(new FormData(event.currentTarget).entries()));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New Enterprise" />
            <main className="max-w-5xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Create enterprise</h1>
                <EnterpriseForm onSubmit={submit} />
            </main>
        </AppLayout>
    );
}

export function EnterpriseForm({ enterprise, onSubmit }: { enterprise?: any; onSubmit: (event: FormEvent<HTMLFormElement>) => void }) {
    return (
        <form onSubmit={onSubmit} className="grid gap-4 rounded-lg border bg-card p-5">
            <div className="grid gap-3 md:grid-cols-3">
                <Field name="legal_name" label="Legal name" defaultValue={enterprise?.legal_name} required />
                <Field name="trading_name" label="Trading name" defaultValue={enterprise?.trading_name} />
                <Field name="registration_number" label="Registration number" defaultValue={enterprise?.registration_number} />
                <Field name="enterprise_type" label="Enterprise type" defaultValue={enterprise?.enterprise_type} />
                <Field name="sector" label="Sector or industry" defaultValue={enterprise?.sector} />
                <Field name="registration_status" label="Registration status" defaultValue={enterprise?.registration_status} />
                <Field name="trading_status" label="Trading status" defaultValue={enterprise?.trading_status} />
                <Field name="province" label="Province" defaultValue={enterprise?.province} />
                <Field name="municipality" label="Municipality" defaultValue={enterprise?.municipality} />
                <Field name="primary_email" label="Primary email" type="email" defaultValue={enterprise?.primary_email} />
                <Field name="primary_telephone" label="Primary telephone" defaultValue={enterprise?.primary_telephone} />
                <Field name="website" label="Website" type="url" defaultValue={enterprise?.website} />
            </div>
            <label className="grid gap-1">
                <span className="text-xs font-semibold uppercase text-muted-foreground">Physical or trading address</span>
                <textarea name="physical_address" defaultValue={enterprise?.physical_address ?? ''} className="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" />
            </label>
            <label className="grid gap-1">
                <span className="text-xs font-semibold uppercase text-muted-foreground">Notes</span>
                <textarea name="notes" defaultValue={enterprise?.notes ?? ''} className="min-h-20 rounded-md border bg-background px-3 py-2 text-sm" />
            </label>
            <label className="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="is_active" defaultChecked={enterprise?.is_active ?? true} /> Active
            </label>
            <button className="w-fit rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Save enterprise</button>
        </form>
    );
}

export function Field({ name, label, type = 'text', defaultValue = '', required = false }: { name: string; label: string; type?: string; defaultValue?: string | null; required?: boolean }) {
    return (
        <label className="grid gap-1">
            <span className="text-xs font-semibold uppercase text-muted-foreground">{label}</span>
            <input name={name} type={type} defaultValue={defaultValue ?? ''} required={required} className="h-10 rounded-md border bg-background px-3 text-sm" />
        </label>
    );
}
