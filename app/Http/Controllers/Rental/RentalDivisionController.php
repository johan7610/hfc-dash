<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\Docuperfect\Document as DocuperfectDocument;
use App\Models\Docuperfect\LeaseRecord;
use App\Models\Rental\RentalDocumentType;
use App\Models\Rental\RentalProperty;
use App\Services\Docuperfect\SignatureService;
use Illuminate\Http\Request;

class RentalDivisionController extends Controller
{
    public function __construct(private SignatureService $signatureService)
    {
    }

    /**
     * Rental Division dashboard — high-level overview with metric tiles.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $data = $this->signatureService->getRentalDashboardData($user);

        $counts = [
            'needs_approval'      => $data['counts']['pending_approval'],
            'draft'               => $data['counts']['draft'],
            'ready_to_sign'       => $data['counts']['ready_to_sign'],
            'in_progress'         => $data['counts']['awaiting_signatures'],
            'completed'           => $data['counts']['completed'],
            'active_leases'       => $data['activeLeaseCount'],
            'expiring_soon'       => $data['upcomingRenewals']->count(),
        ];

        return view('rental.dashboard', compact('counts'));
    }

    /**
     * Electronic Signatures — reuses existing rental dashboard data + view.
     */
    public function signatures(Request $request)
    {
        $user = $request->user();
        $data = $this->signatureService->getRentalDashboardData($user);

        $properties = RentalProperty::where('is_active', true)->orderBy('full_address')->get();
        $documentTypes = RentalDocumentType::where('is_active', true)->orderBy('sort_order')->get();

        // All documents across all groups for property grouping
        $allDocuments = collect();
        foreach ($data['groups'] as $group) {
            $allDocuments = $allDocuments->merge($group);
        }

        // Group documents by property for the Properties section
        $documentsByProperty = $allDocuments->groupBy(function ($doc) {
            return $doc->property_id ?? 0;
        });

        // Completed lease-type documents for Active Leases section
        $completedLeaseDocs = $data['groups']['completed']->filter(function ($doc) {
            $type = $doc->document_type ?? '';
            return stripos($type, 'lease') !== false;
        });

        return view('rental.signatures', [
            'groups'               => $data['groups'],
            'rejected'             => $data['rejected'],
            'signatureTemplates'   => $data['signatureTemplates'],
            'fieldStatus'          => $data['fieldStatus'],
            'counts'               => $data['counts'],
            'upcomingRenewals'     => $data['upcomingRenewals'],
            'expiredLeases'        => $data['expiredLeases'],
            'activeLeases'         => $data['activeLeases'],
            'activeLeaseCount'     => $data['activeLeaseCount'],
            'lastUpdate'           => $data['lastUpdate'] ?? '',
            'user'                 => $user,
            'properties'           => $properties,
            'documentTypes'        => $documentTypes,
            'documentsByProperty'  => $documentsByProperty,
            'completedLeaseDocs'   => $completedLeaseDocs,
        ]);
    }

    /**
     * AJAX: Assign document type and/or property to a document.
     */
    public function assignMetadata(Request $request, DocuperfectDocument $document)
    {
        $validated = $request->validate([
            'document_type_id' => 'nullable|exists:rental_document_types,id',
            'property_id' => 'nullable|exists:rental_properties,id',
        ]);

        $updates = [];

        if (array_key_exists('document_type_id', $validated)) {
            $type = RentalDocumentType::find($validated['document_type_id']);
            $updates['document_type'] = $type ? $type->slug : null;
        }

        if (array_key_exists('property_id', $validated)) {
            $property = RentalProperty::find($validated['property_id']);
            $updates['property_id'] = $property?->id;
            $updates['property_address'] = $property?->full_address;
        }

        $document->update($updates);

        return response()->json(['success' => true, 'document' => $document->fresh()]);
    }

    /**
     * AJAX: Set lease expiry date on a document.
     */
    public function setExpiry(Request $request, DocuperfectDocument $document)
    {
        $validated = $request->validate([
            'lease_expiry_date' => 'required|date|after:today',
        ]);

        $document->update(['lease_expiry_date' => $validated['lease_expiry_date']]);

        return response()->json([
            'success' => true,
            'expiry' => $document->fresh()->lease_expiry_date->format('Y-m-d'),
        ]);
    }

    /**
     * Active Leases — completed signed leases currently active.
     */
    public function activeLeases(Request $request)
    {
        $user = $request->user();

        $leases = LeaseRecord::visibleTo($user)
            ->whereIn('status', [LeaseRecord::STATUS_ACTIVE, LeaseRecord::STATUS_EXPIRING_SOON])
            ->with(['document', 'signatureTemplate'])
            ->orderBy('lease_end_date')
            ->get();

        return view('rental.active-leases', compact('leases'));
    }

    /**
     * Expired Leases.
     */
    public function expiredLeases(Request $request)
    {
        $user = $request->user();

        $leases = LeaseRecord::visibleTo($user)
            ->where('status', LeaseRecord::STATUS_EXPIRED)
            ->with(['document', 'signatureTemplate'])
            ->orderByDesc('lease_end_date')
            ->get();

        return view('rental.expired-leases', compact('leases'));
    }

    /**
     * Rental Settings — placeholder.
     */
    public function settings()
    {
        return view('rental.settings');
    }
}
