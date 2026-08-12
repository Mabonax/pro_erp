import { Head, Link, router } from '@inertiajs/react';
import { Archive, CheckCircle2, Copy, Pencil, Power, RotateCcw, XCircle } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

import { Offering } from './types';

export default function Show({ offering, permissions }: { offering: Offering; permissions: Record<string, boolean> }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Citizen Access', href: '/citizen-access/intakes' },
        { title: 'Offerings', href: '/citizen-access/admin/offerings' },
        { title: offering.name, href: `/citizen-access/admin/offerings/${offering.id}` },
    ];

    const publicVisibility = offering.is_published && offering.status === 'published' && offering.is_active && !offering.archived_at ? 'Visible on public website' : 'Not visible on public website';
    const publishReady = offering.publish_readiness ?? offering.readiness;

    function action(path: string, confirmation?: string) {
        if (confirmation && !window.confirm(confirmation)) {
            return;
        }

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
                        {permissions.publish && !offering.is_published ? <button disabled={!publishReady.ready} onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/publish`, 'Publish this offering to the public website?')} className="inline-flex items-center gap-2 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"><CheckCircle2 className="h-4 w-4" /> Publish Offering</button> : null}
                        {permissions.publish && offering.is_published ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/unpublish`, 'Unpublish this offering and remove it from the public website?')} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><XCircle className="h-4 w-4" /> Unpublish Offering</button> : null}
                        {permissions.update && offering.is_active ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/deactivate`, 'Deactivate this offering and remove it from the public website?')} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><Power className="h-4 w-4" /> Deactivate Offering</button> : null}
                        {permissions.update && !offering.is_active && !offering.archived_at ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/activate`)} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><Power className="h-4 w-4" /> Activate Offering</button> : null}
                        {permissions.archive && !offering.archived_at ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/archive`, 'Archive this offering and remove it from the public website?')} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><Archive className="h-4 w-4" /> Archive Offering</button> : null}
                        {permissions.archive && offering.archived_at ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/restore`, 'Restore this offering as a draft?')} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><RotateCcw className="h-4 w-4" /> Restore Offering</button> : null}
                        {permissions.create ? <button onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/duplicate`)} className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"><Copy className="h-4 w-4" /> Duplicate</button> : null}
                    </div>
                </div>

                <section className="grid gap-4 lg:grid-cols-[1.3fr_0.7fr]">
                    <div className="grid gap-4 rounded-lg border bg-card p-4">
                        <h2 className="font-semibold">Details</h2>
                        <dl className="grid gap-3 md:grid-cols-2">
                            <div>
                                <dt className="text-xs font-semibold uppercase text-muted-foreground">Publication status</dt>
                                <dd className="mt-2 flex flex-wrap gap-2">
                                    <Badge tone={offering.is_published && offering.status === 'published' ? 'green' : 'zinc'}>{offering.is_published && offering.status === 'published' ? 'Published' : 'Draft / Unpublished'}</Badge>
                                    <Badge tone={offering.is_active ? 'green' : 'amber'}>{offering.is_active ? 'Active' : 'Inactive'}</Badge>
                                    <Badge tone={offering.archived_at ? 'red' : 'green'}>{offering.archived_at ? 'Archived' : 'Current'}</Badge>
                                </dd>
                            </div>
                            <Item label="Public website visibility" value={publicVisibility} />
                            <Item label="Service stream" value={offering.service_stream?.name} />
                            <Item label="Program" value={offering.program?.title} />
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
                            <h2 className="font-semibold">Publication readiness</h2>
                            <p className={`mt-1 text-sm font-semibold ${publishReady.ready ? 'text-emerald-700' : 'text-rose-700'}`}>{publishReady.status}</p>
                            {!publishReady.ready ? <p className="mt-1 text-sm text-muted-foreground">{publishReady.errors.length} item(s) require attention before this offering can be published.</p> : null}
                        </div>
                        <ul className="grid gap-2">
                            {publishReady.checks.map((check) => (
                                <li key={check.field} className="flex gap-2 text-sm">
                                    {check.passes ? <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /> : <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />}
                                    <span>
                                        {check.passes ? check.label : check.message}
                                        {!check.passes && check.action ? <span className="block text-xs text-muted-foreground">{check.action}</span> : null}
                                    </span>
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

function Badge({ children, tone }: { children: string; tone: 'green' | 'amber' | 'red' | 'zinc' }) {
    const tones = {
        green: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        amber: 'bg-amber-50 text-amber-700 ring-amber-200',
        red: 'bg-rose-50 text-rose-700 ring-rose-200',
        zinc: 'bg-zinc-100 text-zinc-700 ring-zinc-200',
    };

    return <span className={`inline-flex rounded px-2 py-1 text-xs font-semibold ring-1 ${tones[tone]}`}>{children}</span>;
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
