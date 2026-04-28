<?php

namespace App\Http\Controllers\PrivateProperty;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PrivateProperty\PrivatePropertyListingMapper;
use App\Services\PrivateProperty\PrivatePropertySoapClient;
use App\Services\PrivateProperty\PrivatePropertySyndicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentPpController extends Controller
{
    private PrivatePropertySyndicationService $syndicationService;
    private PrivatePropertySoapClient $soapClient;
    private PrivatePropertyListingMapper $mapper;

    public function __construct(
        PrivatePropertySyndicationService $syndicationService,
        PrivatePropertySoapClient $soapClient,
        PrivatePropertyListingMapper $mapper
    ) {
        $this->syndicationService = $syndicationService;
        $this->soapClient = $soapClient;
        $this->mapper = $mapper;
    }

    /**
     * Register/sync an agent on Private Property.
     */
    public function sync(User $user): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('manage_users'), 403);

        $result = $this->syndicationService->registerAgent($user);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Update PP ownership — claim the agent's encrypted PP ID.
     */
    public function updateId(User $user, Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('manage_users'), 403);

        $request->validate([
            'pp_agent_id' => 'required|string|max:100',
        ]);

        $result = $this->syndicationService->updateUniqueAgentId($user, $request->pp_agent_id);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Update the PP External Ref (AgentId) for an agent.
     *
     * Always uses UpdateUniqueAgentID — UpdateAgent creates a new profile
     * when the AgentId doesn't already exist on PP, which is never what we
     * want here. UpdateUniqueAgentID remaps PP's existing internal record
     * (identified by its encrypted PrivatePropertyAgentId) to the supplied
     * External Ref without creating a duplicate.
     *
     * Encrypted ID resolution order:
     *   1. pp_encrypted_id supplied in the request (admin pasted from PP support)
     *   2. user.pp_unique_agent_id stored on a previous claim
     *   3. fetch from PP via GetAgent(current External Ref)
     */
    public function updateExternalRef(User $user, Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('manage_users'), 403);

        $validated = $request->validate([
            'external_ref'    => 'required|string|max:100',
            'pp_encrypted_id' => 'nullable|string|max:500',
        ]);

        $externalRef   = trim($validated['external_ref']);
        $ppEncryptedId = trim($validated['pp_encrypted_id'] ?? '');

        if ($ppEncryptedId === '') {
            $ppEncryptedId = trim((string) ($user->pp_unique_agent_id ?? ''));
        }

        if ($ppEncryptedId === '') {
            $ppEncryptedId = $this->fetchEncryptedAgentIdFromPp($user);
        }

        if ($ppEncryptedId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Could not locate this agent on Private Property. '
                           . 'Sync the agent first (creates the profile), or paste '
                           . 'the encrypted PrivatePropertyAgentId from PP support '
                           . 'into the field below.',
            ], 422);
        }

        $result = $this->soapClient->updateUniqueAgentId($ppEncryptedId, $externalRef);

        if (isset($result['error']) && $result['error'] === true) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'UpdateUniqueAgentID failed',
            ], 422);
        }

        $user->update(['pp_unique_agent_id' => $ppEncryptedId]);

        return response()->json([
            'success'            => true,
            'message'            => 'PP External Ref remapped via UpdateUniqueAgentID (now ' . $externalRef . ')',
            'external_ref'       => $externalRef,
            'pp_unique_agent_id' => $ppEncryptedId,
        ]);
    }

    /**
     * Try to discover PP's encrypted PrivatePropertyAgentId for a user by
     * looking up their current External Ref via GetAgent. Returns '' on miss.
     */
    private function fetchEncryptedAgentIdFromPp(User $user): string
    {
        $candidates = array_unique(array_filter([
            (string) $user->id,
            (string) ($user->pp_external_ref ?? ''),
        ]));

        foreach ($candidates as $candidate) {
            try {
                $resp = $this->soapClient->getAgent($candidate);
            } catch (\Throwable $e) {
                continue;
            }

            if (isset($resp['error']) && $resp['error'] === true) {
                continue;
            }

            $encrypted = $this->extractEncryptedId($resp);
            if ($encrypted !== '') {
                return $encrypted;
            }
        }

        return '';
    }

    private function extractEncryptedId(array $resp): string
    {
        $candidates = [
            $resp['PrivatePropertyAgentId'] ?? null,
            $resp['GetAgentResult']['PrivatePropertyAgentId'] ?? null,
            $resp['Agent']['PrivatePropertyAgentId'] ?? null,
        ];

        foreach ($candidates as $value) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
