<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientUser;
use App\Models\ContactMatch;
use App\Models\Scopes\AgencyScope;
use App\Models\Scopes\ContactScope;
use App\Services\ClientAuthService;
use App\Services\Matching\MatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated client portal endpoints (Sanctum ability `client`).
 *
 * Spec: .ai/specs/client-auth.md
 */
class ClientPortalController extends Controller
{
    public function __construct(
        private readonly ClientAuthService $service,
        private readonly MatchingService $matching,
    ) {}

    /**
     * GET /api/v1/client/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $agencies = $this->service->agenciesFor($client);

        $contact = $client->current_agency_id
            ? $this->service->contactForAgency($client, $client->current_agency_id)
            : null;

        return response()->json([
            'client' => [
                'id'                    => $client->id,
                'email'                 => $client->email,
                'has_password'          => $client->hasPassword(),
                'password_must_change'  => (bool) $client->password_must_change,
                'preferred_agency_id'   => $client->preferred_agency_id,
                'locked_to_agency_id'   => $client->locked_to_agency_id,
                'current_agency_id'     => $client->current_agency_id,
                'last_login_at'         => $client->last_login_at,
            ],
            'agencies' => $agencies,
            'contact'  => $contact ? [
                'id'         => $contact->id,
                'first_name' => $contact->first_name,
                'last_name'  => $contact->last_name,
                'full_name'  => trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')),
                'email'      => $contact->email,
                'phone'      => $contact->phone,
                'agency_id'  => $contact->agency_id,
            ] : null,
        ]);
    }

    /**
     * GET /api/v1/client/matches
     * Core Matches that belong to the client in their currently selected agency.
     */
    public function matches(Request $request): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $agencyId = $client->current_agency_id;
        if (!$agencyId) {
            return response()->json([
                'message'  => 'Select an agency first.',
                'matches'  => [],
            ], 409);
        }

        $contact = $this->service->contactForAgency($client, $agencyId);
        if (!$contact) {
            return response()->json(['matches' => []]);
        }

        $matches = ContactMatch::query()
            ->withoutGlobalScope(AgencyScope::class)
            ->withoutGlobalScope(ContactScope::class)
            ->where('contact_id', $contact->id)
            ->where('agency_id', $agencyId)
            ->latest()
            ->get();

        $payload = $matches->map(function (ContactMatch $match) {
            $properties = collect();
            try {
                $properties = $this->matching->propertiesForMatch($match);
            } catch (\Throwable $e) {
                report($e);
            }

            return [
                'id'           => $match->id,
                'status'       => $match->status ?? null,
                'listing_type' => $match->listing_type ?? null,
                'created_at'   => $match->created_at,
                'result_count' => $properties->count(),
                'results'      => $properties->take(50)->map(fn ($p) => [
                    'id'        => $p->id,
                    'address'   => $p->address ?? null,
                    'suburb'    => $p->suburb ?? null,
                    'beds'      => $p->beds ?? null,
                    'baths'     => $p->baths ?? null,
                    'price'     => $p->price ?? null,
                    'thumbnail' => method_exists($p, 'getThumbnailUrl') ? $p->getThumbnailUrl() : null,
                ])->values(),
            ];
        });

        return response()->json([
            'agency_id' => $agencyId,
            'matches'   => $payload,
        ]);
    }
}
