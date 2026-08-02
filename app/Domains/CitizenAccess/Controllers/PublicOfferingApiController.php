<?php

namespace App\Domains\CitizenAccess\Controllers;

use App\Domains\CitizenAccess\Models\Opportunity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicOfferingApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            hash_equals((string) config('services.citizen_access.public_intake_token'), (string) $request->bearerToken())
                && filled(config('services.citizen_access.public_intake_token')),
            403
        );

        $offerings = Opportunity::query()
            ->publishedPublic()
            ->with(['serviceStream:id,name', 'institution:id,name'])
            ->get()
            ->map(fn (Opportunity $opportunity) => [
                'slug' => $opportunity->public_slug,
                'title' => $opportunity->public_title,
                'summary' => $opportunity->public_summary,
                'service_stream' => $opportunity->serviceStream?->name,
                'institution' => $opportunity->institution?->name,
                'help_text' => $opportunity->public_help_text,
                'display_order' => $opportunity->display_order,
            ])
            ->values();

        return response()->json([
            'data' => $offerings,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
