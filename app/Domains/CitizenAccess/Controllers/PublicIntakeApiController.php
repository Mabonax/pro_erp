<?php

namespace App\Domains\CitizenAccess\Controllers;

use App\Domains\CitizenAccess\Requests\PublicIntakeRequest;
use App\Domains\CitizenAccess\Services\CitizenAccessIntakeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PublicIntakeApiController extends Controller
{
    public function __construct(private CitizenAccessIntakeService $service) {}

    public function store(PublicIntakeRequest $request): JsonResponse
    {
        $intake = $this->service->createPublicIntake(
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'public_reference' => $intake->public_reference,
            'submitted_at' => $intake->created_at?->toIso8601String(),
            'status' => 'received_for_screening',
            'next_step' => 'Program of Action will screen the request and contact the citizen using the preferred contact method.',
        ], $intake->wasRecentlyCreated ? 201 : 200);
    }
}
