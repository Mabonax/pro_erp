import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

import { OfferingFormFields } from './FormFields';
import { Offering, OfferingOptions } from './types';

export default function Form({ mode, offering, options }: { mode: 'create' | 'edit'; offering: Offering | null; options: OfferingOptions }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Citizen Access', href: '/citizen-access/intakes' },
        { title: 'Offerings', href: '/citizen-access/admin/offerings' },
        { title: mode === 'create' ? 'New Offering' : offering?.name ?? 'Offering', href: mode === 'create' ? '/citizen-access/admin/offerings/create' : `/citizen-access/admin/offerings/${offering?.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={mode === 'create' ? 'New Offering' : `Edit ${offering?.name}`} />
            <main className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{mode === 'create' ? 'New Offering' : offering?.name}</h1>
                        <p className="text-sm text-muted-foreground">Citizen Access offering management</p>
                    </div>
                </div>
                <OfferingFormFields offering={offering} options={options} />
            </main>
        </AppLayout>
    );
}
