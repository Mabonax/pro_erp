import { Head, router } from '@inertiajs/react';
import { FormEvent } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { EnterpriseForm } from './Create';

export default function Edit({ enterprise }: { enterprise: any }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Enterprises', href: '/enterprises' },
        { title: enterprise.trading_name || enterprise.legal_name, href: `/enterprises/${enterprise.id}` },
        { title: 'Edit', href: `/enterprises/${enterprise.id}/edit` },
    ];

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.put(`/enterprises/${enterprise.id}`, {
            ...Object.fromEntries(form.entries()),
            is_active: form.has('is_active'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${enterprise.trading_name || enterprise.legal_name}`} />
            <main className="max-w-5xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Edit enterprise</h1>
                <EnterpriseForm enterprise={enterprise} onSubmit={submit} />
            </main>
        </AppLayout>
    );
}
