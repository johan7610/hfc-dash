<?php

namespace App\Services\PrivateProperty;

use Illuminate\Support\Facades\Log;

class PrivatePropertySoapClient
{
    private PrivatePropertyTokenService $tokenService;
    private ?\SoapClient $client = null;

    public function __construct(PrivatePropertyTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    private function soapClient(): \SoapClient
    {
        if ($this->client === null) {
            $wsdl = config('services.private_property.wsdl');

            $this->client = new \SoapClient($wsdl, [
                'trace'              => true,
                'exceptions'         => true,
                'cache_wsdl'         => WSDL_CACHE_BOTH,
                'connection_timeout' => 30,
                'default_socket_timeout' => 60,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer'      => false,
                        'verify_peer_name' => false,
                    ],
                    'http' => [
                        'timeout' => 60,
                    ],
                ]),
            ]);
        }

        return $this->client;
    }

    private function buildToken(): array
    {
        return $this->tokenService->generate();
    }

    /**
     * Execute a raw SOAP call with retry on timeout.
     */
    public function call(string $method, array $params): array
    {
        $this->log('info', "SOAP call: {$method}", ['params' => $this->sanitizeForLog($params)]);

        $maxAttempts = 2;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                // Set PHP's global socket timeout for this call
                $oldTimeout = ini_get('default_socket_timeout');
                ini_set('default_socket_timeout', 120);

                $response = $this->soapClient()->__soapCall($method, [$params]);

                ini_set('default_socket_timeout', $oldTimeout);

                $result = json_decode(json_encode($response), true) ?? [];

                $this->log('info', "SOAP response: {$method}", ['response' => $result]);

                return $result;
            } catch (\SoapFault $e) {
                ini_set('default_socket_timeout', $oldTimeout ?? 60);
                $lastError = $e;

                // Retry only on timeout errors
                $isTimeout = str_contains($e->getMessage(), 'Error Fetching http headers')
                          || str_contains($e->getMessage(), 'Could not connect')
                          || str_contains($e->getMessage(), 'timed out');

                if ($isTimeout && $attempt < $maxAttempts) {
                    $this->log('warning', "SOAP timeout on {$method}, retrying (attempt {$attempt}/{$maxAttempts})");
                    $this->client = null; // Force fresh connection
                    sleep(3);
                    continue;
                }

                $this->log('error', "SOAP fault: {$method}", [
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                return ['error' => true, 'message' => $e->getMessage()];
            }
        }

        // Should never reach here, but just in case
        return ['error' => true, 'message' => $lastError?->getMessage() ?? 'Unknown error'];
    }

    /**
     * Get branch details from PP.
     * WSDL: GetBranchDetails { guid BranchId, SecurityToken Token }
     */
    public function getBranchDetails(): array
    {
        return $this->call('GetBranchDetails', [
            'BranchId' => config('services.private_property.branch_guid'),
            'Token'    => $this->buildToken(),
        ]);
    }

    /**
     * Update/create an agent record on PP.
     * WSDL: UpdateAgent { Agent Agent, SecurityToken Token }
     */
    public function updateAgent(array $agentData): array
    {
        return $this->call('UpdateAgent', [
            'Agent' => $agentData,
            'Token' => $this->buildToken(),
        ]);
    }

    /**
     * Submit/update a listing on PP.
     * WSDL: UpdateListing { Listing ListingImport, SecurityToken Token }
     */
    public function updateListing(array $listingData): array
    {
        return $this->call('UpdateListing', [
            'ListingImport' => $listingData,
            'Token'         => $this->buildToken(),
        ]);
    }

    /**
     * Get listing status from PP.
     * WSDL: GetListingStatus { guid BranchId, string PropertyId, SecurityToken Token }
     */
    public function getListingStatus(string $propertyId): array
    {
        return $this->call('GetListingStatus', [
            'BranchId'   => config('services.private_property.branch_guid'),
            'PropertyId' => $propertyId,
            'Token'      => $this->buildToken(),
        ]);
    }

    /**
     * Deactivate a listing on PP.
     * WSDL: ListingStatusUpdate { guid BranchId, string PropertyId, ListingType ListingType, PropertyStatus PropertyStatus, SecurityToken Token }
     */
    public function deactivateListing(string $propertyId, string $listingType = 'Sale'): array
    {
        return $this->call('ListingStatusUpdate', [
            'BranchId'       => config('services.private_property.branch_guid'),
            'PropertyId'     => $propertyId,
            'ListingType'    => $listingType,
            'PropertyStatus' => 'Inactive',
            'Token'          => $this->buildToken(),
        ]);
    }

    /**
     * Get listing event feed for the branch.
     * WSDL: GetListingEventFeedByBranch { guid UniqueBranchId, SecurityToken Token, string continuationKey, dateTime startDateTime }
     */
    public function getListingEventFeed(?string $continuationKey = null, ?string $startDateTime = null): array
    {
        $params = [
            'UniqueBranchId'  => config('services.private_property.branch_guid'),
            'Token'           => $this->buildToken(),
            'continuationKey' => $continuationKey ?? '',
            'startDateTime'   => $startDateTime ?? now()->subDay()->toIso8601String(),
        ];

        return $this->call('GetListingEventFeedByBranch', $params);
    }

    /**
     * Get PP reference number by listing.
     * WSDL: GetReferenceNumberByListing { guid BranchId, string UniqueListingID, ListingType listingType, SecurityToken Token }
     */
    public function getReferenceNumber(string $propertyId, string $listingType = 'Sale'): array
    {
        return $this->call('GetReferenceNumberByListing', [
            'BranchId'        => config('services.private_property.branch_guid'),
            'UniqueListingID' => $propertyId,
            'listingType'     => $listingType,
            'Token'           => $this->buildToken(),
        ]);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel('private_property')->{$level}($message, $context);
    }

    private function sanitizeForLog(array $params): array
    {
        if (isset($params['Token']['Digest'])) {
            $params['Token']['Digest'] = '***';
        }
        return $params;
    }
}
