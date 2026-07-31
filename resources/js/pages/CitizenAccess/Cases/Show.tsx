import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

type CaseActivity = {
    id: number;
    activity_type: string;
    official_channel?: string;
    external_reference?: string;
    submission_date?: string;
    referral_institution?: string;
    follow_up_date?: string;
    external_status?: string;
    outcome_category?: string;
    outcome_date?: string;
    outcome_verification_status?: string;
    closure_reason?: string;
    created_at?: string;
};

type CaseRecord = {
    id: number;
    case_reference: string;
    beneficiary: string;
    service_stream: string;
    stage: string;
    readiness_state: string;
    readiness_percentage: number;
    readiness_reasons: Array<{ requirement: string; status: string }>;
    assessment_items: Array<{ id: number; name: string; status: string; is_blocking: boolean; reason?: string }>;
    readiness_actions: Array<{ id: number; description: string; status: string; priority: string }>;
    activities: CaseActivity[];
};

export default function Show({ caseRecord }: { caseRecord: CaseRecord }) {
    const [activityType, setActivityType] = useState('application');
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Citizen Access Cases', href: '/citizen-access/cases' },
        { title: caseRecord.case_reference, href: `/citizen-access/cases/${caseRecord.id}` },
    ];

    function submitActivity(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.post(`/citizen-access/cases/${caseRecord.id}/activities`, Object.fromEntries(new FormData(event.currentTarget).entries()), {
            preserveScroll: true,
        });
        event.currentTarget.reset();
        setActivityType('application');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={caseRecord.case_reference} />
            <main className="space-y-6 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{caseRecord.case_reference}</h1>
                        <p className="text-sm text-muted-foreground">
                            {caseRecord.beneficiary} - {caseRecord.service_stream}
                        </p>
                    </div>
                    <button onClick={() => router.post(`/citizen-access/cases/${caseRecord.id}/readiness`)} className="rounded-md border px-4 py-2 text-sm">
                        Recalculate readiness
                    </button>
                </div>

                <section className="grid gap-4 md:grid-cols-3">
                    <Summary label="Stage" value={caseRecord.stage.replaceAll('_', ' ')} />
                    <Summary label="Readiness" value={`${caseRecord.readiness_state.replaceAll('_', ' ')} - ${caseRecord.readiness_percentage}%`} />
                    <Summary label="Eligibility" value="Internal indication only" />
                </section>

                <section className="rounded-lg border bg-card p-5">
                    <h2 className="font-semibold">Requirements</h2>
                    <div className="mt-4 space-y-3">
                        {caseRecord.assessment_items.map((item) => (
                            <div key={item.id} className="flex flex-col gap-3 rounded-md border p-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p className="font-medium">{item.name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {item.status.replaceAll('_', ' ')}
                                        {item.is_blocking ? ' - blocking' : ''}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {['verified', 'evidence_missing', 'under_verification', 'waived_with_reason', 'not_applicable', 'rejected'].map((status) => (
                                        <button
                                            key={status}
                                            onClick={() =>
                                                router.post(
                                                    `/citizen-access/assessment-items/${item.id}/status`,
                                                    { status, reason: status.includes('waived') || status === 'rejected' || status === 'not_applicable' ? 'Officer decision recorded in case workspace.' : '' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                            className="rounded-md border px-2 py-1 text-xs"
                                        >
                                            {status.replaceAll('_', ' ')}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="rounded-lg border bg-card p-5">
                    <h2 className="font-semibold">Readiness actions</h2>
                    <ul className="mt-4 space-y-2 text-sm">
                        {caseRecord.readiness_actions.map((action) => (
                            <li key={action.id} className="rounded-md border p-3">
                                {action.description} - {action.status}
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="grid gap-5 rounded-lg border bg-card p-5 lg:grid-cols-[0.9fr_1.1fr]">
                    <div>
                        <h2 className="font-semibold">Applications, referrals, follow-ups and outcomes</h2>
                        <p className="mt-2 text-sm text-muted-foreground">Record POA support separately from official external decisions. Never store institutional passwords or OTPs.</p>
                        <div className="mt-4 space-y-3">
                            {caseRecord.activities.length === 0 ? <p className="rounded-md border p-3 text-sm text-muted-foreground">No activity recorded yet.</p> : null}
                            {caseRecord.activities.map((activity) => (
                                <article key={activity.id} className="rounded-md border p-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-semibold">{activity.activity_type.replaceAll('_', ' ')}</p>
                                        <p className="text-muted-foreground">{activity.created_at}</p>
                                    </div>
                                    <p className="mt-2 text-muted-foreground">
                                        {[activity.official_channel, activity.referral_institution, activity.external_reference, activity.external_status, activity.outcome_category].filter(Boolean).join(' - ') || 'Activity details captured.'}
                                    </p>
                                    {activity.follow_up_date ? <p className="mt-1 text-muted-foreground">Next follow-up: {activity.follow_up_date}</p> : null}
                                    {activity.outcome_verification_status ? <p className="mt-1 text-muted-foreground">Outcome verification: {activity.outcome_verification_status.replaceAll('_', ' ')}</p> : null}
                                </article>
                            ))}
                        </div>
                    </div>
                    <form onSubmit={submitActivity} className="grid gap-3 rounded-md border p-4">
                        <label className="grid gap-1 text-sm font-medium">
                            Activity type
                            <select name="activity_type" value={activityType} onChange={(event) => setActivityType(event.target.value)} className="h-10 rounded-md border bg-background px-3">
                                <option value="application">Application</option>
                                <option value="referral">Referral</option>
                                <option value="follow_up">Follow-up</option>
                                <option value="outcome">Outcome</option>
                            </select>
                        </label>
                        <div className="grid gap-3 md:grid-cols-2">
                            <Input name="official_channel" label="Official channel" />
                            <Input name="external_reference" label="External reference" />
                            <Input name="submission_date" label="Submission date" type="date" />
                            <Input name="follow_up_date" label="Follow-up date" type="date" />
                            <Input name="referral_institution" label="Institution or referral destination" />
                            <Input name="external_status" label="External status" />
                        </div>
                        {activityType === 'outcome' ? (
                            <div className="grid gap-3 md:grid-cols-2">
                                <Input name="outcome_category" label="Outcome category" />
                                <Input name="outcome_date" label="Outcome date" type="date" />
                                <label className="grid gap-1 text-sm font-medium">
                                    Verification status
                                    <select name="outcome_verification_status" className="h-10 rounded-md border bg-background px-3">
                                        <option value="unverified">Unverified</option>
                                        <option value="awaiting_verification">Awaiting verification</option>
                                        <option value="verified">Verified</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </label>
                            </div>
                        ) : null}
                        <label className="grid gap-1 text-sm font-medium">
                            Notes or closure reason
                            <textarea name="closure_reason" className="min-h-24 rounded-md border bg-background px-3 py-2" />
                        </label>
                        <button className="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground">Record activity</button>
                    </form>
                </section>
            </main>
        </AppLayout>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="font-semibold">{value}</p>
        </div>
    );
}

function Input({ name, label, type = 'text' }: { name: string; label: string; type?: string }) {
    return (
        <label className="grid gap-1 text-sm font-medium">
            {label}
            <input name={name} type={type} className="h-10 rounded-md border bg-background px-3" />
        </label>
    );
}
