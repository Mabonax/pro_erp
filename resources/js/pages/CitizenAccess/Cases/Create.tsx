import { Head, router } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Citizen Access Cases', href: '/citizen-access/cases' },
    { title: 'New Case', href: '/citizen-access/cases/create' },
];

export default function Create({
    beneficiaries,
    serviceStreams,
    institutions,
    opportunities,
    cycles,
    templateVersions,
    pathwayVersions,
    enterprises,
    selectedBeneficiaryId,
    selectedEnterpriseId,
}: {
    beneficiaries: Array<{
        id: number;
        name: string;
        surname: string;
        program_id?: number | null;
        project_id?: number | null;
        project_location_id?: number | null;
    }>;
    serviceStreams: Array<{ id: number; name: string }>;
    institutions: Array<{ id: number; name: string }>;
    opportunities: Array<{
        id: number;
        name: string;
        service_pathway_version_id?: number | null;
        service_pathway_version?: { id: number; label: string } | null;
    }>;
    enterprises: Array<{
        id: number;
        legal_name: string;
        trading_name?: string | null;
        primary_email?: string | null;
    }>;
    cycles: Array<{ id: number; name: string }>;
    templateVersions: Array<{
        id: number;
        version_number: number;
        template?: { name: string };
    }>;
    pathwayVersions: Array<{
        id: number;
        label: string;
        pathway?: { name: string; recipient_type: string };
    }>;
    selectedBeneficiaryId?: number | null;
    selectedEnterpriseId?: number | null;
}) {
    const selectedBeneficiary = useMemo(
        () =>
            beneficiaries.find(
                (beneficiary) => beneficiary.id === selectedBeneficiaryId,
            ),
        [beneficiaries, selectedBeneficiaryId],
    );
    const [data, setData] = useState<Record<string, string>>(() => {
        if (selectedEnterpriseId) {
            return {
                recipient_type: 'enterprise',
                enterprise_id: String(selectedEnterpriseId),
            };
        }

        if (selectedBeneficiary) {
            return {
                recipient_type: 'person',
                beneficiary_id: String(selectedBeneficiary.id),
                program_id: selectedBeneficiary.program_id
                    ? String(selectedBeneficiary.program_id)
                    : '',
                project_id: selectedBeneficiary.project_id
                    ? String(selectedBeneficiary.project_id)
                    : '',
                project_location_id: selectedBeneficiary.project_location_id
                    ? String(selectedBeneficiary.project_location_id)
                    : '',
            };
        }

        return {
            recipient_type: 'person',
        };
    });
    const set = (key: string, value: string) =>
        setData((current) => ({ ...current, [key]: value }));
    const setBeneficiary = (value: string) => {
        const beneficiary = beneficiaries.find(
            (item) => String(item.id) === value,
        );

        setData((current) => ({
            ...current,
            recipient_type: 'person',
            beneficiary_id: value,
            enterprise_id: '',
            program_id: beneficiary?.program_id
                ? String(beneficiary.program_id)
                : '',
            project_id: beneficiary?.project_id
                ? String(beneficiary.project_id)
                : '',
            project_location_id: beneficiary?.project_location_id
                ? String(beneficiary.project_location_id)
                : '',
        }));
    };
    const setEnterprise = (value: string) =>
        setData((current) => ({
            ...current,
            recipient_type: 'enterprise',
            enterprise_id: value,
            beneficiary_id: '',
        }));
    const setOpportunity = (value: string) => {
        const opportunity = opportunities.find((item) => String(item.id) === value);
        setData((current) => ({
            ...current,
            opportunity_id: value,
            service_pathway_version_id: opportunity?.service_pathway_version_id
                ? String(opportunity.service_pathway_version_id)
                : current.service_pathway_version_id ?? '',
        }));
    };
    function submit(event: FormEvent) {
        event.preventDefault();
        router.post('/citizen-access/cases', data);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New Citizen Access Case" />
            <main className="max-w-3xl space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Create support case</h1>
                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-lg border bg-card p-5"
                >
                    <div className="grid gap-2">
                        <span className="text-xs font-semibold uppercase text-muted-foreground">
                            Recipient type
                        </span>
                        <div className="flex flex-wrap gap-3">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="radio"
                                    name="recipient_type"
                                    value="person"
                                    checked={(data.recipient_type ?? 'person') === 'person'}
                                    onChange={() =>
                                        setData((current) => ({
                                            ...current,
                                            recipient_type: 'person',
                                            enterprise_id: '',
                                        }))
                                    }
                                />
                                Person
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="radio"
                                    name="recipient_type"
                                    value="enterprise"
                                    checked={data.recipient_type === 'enterprise'}
                                    onChange={() =>
                                        setData((current) => ({
                                            ...current,
                                            recipient_type: 'enterprise',
                                            beneficiary_id: '',
                                        }))
                                    }
                                />
                                Enterprise
                            </label>
                        </div>
                    </div>
                    <select
                        required={(data.recipient_type ?? 'person') === 'person'}
                        disabled={data.recipient_type === 'enterprise'}
                        value={data.beneficiary_id ?? ''}
                        onChange={(event) => setBeneficiary(event.target.value)}
                        className="h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Beneficiary</option>
                        {beneficiaries.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name} {item.surname}
                            </option>
                        ))}
                    </select>
                    <select
                        required={data.recipient_type === 'enterprise'}
                        disabled={(data.recipient_type ?? 'person') === 'person'}
                        value={data.enterprise_id ?? ''}
                        onChange={(event) => setEnterprise(event.target.value)}
                        className="h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Enterprise</option>
                        {enterprises.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.trading_name || item.legal_name}
                            </option>
                        ))}
                    </select>
                    <input
                        type="hidden"
                        name="program_id"
                        value={data.program_id ?? ''}
                    />
                    <input
                        type="hidden"
                        name="project_id"
                        value={data.project_id ?? ''}
                    />
                    <input
                        type="hidden"
                        name="project_location_id"
                        value={data.project_location_id ?? ''}
                    />
                    <select
                        required
                        onChange={(event) =>
                            set('service_stream_id', event.target.value)
                        }
                        className="h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Service stream</option>
                        {serviceStreams.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                    <select
                        onChange={(event) =>
                            set('institution_id', event.target.value)
                        }
                        className="h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Institution</option>
                        {institutions.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                    <select
                        onChange={(event) => setOpportunity(event.target.value)}
                        className="h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Service offering</option>
                        {opportunities.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                                {item.service_pathway_version?.label
                                    ? ` - ${item.service_pathway_version.label}`
                                    : ''}
                            </option>
                        ))}
                    </select>
                    <select
                        value={data.service_pathway_version_id ?? ''}
                        onChange={(event) =>
                            set('service_pathway_version_id', event.target.value)
                        }
                        className="h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Service pathway version</option>
                        {pathwayVersions.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.pathway?.name} - {item.label}
                            </option>
                        ))}
                    </select>
                    <select
                        onChange={(event) =>
                            set('application_cycle_id', event.target.value)
                        }
                        className="h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Application cycle</option>
                        {cycles.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                    <select
                        onChange={(event) =>
                            set('template_version_id', event.target.value)
                        }
                        className="h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Requirement template version</option>
                        {templateVersions.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.template?.name} v{item.version_number}
                            </option>
                        ))}
                    </select>
                    <button className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">
                        Create case
                    </button>
                </form>
            </main>
        </AppLayout>
    );
}
