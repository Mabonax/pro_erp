<?php

namespace App\Domains\CitizenAccess\Controllers;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\CitizenAccess\Models\Intake;
use App\Domains\CitizenAccess\Services\CitizenAccessIntakeService;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntakeController extends Controller
{
    public function __construct(private CitizenAccessIntakeService $service) {}

    public function index(Request $request): Response
    {
        $query = Intake::query()->with(['needs.stream', 'assignedTo:id,name'])->latest();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('public_reference', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return Inertia::render('CitizenAccess/Intakes/Index', [
            'intakes' => $query->paginate(15)->withQueryString()->through(fn (Intake $intake) => $this->mapIntake($intake)),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Intake $intake): Response
    {
        $intake->load(['needs.stream', 'assignedTo:id,name', 'convertedBeneficiary:id,name,surname,email,phone']);

        return Inertia::render('CitizenAccess/Intakes/Show', [
            'intake' => $this->mapIntake($intake) + [
                'assistance_description' => $intake->assistance_description,
                'duplicate_candidates' => $intake->duplicate_candidates ?? [],
                'converted_beneficiary' => $intake->convertedBeneficiary ? [
                    'id' => $intake->convertedBeneficiary->id,
                    'name' => trim($intake->convertedBeneficiary->name.' '.$intake->convertedBeneficiary->surname),
                ] : null,
            ],
            'users' => User::query()->select('id', 'name')->orderBy('name')->get(),
            'projects' => Project::query()->select('id', 'name', 'program_id')->orderBy('name')->get(),
            'possibleBeneficiaries' => Beneficiary::query()
                ->select('id', 'name', 'surname', 'email', 'phone')
                ->when($intake->email, fn ($query) => $query->orWhere('email', $intake->email))
                ->when($intake->mobile_number, fn ($query) => $query->orWhere('phone', $intake->mobile_number))
                ->limit(10)
                ->get()
                ->map(fn (Beneficiary $beneficiary) => [
                    'id' => $beneficiary->id,
                    'name' => trim($beneficiary->name.' '.$beneficiary->surname),
                    'email' => $beneficiary->email,
                    'phone' => $beneficiary->phone,
                ]),
        ]);
    }

    public function assign(Request $request, Intake $intake): RedirectResponse
    {
        $validated = $request->validate(['assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id']]);
        $this->service->assign($intake, $validated['assigned_to_user_id'] ?? null, $request->user());

        return back()->with('success', 'Intake assignment updated.');
    }

    public function status(Request $request, Intake $intake): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'string', 'max:80']]);
        $this->service->updateStatus($intake, $validated['status'], $request->user());

        return back()->with('success', 'Intake status updated.');
    }

    public function convert(Request $request, Intake $intake): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        ]);

        $beneficiary = $this->service->convertToBeneficiary($intake, $validated, $request->user());

        return redirect()->route('beneficiaries.show', $beneficiary)->with('success', 'Intake converted to a beneficiary.');
    }

    public function link(Request $request, Intake $intake): RedirectResponse
    {
        $validated = $request->validate(['beneficiary_id' => ['required', 'integer', 'exists:beneficiaries,id']]);
        $this->service->linkBeneficiary($intake, Beneficiary::query()->findOrFail($validated['beneficiary_id']), $request->user());

        return back()->with('success', 'Intake linked to existing beneficiary.');
    }

    private function mapIntake(Intake $intake): array
    {
        return [
            'id' => $intake->id,
            'public_reference' => $intake->public_reference,
            'status' => $intake->status,
            'priority' => $intake->priority,
            'source_channel' => $intake->source_channel,
            'name' => trim($intake->first_name.' '.$intake->surname),
            'mobile_number' => $intake->mobile_number,
            'email' => $intake->email,
            'province' => $intake->province,
            'municipality' => $intake->municipality,
            'needs' => $intake->needs->map(fn ($need) => ['key' => $need->need_key, 'label' => $need->label])->values(),
            'assigned_to' => $intake->assignedTo?->name,
            'created_at' => $intake->created_at?->format('Y-m-d H:i'),
            'age_days' => $intake->created_at ? $intake->created_at->diffInDays(now()) : 0,
        ];
    }
}
