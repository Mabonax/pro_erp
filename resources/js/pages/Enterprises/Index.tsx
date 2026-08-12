import { Head, Link } from '@inertiajs/react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Enterprises', href: '/enterprises' }];

type EnterpriseRow = {
    id: number;
    legal_name: string;
    trading_name?: string | null;
    registration_number?: string | null;
    enterprise_type?: string | null;
    sector?: string | null;
    province?: string | null;
    municipality?: string | null;
    is_active: boolean;
    people_count: number;
    support_cases_count: number;
    evidence_items_count: number;
};

export default function Index({ enterprises }: { enterprises: { data: EnterpriseRow[] } }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Enterprises" />
            <main className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Enterprises</h1>
                        <p className="text-sm text-muted-foreground">
                            Business and organisation recipients for Citizen Access support.
                        </p>
                    </div>
                    <Link href="/enterprises/create" className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">
                        New enterprise
                    </Link>
                </div>
                <div className="overflow-hidden rounded-lg border bg-card">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-muted/40">
                            <tr>
                                <th className="px-4 py-3">Enterprise</th>
                                <th className="px-4 py-3">Registration</th>
                                <th className="px-4 py-3">Sector</th>
                                <th className="px-4 py-3">Location</th>
                                <th className="px-4 py-3">Journey</th>
                            </tr>
                        </thead>
                        <tbody>
                            {enterprises.data.map((enterprise) => (
                                <tr key={enterprise.id} className="border-b last:border-0">
                                    <td className="px-4 py-3">
                                        <Link href={`/enterprises/${enterprise.id}`} className="font-medium text-primary hover:underline">
                                            {enterprise.trading_name || enterprise.legal_name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">{enterprise.legal_name}</div>
                                    </td>
                                    <td className="px-4 py-3">{enterprise.registration_number || '-'}</td>
                                    <td className="px-4 py-3">{enterprise.sector || enterprise.enterprise_type || '-'}</td>
                                    <td className="px-4 py-3">{[enterprise.municipality, enterprise.province].filter(Boolean).join(', ') || '-'}</td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {enterprise.support_cases_count} case(s), {enterprise.evidence_items_count} evidence item(s), {enterprise.people_count} person role(s)
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </main>
        </AppLayout>
    );
}
