import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEvent, useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Citizen Access Intakes', href: '/citizen-access/intakes' }];

type Intake = {
    id: number;
    public_reference: string;
    status: string;
    priority: string;
    source_channel: string;
    name: string;
    mobile_number: string;
    province?: string;
    municipality?: string;
    assigned_to?: string;
    age_days: number;
    needs: Array<{ label: string }>;
};

export default function Index({ intakes, filters = {} }: { intakes: { data: Intake[]; links: Array<{ url: string | null; label: string; active: boolean }> }; filters?: { search?: string; status?: string } }) {
    const [search, setSearch] = useState(filters.search ?? '');

    function submit(event: FormEvent) {
        event.preventDefault();
        router.get('/citizen-access/intakes', { search }, { preserveState: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Citizen Access Intakes" />
            <main className="space-y-6 p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Intake queue</h1>
                        <p className="text-sm text-muted-foreground">Public, assisted, referral and campaign enquiries awaiting screening.</p>
                    </div>
                    <Link href="/citizen-access/cases" className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">Support cases</Link>
                </div>

                <form onSubmit={submit} className="flex max-w-xl gap-2">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                        <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search reference, name or mobile" className="h-10 w-full rounded-md border bg-background pl-9 pr-3 text-sm" />
                    </div>
                    <button className="rounded-md border px-4 text-sm font-medium">Search</button>
                </form>

                <div className="overflow-hidden rounded-lg border bg-card">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3">Reference</th>
                                <th className="px-4 py-3">Citizen</th>
                                <th className="px-4 py-3">Needs</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Age</th>
                                <th className="px-4 py-3">Assigned</th>
                            </tr>
                        </thead>
                        <tbody>
                            {intakes.data.map((intake) => (
                                <tr key={intake.id} className="border-b last:border-0">
                                    <td className="px-4 py-3 font-medium"><Link href={`/citizen-access/intakes/${intake.id}`}>{intake.public_reference}</Link></td>
                                    <td className="px-4 py-3">
                                        <div>{intake.name}</div>
                                        <div className="text-xs text-muted-foreground">{intake.mobile_number}</div>
                                    </td>
                                    <td className="px-4 py-3">{intake.needs.map((need) => need.label).join(', ')}</td>
                                    <td className="px-4 py-3">{intake.status.replaceAll('_', ' ')}</td>
                                    <td className="px-4 py-3">{intake.age_days}d</td>
                                    <td className="px-4 py-3">{intake.assigned_to ?? 'Unassigned'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </main>
        </AppLayout>
    );
}
