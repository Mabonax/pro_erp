import { Head, Link, router } from '@inertiajs/react';
import { Archive, CheckCircle2, Copy, Eye, Pencil, RotateCcw, Search, XCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { FormEvent, useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

import { Offering, OfferingOptions } from './types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Citizen Access', href: '/citizen-access/intakes' },
    { title: 'Offerings', href: '/citizen-access/admin/offerings' },
];

export default function Index({ offerings, filters, options, permissions }: { offerings: Offering[]; filters: Record<string, string>; options: OfferingOptions; permissions: Record<string, boolean> }) {
    const [form, setForm] = useState({
        search: filters.search ?? '',
        program_id: filters.program_id ?? '',
        project_id: filters.project_id ?? '',
        service_stream_id: filters.service_stream_id ?? '',
        project_location_id: filters.project_location_id ?? '',
        status: filters.status ?? '',
        active: filters.active ?? '',
        visibility: filters.visibility ?? '',
        readiness: filters.readiness ?? '',
        archived: filters.archived ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        router.get('/citizen-access/admin/offerings', form, { preserveState: true });
    }

    function action(path: string, confirmation?: string) {
        if (confirmation && !window.confirm(confirmation)) {
            return;
        }

        router.post(path, {}, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Citizen Access Offerings" />
            <main className="space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Offerings</h1>
                        <p className="text-sm text-muted-foreground">{offerings.length} configured offering(s)</p>
                    </div>
                    {permissions.create ? (
                        <Link href="/citizen-access/admin/offerings/create" className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">
                            New offering
                        </Link>
                    ) : null}
                </div>

                <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-card p-4">
                    <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
                        <label className="relative">
                            <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                            <input value={form.search} onChange={(event) => setForm({ ...form, search: event.target.value })} className="h-10 w-full rounded-md border bg-background pl-9 pr-3 text-sm" placeholder="Search" />
                        </label>
                        <Select value={form.program_id} onChange={(value) => setForm({ ...form, program_id: value })} options={options.programs.map((item) => [item.id, item.title ?? String(item.id)])} placeholder="Program" />
                        <Select value={form.project_id} onChange={(value) => setForm({ ...form, project_id: value })} options={options.projects.map((item) => [item.id, item.name ?? String(item.id)])} placeholder="Project" />
                        <Select value={form.service_stream_id} onChange={(value) => setForm({ ...form, service_stream_id: value })} options={options.serviceStreams.map((item) => [item.id, item.name ?? String(item.id)])} placeholder="Service stream" />
                        <Select value={form.project_location_id} onChange={(value) => setForm({ ...form, project_location_id: value })} options={options.projectLocations.map((item) => [item.id, item.province?.name ?? `Location ${item.id}`])} placeholder="Location/province" />
                        <Select value={form.status} onChange={(value) => setForm({ ...form, status: value })} options={[['draft', 'Draft'], ['ready', 'Ready'], ['published', 'Published'], ['unpublished', 'Unpublished'], ['archived', 'Archived']]} placeholder="Status" />
                        <Select value={form.active} onChange={(value) => setForm({ ...form, active: value })} options={[['active', 'Active'], ['inactive', 'Inactive']]} placeholder="Active" />
                        <Select value={form.visibility} onChange={(value) => setForm({ ...form, visibility: value })} options={[['public', 'Public'], ['private', 'Private']]} placeholder="Visibility" />
                        <Select value={form.readiness} onChange={(value) => setForm({ ...form, readiness: value })} options={[['publishable', 'Publishable'], ['not_publishable', 'Not publishable']]} placeholder="Readiness" />
                        <Select value={form.archived} onChange={(value) => setForm({ ...form, archived: value })} options={[['with', 'With archived'], ['only', 'Archived only']]} placeholder="Archived" />
                    </div>
                    <div className="flex gap-2">
                        <button className="rounded-md border px-3 py-2 text-sm font-medium">Filter</button>
                        <Link href="/citizen-access/admin/offerings" className="rounded-md border px-3 py-2 text-sm font-medium">
                            Reset
                        </Link>
                    </div>
                </form>

                <div className="overflow-hidden rounded-lg border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1200px] text-left text-sm">
                            <thead className="bg-muted/60 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3">Offering</th>
                                    <th className="px-4 py-3">Program</th>
                                    <th className="px-4 py-3">Project</th>
                                    <th className="px-4 py-3">Location</th>
                                    <th className="px-4 py-3">Stream</th>
                                    <th className="px-4 py-3">Visibility</th>
                                    <th className="px-4 py-3">Lifecycle</th>
                                    <th className="px-4 py-3">Readiness</th>
                                    <th className="px-4 py-3">Dates</th>
                                    <th className="px-4 py-3">Updated</th>
                                    <th className="px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {offerings.map((offering) => (
                                    <tr key={offering.id}>
                                        <td className="px-4 py-3">
                                            <Link href={`/citizen-access/admin/offerings/${offering.id}`} className="font-semibold text-primary underline">
                                                {offering.public_title || offering.name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">{offering.public_slug ?? 'No public slug'}</div>
                                        </td>
                                        <td className="px-4 py-3">{offering.program?.title ?? '-'}</td>
                                        <td className="px-4 py-3">{offering.project?.name ?? '-'}</td>
                                        <td className="px-4 py-3">{offering.project_location?.province?.name ?? offering.province ?? '-'}</td>
                                        <td className="px-4 py-3">{offering.service_stream?.name ?? '-'}</td>
                                        <td className="px-4 py-3">
                                            <Badge tone={offering.is_published && offering.status === 'published' && offering.is_active && !offering.archived_at ? 'green' : 'zinc'}>{offering.is_published && offering.status === 'published' && offering.is_active && !offering.archived_at ? 'Visible on public website' : 'Not visible'}</Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                <Badge tone={offering.status === 'published' ? 'green' : offering.status === 'archived' ? 'red' : 'amber'}>{offering.status}</Badge>
                                                <Badge tone={offering.is_active ? 'green' : 'zinc'}>{offering.is_active ? 'Active' : 'Inactive'}</Badge>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={offering.readiness.ready ? 'green' : 'red'}>{offering.readiness.ready ? 'Ready' : 'Blocked'}</Badge>
                                        </td>
                                        <td className="px-4 py-3 text-xs">{[offering.opens_on, offering.closes_on].filter(Boolean).join(' to ') || '-'}</td>
                                        <td className="px-4 py-3 text-xs">{new Date((offering as any).updated_at).toLocaleDateString()}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                <IconLink href={`/citizen-access/admin/offerings/${offering.id}`} label="View" icon={<Eye className="h-4 w-4" />} />
                                                {permissions.update ? <IconLink href={`/citizen-access/admin/offerings/${offering.id}/edit`} label="Edit" icon={<Pencil className="h-4 w-4" />} /> : null}
                                                {permissions.publish && !offering.is_published ? <IconButton label={offering.publish_readiness?.ready ? 'Publish Offering' : 'Not ready to publish'} disabled={!offering.publish_readiness?.ready} onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/publish`, 'Publish this offering to the public website?')} icon={<CheckCircle2 className="h-4 w-4" />} /> : null}
                                                {permissions.publish && offering.is_published ? <IconButton label="Unpublish Offering" onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/unpublish`, 'Unpublish this offering and remove it from the public website?')} icon={<XCircle className="h-4 w-4" />} /> : null}
                                                {permissions.archive && !offering.archived_at ? <IconButton label="Archive Offering" onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/archive`, 'Archive this offering and remove it from the public website?')} icon={<Archive className="h-4 w-4" />} /> : null}
                                                {permissions.archive && offering.archived_at ? <IconButton label="Restore Offering" onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/restore`, 'Restore this offering as a draft?')} icon={<RotateCcw className="h-4 w-4" />} /> : null}
                                                {permissions.create ? <IconButton label="Duplicate" onClick={() => action(`/citizen-access/admin/offerings/${offering.id}/duplicate`)} icon={<Copy className="h-4 w-4" />} /> : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </AppLayout>
    );
}

function Select({ value, onChange, options, placeholder }: { value: string; onChange: (value: string) => void; options: Array<[number | string, string]>; placeholder: string }) {
    return (
        <select value={value} onChange={(event) => onChange(event.target.value)} className="h-10 rounded-md border bg-background px-3 text-sm">
            <option value="">{placeholder}</option>
            {options.map(([optionValue, label]) => (
                <option key={optionValue} value={optionValue}>
                    {label}
                </option>
            ))}
        </select>
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

function IconButton({ label, icon, onClick, disabled = false }: { label: string; icon: ReactNode; onClick: () => void; disabled?: boolean }) {
    return (
        <button type="button" title={label} aria-label={label} disabled={disabled} onClick={onClick} className="inline-flex h-8 w-8 items-center justify-center rounded-md border text-muted-foreground hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40">
            {icon}
        </button>
    );
}

function IconLink({ label, icon, href }: { label: string; icon: ReactNode; href: string }) {
    return (
        <Link title={label} aria-label={label} href={href} className="inline-flex h-8 w-8 items-center justify-center rounded-md border text-muted-foreground hover:text-foreground">
            {icon}
        </Link>
    );
}
