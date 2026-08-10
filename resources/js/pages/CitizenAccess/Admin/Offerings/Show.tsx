import { Head, Link, router } from '@inertiajs/react';
import { Archive, CheckCircle2, Copy, Pencil, RotateCcw, XCircle } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

import { Offering } from './types';

export default function Show({ offering, permissions }: { offering: Offering; permissions: Record<string, boolean> }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Citizen Access', href: '/citizen-access/intakes' },
        { title: 'Offerings', href: '/citizen-access/admin/offerings' },
        { title: offering.name, href: `/citizen-access/admin/offerings/${offering.id}` },
    ];

    function action(path: string) {
        router.post(path, {}, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={offering.name} />
            <main className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{offering.public_title || offering.name}</h1>
                        <p className="text-sm text-muted-foreground">{offering.public_slug ?? 'No public slug'}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.update ? <Link href={`/citizen-access/admin/offerings/${offering.id}/edit`} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><Pencil className="h-4 w-4" /> Edit</Link> : null}
                        {permissions.publish && !offering.is_published ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/publish`)} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><CheckCircle2 className="h-4 w-4" /> Publish</button> : null}
                        {permissions.publish && offering.is_published ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/unpublish`)} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><XCircle className="h-4 w-4" /> Unpublish</button> : null}
                        {permissions.archive && !offering.archived_at ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/archive`)} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><Archive className="h-4 w-4" /> Archive</button> : null}
                        {permissions.archive && offering.archived_at ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/restore`)} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><RotateCcw className="h-4 w-4" /> Restore</button> : null}
                        {permissions.create ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/duplicate`)} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><Copy className="h-4 w-4" /> Duplicate</button> : null}
                    </div>
                </div>

                <section className="grid gap-4 lg:grid-cols-[1.3fr_0.7fr]">
                    <div className="grid gap-4 rounded-lg border bg-card p-4">
                        <h2 className="font-semibold">Details</h2>
                        <dl className="grid gap-3 md:grid-cols-2">
                            <Item label="Status" value={`${offering.status} / ${offering.is_active ? 'active' : 'inactive'}`} />
                            <Item label="Service stream" value={offering.service_stream?.name} />
                            <Item label="Programme" value={offering.program?.title} />
                            <Item label="Project" value={offering.project?.name} />
                            <Item label="Location" value={offering.project_location?.province?.name ?? offering.province} />
                            <Item label="Template" value={offering.requirement_template?.name} />
                            <Item label="Provider" value={offering.external_provider ?? offering.institution?.name} />
                            <Item label="Delivery" value={[offering.delivery_mode, offering.delivery_channel].filter(Boolean).join(' / ')} />
                            <Item label="Dates" value={[offering.opens_on, offering.closes_on].filter(Boolean).join(' to ')} />
                            <Item label="Owner" value={offering.owner_staff ? `${offering.owner_staff.first_name ?? ''} ${offering.owner_staff.last_name ?? ''}`.trim() : undefined} />
                        </dl>
                        <Text label="Public summary" value={offering.public_summary} />
                        <Text label="Internal description" value={offering.description} />
                        <Text label="Notes" value={offering.notes} />
                    </div>

                    <div className="grid gap-4 rounded-lg border bg-card p-4">
                        <div>
                            <h2 className="font-semibold">Public Offering Readiness</h2>
                            <p className={`mt-1 text-sm font-semibold ${offering.readiness.ready ? 'text-emerald-700' : 'text-rose-700'}`}>{offering.readiness.status}</p>
                        </div>
                        <ul className="grid gap-2">
                            {offering.readiness.checks.map((check) => (
                                <li key={check.field} className="flex gap-2 text-sm">
                                    {check.passes ? <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /> : <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />}
                                    <span>{check.passes ? check.label : check.message}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>

                <section className="grid gap-4 rounded-lg border bg-card p-4">
                    <h2 className="font-semibold">Audit</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[700px] text-left text-sm">
                            <thead className="text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th className="py-2">Action</th>
                                    <th className="py-2">Timestamp</th>
                                    <th className="py-2">Properties</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {(offering.audit_events ?? []).map((event) => (
                                    <tr key={event.id}>
                                        <td className="py-2 font-medium">{event.event_type}</td>
                                        <td className="py-2">{new Date(event.created_at).toLocaleString()}</td>
                                        <td className="py-2 text-xs text-muted-foreground">{JSON.stringify(event.properties ?? {})}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </AppLayout>
    );
}

function Item({ label, value }: { label: string; value?: string | null }) {
    return (
        <div>
            <dt className="text-xs font-semibold uppercase text-muted-foreground">{label}</dt>
            <dd className="mt-1 text-sm">{value || '-'}</dd>
        </div>
    );
}

function Text({ label, value }: { label: string; value?: string | null }) {
    return (
        <div>
            <h3 className="text-xs font-semibold uppercase text-muted-foreground">{label}</h3>
            <p className="mt-1 whitespace-pre-wrap text-sm">{value || '-'}</p>
        </div>
    );
}
