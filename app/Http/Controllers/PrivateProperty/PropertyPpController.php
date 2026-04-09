<?php

namespace App\Http\Controllers\PrivateProperty;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\PermissionService;
use App\Services\PrivateProperty\PrivatePropertySyndicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyPpController extends Controller
{
    private PrivatePropertySyndicationService $syndicationService;

    public function __construct(PrivatePropertySyndicationService $syndicationService)
    {
        $this->syndicationService = $syndicationService;
    }

    /**
     * Save video/matterport IDs and push to PP.
     */
    public function video(Property $property, Request $request): JsonResponse
    {
        $this->authorizeProperty($property);

        $request->validate([
            'youtube_video_id' => 'nullable|string|size:11',
            'matterport_id'    => 'nullable|string|max:100',
        ]);

        if (empty($request->youtube_video_id) && empty($request->matterport_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Provide at least one of YouTube Video ID or Matterport ID.',
            ], 422);
        }

        $property->update([
            'youtube_video_id' => $request->youtube_video_id ?: null,
            'matterport_id'    => $request->matterport_id ?: null,
        ]);

        $property->refresh();

        $result = $this->syndicationService->pushVideoOrMatterport($property);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Claim PP listing ownership via encrypted listing ID.
     */
    public function updateId(Property $property, Request $request): JsonResponse
    {
        $this->authorizeProperty($property);

        $request->validate([
            'pp_listing_id' => 'required|string|max:200',
        ]);

        $result = $this->syndicationService->updateUniqueListingId($property, $request->pp_listing_id);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Authorize access — same pattern as SyndicationController.
     */
    private function authorizeProperty(Property $property): void
    {
        /** @var \App\Models\User $user */
        $user  = auth()->user();
        $scope = PermissionService::getDataScope($user, 'properties');

        if ($scope === 'all') return;
        if ($scope === 'branch' && (int) $property->branch_id === (int) $user->effectiveBranchId()) return;
        if ($scope === 'own' && (int) $property->agent_id === (int) $user->id) return;

        abort(403);
    }
}
