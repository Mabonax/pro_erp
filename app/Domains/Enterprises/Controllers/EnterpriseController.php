<?php

namespace App\Domains\Enterprises\Controllers;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\EvidenceItem;
use App\Domains\Documents\Services\DocumentFileService;
use App\Domains\Documents\Services\DocumentFolderService;
use App\Domains\Enterprises\Models\Enterprise;
use App\Domains\Enterprises\Requests\StoreEnterpriseEvidenceRequest;
use App\Domains\Enterprises\Requests\StoreEnterprisePersonRoleRequest;
use App\Domains\Enterprises\Requests\StoreEnterpriseRequest;
use App\Domains\Enterprises\Requests\UpdateEnterpriseRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnterpriseController extends Controller
{
    public function __construct(
        protected DocumentFolderService $folderService,
        protected DocumentFileService $fileService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('domain.citizen-access.view') || $request->user()?->can('domain.citizen-access.manage'), 403);

        return Inertia::render('Enterprises/Index', [
            'enterprises' => Enterprise::query()
                ->withCount(['people', 'supportCases', 'evidenceItems'])
                ->orderBy('legal_name')
                ->paginate(20)
                ->through(fn (Enterprise $enterprise) => $this->mapEnterpriseRow($enterprise)),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('domain.citizen-access.manage'), 403);

        return Inertia::render('Enterprises/Create');
    }

    public function store(StoreEnterpriseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $enterprise = Enterprise::query()->create($data);

        return redirect()
            ->route('enterprises.show', $enterprise)
            ->with('success', 'Enterprise created.');
    }

    public function show(Request $request, Enterprise $enterprise): Response
    {
        abort_unless($request->user()?->can('domain.citizen-access.view') || $request->user()?->can('domain.citizen-access.manage'), 403);

        $enterprise->load([
            'people.beneficiary:id,name,surname,email,phone',
            'supportCases' => fn ($query) => $query
                ->with(['serviceStream:id,name', 'opportunity:id,name', 'servicePathwayVersion.pathway:id,name'])
                ->latest()
                ->limit(20),
            'evidenceItems' => fn ($query) => $query
                ->with('documentFile:id,title,original_name')
                ->latest()
                ->limit(20),
        ]);

        return Inertia::render('Enterprises/Show', [
            'enterprise' => $this->mapEnterpriseDetail($enterprise),
            'beneficiaries' => $this->beneficiaryOptions(),
            'canManageEnterprise' => $request->user()?->can('domain.citizen-access.manage') ?? false,
        ]);
    }

    public function edit(Request $request, Enterprise $enterprise): Response
    {
        abort_unless($request->user()?->can('domain.citizen-access.manage'), 403);

        return Inertia::render('Enterprises/Edit', [
            'enterprise' => $this->mapEnterpriseDetail($enterprise),
        ]);
    }

    public function update(UpdateEnterpriseRequest $request, Enterprise $enterprise): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $enterprise->update($data);

        return redirect()
            ->route('enterprises.show', $enterprise)
            ->with('success', 'Enterprise updated.');
    }

    public function storePerson(StoreEnterprisePersonRoleRequest $request, Enterprise $enterprise): RedirectResponse
    {
        $data = $request->validated();
        $enterprise->people()->create($data + [
            'is_primary_contact' => (bool) ($data['is_primary_contact'] ?? false),
            'is_authorised_representative' => (bool) ($data['is_authorised_representative'] ?? false),
            'is_active' => true,
        ]);

        return back()->with('success', 'Enterprise person role linked.');
    }

    public function storeEvidence(StoreEnterpriseEvidenceRequest $request, Enterprise $enterprise): RedirectResponse
    {
        $folder = $this->folderService->firstOrCreateOwnedRootFolder(
            Enterprise::class,
            $enterprise->id,
            ($enterprise->trading_name ?: $enterprise->legal_name).' Evidence',
            $request->user()
        );

        $validated = $request->validated();
        $documentFile = $this->fileService->uploadFile($folder, [
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'file' => $request->file('file'),
        ], $request->user());

        EvidenceItem::query()->create([
            'enterprise_id' => $enterprise->id,
            'document_file_id' => $documentFile->id,
            'evidence_type' => $validated['evidence_type'],
            'issuer' => $validated['issuer'] ?? null,
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'upload_source' => 'erp',
            'uploaded_by_user_id' => $request->user()->id,
            'verification_status' => $validated['verification_status'] ?? 'pending',
            'sensitivity_classification' => $validated['sensitivity_classification'] ?? 'business_confidential',
            'retention_category' => 'enterprise_support',
            'archive_status' => 'active',
        ]);

        return redirect()
            ->route('enterprises.show', $enterprise)
            ->with('success', 'Enterprise evidence uploaded.');
    }

    protected function beneficiaryOptions()
    {
        return Beneficiary::query()
            ->select('id', 'name', 'surname', 'email', 'phone')
            ->orderBy('name')
            ->limit(250)
            ->get()
            ->map(fn (Beneficiary $beneficiary) => [
                'id' => $beneficiary->id,
                'name' => trim($beneficiary->name.' '.$beneficiary->surname),
                'email' => $beneficiary->email,
                'phone' => $beneficiary->phone,
            ]);
    }

    protected function mapEnterpriseRow(Enterprise $enterprise): array
    {
        return [
            'id' => $enterprise->id,
            'legal_name' => $enterprise->legal_name,
            'trading_name' => $enterprise->trading_name,
            'registration_number' => $enterprise->registration_number,
            'enterprise_type' => $enterprise->enterprise_type,
            'sector' => $enterprise->sector,
            'province' => $enterprise->province,
            'municipality' => $enterprise->municipality,
            'is_active' => $enterprise->is_active,
            'people_count' => $enterprise->people_count ?? 0,
            'support_cases_count' => $enterprise->support_cases_count ?? 0,
            'evidence_items_count' => $enterprise->evidence_items_count ?? 0,
        ];
    }

    protected function mapEnterpriseDetail(Enterprise $enterprise): array
    {
        return [
            ...$enterprise->only([
                'id',
                'legal_name',
                'trading_name',
                'registration_number',
                'enterprise_type',
                'sector',
                'registration_status',
                'trading_status',
                'province',
                'municipality',
                'physical_address',
                'primary_email',
                'primary_telephone',
                'website',
                'notes',
                'is_active',
            ]),
            'people' => $enterprise->people->map(fn ($role) => [
                'id' => $role->id,
                'role' => $role->role,
                'person_name' => $role->person_name ?: ($role->beneficiary ? trim($role->beneficiary->name.' '.$role->beneficiary->surname) : null),
                'person_email' => $role->person_email ?: $role->beneficiary?->email,
                'person_telephone' => $role->person_telephone ?: $role->beneficiary?->phone,
                'is_primary_contact' => $role->is_primary_contact,
                'is_authorised_representative' => $role->is_authorised_representative,
                'is_active' => $role->is_active,
            ]),
            'support_cases' => $enterprise->supportCases->map(fn ($case) => [
                'id' => $case->id,
                'case_reference' => $case->case_reference,
                'service_stream' => $case->serviceStream?->name,
                'service_offering' => $case->opportunity?->name,
                'service_pathway' => $case->servicePathwayVersion?->pathway?->name,
                'stage' => $case->stage,
                'readiness_state' => $case->readiness_state,
                'readiness_percentage' => $case->readiness_percentage,
                'created_at' => $case->created_at?->format('Y-m-d H:i'),
            ]),
            'evidence_items' => $enterprise->evidenceItems->map(fn (EvidenceItem $item) => [
                'id' => $item->id,
                'evidence_type' => $item->evidence_type,
                'issuer' => $item->issuer,
                'issue_date' => $item->issue_date?->format('Y-m-d'),
                'expiry_date' => $item->expiry_date?->format('Y-m-d'),
                'verification_status' => $item->verification_status,
                'document_file' => $item->documentFile ? [
                    'id' => $item->documentFile->id,
                    'title' => $item->documentFile->title,
                    'original_name' => $item->documentFile->original_name,
                ] : null,
            ]),
        ];
    }
}
