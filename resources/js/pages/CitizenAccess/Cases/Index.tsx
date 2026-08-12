import { Head, Link } from '@inertiajs/react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Citizen Access Cases', href: '/citizen-access/cases' }];

export default function Index({ cases }: { cases: { data: Array<{ id: number; case_reference: string; beneficiary?: string | null; enterprise?: string | null; recipient_name?: string | null; recipient_type?: string | null; service_stream: string; stage: string; readiness_state: string; readiness_percentage: number }> } }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Citizen Access Cases" />
            <main className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Support cases</h1>
                        <p className="text-sm text-muted-foreground">Institutional assessment, readiness and application/referral tracking.</p>
                    </div>
                    <Link href="/citizen-access/cases/create" className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">New case</Link>
                </div>
                <div className="overflow-hidden rounded-lg border bg-card">
                    <table className="w-full text-left text-sm">
                        <tbody>
                            {cases.data.map((caseRecord) => (
                                <tr key={caseRecord.id} className="border-b last:border-0">
                                    <td className="px-4 py-3 font-medium"><Link href={`/citizen-access/cases/${caseRecord.id}`}>{caseRecord.case_reference}</Link></td>
                                    <td className="px-4 py-3">
                                        {caseRecord.recipient_name || caseRecord.beneficiary || caseRecord.enterprise || '-'}
                                        <div className="text-xs text-muted-foreground">{caseRecord.recipient_type || 'person'}</div>
                                    </td>
                                    <td className="px-4 py-3">{caseRecord.service_stream}</td>
                                    <td className="px-4 py-3">{caseRecord.stage.replaceAll('_', ' ')}</td>
                                    <td className="px-4 py-3">{caseRecord.readiness_state.replaceAll('_', ' ')} · {caseRecord.readiness_percentage}%</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </main>
        </AppLayout>
    );
}
