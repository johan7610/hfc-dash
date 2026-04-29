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
     * List every agent profile on the PP branch (so admins can spot
     * duplicates created when UpdateAgent was called with mismatched
     * AgentIds). Parses the embedded XML DataSet returned by
     * GetAllAgentsForBranch and renders a sortable table.
     */
    public function index()
    {
        abort_unless(auth()->user()?->hasPermission('manage_users'), 403);

        $resp = $this->soapClient->getAllAgentsForBranch();

        $agents = [];
        $error  = null;

        if (isset($resp['error']) && $resp['error'] === true) {
            $error = $resp['message'] ?? 'Failed to fetch agents from PP';
        } else {
            $xml = $resp['GetAllAgentsForBranchResult']['any'] ?? '';
            if ($xml !== '') {
                $agents = $this->parseAgentDataSet($xml);
            }
        }

        $byUser = User::query()
            ->whereNotNull('email')
            ->get(['id', 'name', 'email'])
            ->keyBy(fn ($u) => strtolower(trim($u->email)));

        foreach ($agents as &$a) {
            $key = strtolower(trim($a['email'] ?? ''));
            $match = $byUser[$key] ?? null;
            $a['corex_user_id']   = $match?->id;
            $a['corex_user_name'] = $match?->name;
            $a['is_duplicate_external_ref'] = false;
        }
        unset($a);

        $emailCounts = array_count_values(array_map(
            fn ($a) => strtolower(trim($a['email'] ?? '')),
            $agents
        ));
        foreach ($agents as &$a) {
            $key = strtolower(trim($a['email'] ?? ''));
            if ($key !== '' && ($emailCounts[$key] ?? 0) > 1) {
                $a['is_duplicate_external_ref'] = true;
            }
        }
        unset($a);

        return view('admin.pp.agents', [
            'agents' => $agents,
            'error'  => $error,
        ]);
    }

    /**
     * Deactivate a PP agent by its encrypted PrivatePropertyAgentId.
     * Used to clean up duplicate profiles that don't map to a CoreX user.
     * Sends UpdateAgent with Active=false.
     */
    public function deactivateByEncryptedId(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('manage_users'), 403);

        $validated = $request->validate([
            'pp_encrypted_id' => 'required|string|max:500',
            'agent_id'        => 'required|string|max:100',
            'first_name'      => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'email'           => 'nullable|string|max:200',
            'tel_cell'        => 'nullable|string|max:50',
        ]);

        $payload = [
            'AgentId'                => $validated['agent_id'],
            'FirstName'              => $validated['first_name'] ?? '',
            'LastName'               => $validated['last_name'] ?? '',
            'Email'                  => $validated['email'] ?? '',
            'TelCell'                => $validated['tel_cell'] ?? '',
            'TelWork'                => $validated['tel_cell'] ?? '',
            'TelHome'                => '',
            'Active'                 => false,
            'BranchId'               => config('services.private_property.branch_guid'),
            'PrivatePropertyAgentId' => $validated['pp_encrypted_id'],
            'PrivysealAlias'         => '',
        ];

        $result = $this->soapClient->updateAgent($payload);

        if (isset($result['error']) && $result['error'] === true) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'UpdateAgent (deactivate) failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'PP agent profile ' . $validated['agent_id'] . ' deactivated',
        ]);
    }

    /**
     * Parse the DataSet XML returned in GetAllAgentsForBranchResult.any.
     */
    private function parseAgentDataSet(string $xml): array
    {
        $rows = [];
        try {
            $previous = libxml_use_internal_errors(true);
            $doc = new \DOMDocument();
            $doc->loadXML($xml);
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

            $nodes = $xpath->query('//NewDataSet/Agents');
            foreach ($nodes as $node) {
                $row = [];
                foreach ($node->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $row[$child->nodeName] = trim($child->textContent);
                    }
                }
                $rows[] = [
                    'id'                       => $row['ID'] ?? '',
                    'agent_id'                 => $row['AgentId'] ?? '',
                    'pp_encrypted_id'          => $row['PrivatePropertyAgentId'] ?? '',
                    'first_name'               => $row['firstName'] ?? '',
                    'last_name'                => $row['LastName'] ?? '',
                    'email'                    => $row['email'] ?? '',
                    'contact_number'           => trim($row['ContactNumber'] ?? ''),
                    'image_url'                => $row['AgentImageUrl'] ?? '',
                ];
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        } catch (\Throwable $e) {
            // fall through with empty rows
        }

        usort($rows, fn ($a, $b) => strcmp(
            ($a['email'] ?? '') . ($a['agent_id'] ?? ''),
            ($b['email'] ?? '') . ($b['agent_id'] ?? '')
        ));

        return $rows;
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
