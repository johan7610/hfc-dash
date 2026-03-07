<?php

namespace App\Http\Controllers\Docuperfect;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Docuperfect\Document;
use App\Models\Docuperfect\DocumentType;
use App\Models\Docuperfect\Flow;
use App\Models\Docuperfect\NamedField;
use App\Models\Docuperfect\SignatureTemplate;
use App\Models\Docuperfect\Template;
use App\Models\Property;
use App\Services\Docuperfect\SignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ESignWizardController extends Controller
{
    /**
     * Test render: minimal page to verify template page images + field overlays.
     */
    public function testRender(Request $request, $templateId)
    {
        $template = Template::findOrFail($templateId);

        $pageImages = [];
        for ($n = 0; $n < $template->page_count; $n++) {
            $pageImages[] = route('docuperfect.page.image', ['id' => $template->id, 'page' => $n]);
        }

        return view('docuperfect.esign.test-render', [
            'template'   => $template,
            'pageImages' => $pageImages,
            'fields'     => $template->fields_json ?? [],
        ]);
    }

    /**
     * Show the wizard — fresh create (step 1: pick template).
     */
    public function create(Request $request)
    {
        $user = $request->user();

        $templates = Template::active()
            ->visibleTo($user)
            ->where('page_count', '>', 0)
            ->with(['documentType', 'branches'])
            ->orderBy('name')
            ->get();

        $documentTypes = DocumentType::orderBy('sort_order')->get();

        $drafts = Flow::where('user_id', $user->id)
            ->whereIn('status', ['active', 'draft'])
            ->with('template')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('docuperfect.esign.wizard', [
            'templates'     => $templates,
            'documentTypes' => $documentTypes,
            'drafts'        => $drafts,
            'flow'          => null,
            'template'      => null,
            'fields'        => [],
            'pageImages'    => [],
            'recipients'    => [],
            'stepData'      => [],
            'currentStep'   => 1,
        ]);
    }

    /**
     * Create a new flow from step 1 and redirect to step 2.
     */
    public function store(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:docuperfect_templates,id',
        ]);

        $template = Template::findOrFail($request->template_id);

        // Copy template fields into flow step_data (same as DocumentController::store)
        $fieldsJson = $template->fields_json ?? [];

        $flow = Flow::create([
            'type'         => 'esign',
            'template_id'  => $request->template_id,
            'user_id'      => $request->user()->id,
            'current_step' => 2,
            'step_data'    => [
                'template' => [
                    'template_id' => (int) $request->template_id,
                ],
                'fields' => $fieldsJson,
            ],
            'status' => 'active',
        ]);

        $url = route('docuperfect.esign.step', ['flow' => $flow->id, 'step' => 2]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => $url]);
        }

        return redirect($url);
    }

    /**
     * Load a specific step of an existing flow.
     */
    public function showStep(Request $request, $flowId, $step)
    {
        $flow = Flow::where('user_id', $request->user()->id)
            ->findOrFail($flowId);

        $flow->load('template.documentType');

        $step = (int) $step;
        $template = $flow->template;
        $stepData = $flow->step_data ?? [];

        // Build page image URLs (same as DocumentController edit view)
        $pageImages = [];
        if ($template && $template->page_count > 0) {
            for ($n = 0; $n < $template->page_count; $n++) {
                $pageImages[] = route('docuperfect.page.image', ['id' => $template->id, 'page' => $n]);
            }
        }

        // Fields: use flow's stored copy (copied from template on creation),
        // with any values filled during wizard steps merged in
        $fields = $stepData['fields'] ?? ($template->fields_json ?? []);

        // Auto-fill fields from wizard step data (property, recipients, details)
        $fields = $this->autoFillFields($fields, $stepData);

        // Separate fields into creator vs signer groups for step 5
        $creatorFields = [];
        $signerFields = [];
        foreach ($fields as $idx => $field) {
            $role = $field['assignedTo'] ?? $field['assigned_to'] ?? 'creator';
            $fieldWithIndex = $field;
            $fieldWithIndex['_index'] = $idx;

            if (in_array($role, ['creator', 'user', 'agent'])) {
                $creatorFields[] = $fieldWithIndex;
            } else {
                $signerFields[] = $fieldWithIndex;
            }
        }

        // Recipients from step data
        $recipients = $stepData['recipients']['recipients'] ?? [];

        // Templates list (for step navigation back to step 1)
        $templates = Template::active()
            ->visibleTo($request->user())
            ->where('page_count', '>', 0)
            ->with(['documentType', 'branches'])
            ->orderBy('name')
            ->get();

        $documentTypes = DocumentType::orderBy('sort_order')->get();

        return view('docuperfect.esign.wizard', [
            'flow'           => $flow,
            'step'           => $step,
            'template'       => $template,
            'fields'         => $fields,
            'creatorFields'  => $creatorFields,
            'signerFields'   => $signerFields,
            'pageImages'     => $pageImages,
            'recipients'     => $recipients,
            'stepData'       => $stepData,
            'templates'      => $templates,
            'documentTypes'  => $documentTypes,
            'drafts'         => collect(),
            'currentStep'    => $step,
        ]);
    }

    /**
     * Save step data and advance.
     */
    public function saveStep(Request $request, $flowId, $step)
    {
        $flow = Flow::where('user_id', $request->user()->id)
            ->findOrFail($flowId);

        $step = (int) $step;
        $stepKey = $this->stepKey($step);

        // Get JSON data
        $data = $request->input('data', []);
        if (empty($data) && $request->isJson()) {
            $data = $request->json('data', []);
        }

        // Merge step data into flow
        $stepData = $flow->step_data ?? [];
        $stepData[$stepKey] = $data;

        // For step 5 (fill_review): merge field values back into the main fields array
        if ($stepKey === 'fill_review' && !empty($data['fieldValues'])) {
            $fields = $stepData['fields'] ?? [];
            foreach ($data['fieldValues'] as $fieldId => $value) {
                foreach ($fields as &$field) {
                    if (($field['id'] ?? null) == $fieldId) {
                        $field['value'] = $value;
                        break;
                    }
                }
                unset($field);
            }
            $stepData['fields'] = $fields;
        }

        $flow->step_data = $stepData;

        // Handle property/contact linking
        if ($stepKey === 'property' && !empty($data['property_id'])) {
            $flow->property_id = $data['property_id'];
        }
        if ($stepKey === 'recipients' && !empty($data['recipients'][0]['_contact_id'])) {
            $flow->contact_id = $data['recipients'][0]['_contact_id'];
        }

        // Advance step (only forward, never backward)
        $nextStep = $step + 1;
        $flow->current_step = max($flow->current_step, $nextStep);
        $flow->save();

        return response()->json([
            'success'   => true,
            'next_step' => $nextStep,
            'redirect'  => route('docuperfect.esign.step', ['flow' => $flow->id, 'step' => $nextStep]),
        ]);
    }

    /**
     * Save current step as draft without advancing.
     */
    public function saveDraft(Request $request, $flowId)
    {
        $flow = Flow::where('user_id', $request->user()->id)
            ->findOrFail($flowId);

        $step = (int) $request->input('step', $flow->current_step);
        $stepKey = $this->stepKey($step);

        $data = $request->input('data', []);
        if (empty($data) && $request->isJson()) {
            $data = $request->json('data', []);
        }

        $stepData = $flow->step_data ?? [];
        $stepData[$stepKey] = $data;

        // Merge field values for fill_review
        if ($stepKey === 'fill_review' && !empty($data['fieldValues'])) {
            $fields = $stepData['fields'] ?? [];
            foreach ($data['fieldValues'] as $fieldId => $value) {
                foreach ($fields as &$field) {
                    if (($field['id'] ?? null) == $fieldId) {
                        $field['value'] = $value;
                        break;
                    }
                }
                unset($field);
            }
            $stepData['fields'] = $fields;
        }

        $flow->step_data = $stepData;
        $flow->status = 'draft';
        $flow->save();

        return response()->json(['success' => true, 'message' => 'Draft saved']);
    }

    /**
     * API: search properties for autocomplete.
     */
    public function searchProperties(Request $request)
    {
        $q = $request->input('q', '');

        $properties = Property::where(function ($query) use ($q) {
            $query->where('title', 'like', "%{$q}%")
                ->orWhere('suburb', 'like', "%{$q}%");
        })
            ->limit(20)
            ->get(['id', 'title', 'suburb', 'property_type', 'beds', 'baths', 'size_m2', 'erf_size_m2']);

        return response()->json($properties);
    }

    /**
     * API: search contacts for autocomplete.
     */
    public function searchContacts(Request $request)
    {
        $q = $request->input('q', '');

        $contacts = Contact::where('first_name', 'like', "%{$q}%")
            ->orWhere('last_name', 'like', "%{$q}%")
            ->orWhere('email', 'like', "%{$q}%")
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        return response()->json($contacts);
    }

    /**
     * API: get template pages + fields for preview.
     */
    public function templatePages(Request $request, $templateId)
    {
        $template = Template::findOrFail($templateId);
        return response()->json([
            'page_count'    => $template->page_count,
            'pages'         => $template->pageImages,
            'fields'        => $template->fields_json ?? [],
            'wizard_config' => $template->wizard_config,
            'name'          => $template->name,
            'template_type' => $template->template_type,
        ]);
    }

    /**
     * Soft-delete a draft flow (set status to abandoned).
     */
    public function destroy(Request $request, $flowId)
    {
        $flow = Flow::where('user_id', $request->user()->id)
            ->findOrFail($flowId);

        $flow->status = 'abandoned';
        $flow->save();

        return response()->json(['success' => true]);
    }

    /**
     * Create Document + SignatureTemplate + SignatureRequests from the wizard flow,
     * then redirect to the existing agent signing interface.
     */
    public function prepareSigning(Request $request, $flowId)
    {
        $user = $request->user();
        $flow = Flow::where('user_id', $user->id)->findOrFail($flowId);
        $flow->load('template');

        $template = $flow->template;
        $stepData = $flow->step_data ?? [];
        $fields = $stepData['fields'] ?? ($template->fields_json ?? []);

        // Auto-fill fields one final time
        $fields = $this->autoFillFields($fields, $stepData);

        // Also merge any fill_review field values
        $frValues = $stepData['fill_review']['fieldValues'] ?? [];
        foreach ($frValues as $fieldId => $value) {
            foreach ($fields as &$field) {
                if (($field['id'] ?? null) == $fieldId && $value !== '') {
                    $field['value'] = $value;
                }
            }
            unset($field);
        }

        $recipients = $stepData['recipients']['recipients'] ?? [];
        $signingSetup = $stepData['signing_setup'] ?? [];
        $propertyAddress = $stepData['property']['address'] ?? $stepData['property']['title'] ?? '';

        // Build document name
        $firstRecipientName = '';
        foreach ($recipients as $r) {
            if (($r['role'] ?? '') !== 'agent' && !empty($r['name'])) {
                $firstRecipientName = $r['name'];
                break;
            }
        }
        $docName = $template->name;
        if ($firstRecipientName) $docName .= ' — ' . $firstRecipientName;
        $docName .= ' — ' . now()->format('Y-m-d');

        $signatureService = app(SignatureService::class);

        $result = DB::transaction(function () use ($user, $flow, $template, $fields, $recipients, $signingSetup, $docName, $propertyAddress, $signatureService) {
            // 1. Create Document
            $document = Document::create([
                'name'             => $docName,
                'template_id'      => $template->id,
                'fields_json'      => $fields,
                'owner_id'         => $user->id,
                'branch_id'        => $user->effectiveBranchId(),
                'property_address' => $propertyAddress,
                'property_id'      => $flow->property_id,
                'document_type'    => $template->template_type,
            ]);

            // 2. Create SignatureTemplate
            $roleAliases = [
                'landlord' => 'landlord', 'tenant' => 'tenant',
                'buyer' => 'buyer', 'seller' => 'seller',
                'agent' => 'agent', 'witness' => 'witness',
            ];

            $parties = [];
            $signingOrder = [];
            foreach ($recipients as $r) {
                $role = $roleAliases[$r['role'] ?? 'other'] ?? ($r['role'] ?? 'other');
                $parties[] = [
                    'role'      => $role,
                    'name'      => $r['name'] ?? '',
                    'email'     => $r['email'] ?? '',
                    'id_number' => $r['id_number'] ?? '',
                ];
                $signingOrder[] = $role;
            }

            $documentHash = hash('sha256', json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $sigTemplate = SignatureTemplate::create([
                'document_id'        => $document->id,
                'document_hash'      => $documentHash,
                'status'             => SignatureTemplate::STATUS_DRAFT,
                'parties_json'       => $parties,
                'signing_order_json' => $signingOrder,
                'created_by'         => $user->id,
            ]);

            // 3. Create SignatureRequests for each recipient
            foreach ($recipients as $i => $r) {
                $role = $roleAliases[$r['role'] ?? 'other'] ?? ($r['role'] ?? 'other');
                $action = $signingSetup[$i]['action'] ?? ($role === 'agent' ? 'signs_now' : 'send_after');
                $email = $r['email'] ?? '';

                // No email = deferred
                if (empty($email) && $role !== 'agent') {
                    $action = 'sign_later';
                }

                $signatureService->createSigningRequest(
                    $sigTemplate,
                    $role,
                    $r['name'] ?? '',
                    $email,
                    $r['id_number'] ?? null,
                    null,
                    $user
                );
            }

            // 4. Convert template signature zones to markers
            $markerCount = $signatureService->convertZonesToMarkers($sigTemplate);

            // Fallback: create markers from fields_json sign/initial fields
            if ($markerCount === 0) {
                $markerCount = $signatureService->convertFieldsJsonToMarkers($sigTemplate, $fields);
            }

            // Final fallback: create one default signature marker per party
            if ($markerCount === 0) {
                $signatureService->createDefaultMarkers($sigTemplate);
            }

            // 5. Transition to signing status
            $signatureService->sendForSigning($sigTemplate, $user);

            // 6. Link document to flow
            $flowStepData = $flow->step_data ?? [];
            $flowStepData['document_id'] = $document->id;
            $flowStepData['signature_template_id'] = $sigTemplate->id;
            $flow->step_data = $flowStepData;
            $flow->current_step = 7;
            $flow->save();

            return $document;
        });

        // Store wizard context in session so signComplete redirects back to wizard
        session(['esign_wizard_flow_id' => $flow->id]);

        // Redirect to existing agent signing interface
        $signingUrl = route('docuperfect.signatures.sign', ['document' => $result->id]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => $signingUrl]);
        }

        return redirect($signingUrl);
    }

    /**
     * Success page after agent completes signing via wizard flow.
     */
    public function signingComplete(Request $request, $flowId)
    {
        $user = $request->user();
        $flow = Flow::where('user_id', $user->id)->findOrFail($flowId);
        $flow->load('template');

        $stepData = $flow->step_data ?? [];
        $documentId = $stepData['document_id'] ?? null;
        $document = $documentId ? Document::find($documentId) : null;
        $sigTemplate = $document ? $document->signatureTemplate : null;

        $recipients = $stepData['recipients']['recipients'] ?? [];
        $nextRecipient = null;
        foreach ($recipients as $r) {
            if (($r['role'] ?? '') !== 'agent' && !empty($r['email'])) {
                $nextRecipient = $r;
                break;
            }
        }

        // Mark flow as completed
        $flow->status = 'completed';
        $flow->save();

        return view('docuperfect.esign.signing-complete', [
            'flow'          => $flow,
            'document'      => $document,
            'sigTemplate'   => $sigTemplate,
            'nextRecipient' => $nextRecipient,
            'template'      => $flow->template,
        ]);
    }

    /**
     * Auto-fill template fields from wizard step data.
     *
     * Uses source_type/source_column/source_contact_type from
     * docuperfect_named_fields to resolve each field's value.
     */
    private function autoFillFields(array $fields, array $stepData): array
    {
        // Load named field source mappings
        $namedFieldMappings = DB::table('docuperfect_named_fields')
            ->whereNotNull('source_type')
            ->where('source_type', '!=', 'manual')
            ->get()
            ->keyBy('id');

        // Build data pools from step_data
        $property   = $stepData['property'] ?? [];
        $recipients = $stepData['recipients']['recipients'] ?? [];
        $details    = $stepData['details'] ?? [];
        $agent      = auth()->user();

        // Build contact lookup by role (ucfirst to match source_contact_type)
        $contactsByRole = [];
        foreach ($recipients as $r) {
            $role = ucfirst($r['role'] ?? '');
            if ($role && !isset($contactsByRole[$role])) {
                $contactsByRole[$role] = $r;
            }
        }

        // Role aliases: wizard uses "landlord"/"tenant", DB uses "Lessor"/"Lessee"
        $roleAliases = [
            'Landlord' => 'Lessor', 'Tenant' => 'Lessee',
            'Lessor' => 'Lessor', 'Lessee' => 'Lessee',
            'Seller' => 'Seller', 'Buyer' => 'Buyer',
        ];

        // Merge aliased roles into contactsByRole
        foreach ($roleAliases as $wizardRole => $dbRole) {
            if (isset($contactsByRole[$wizardRole]) && !isset($contactsByRole[$dbRole])) {
                $contactsByRole[$dbRole] = $contactsByRole[$wizardRole];
            }
        }

        foreach ($fields as &$field) {
            if (!empty($field['value'])) {
                continue; // Don't overwrite existing values
            }

            $namedFieldId = $field['named_field_id'] ?? null;
            if (!$namedFieldId || !isset($namedFieldMappings[$namedFieldId])) {
                continue;
            }

            $mapping = $namedFieldMappings[$namedFieldId];
            $sourceType   = $mapping->source_type;
            $sourceColumn = $mapping->source_column;
            $contactType  = $mapping->source_contact_type;

            $value = $this->resolveFieldValue($sourceType, $sourceColumn, $contactType, $property, $contactsByRole, $details, $agent);

            if ($value !== null && $value !== '') {
                $field['value'] = (string) $value;
            }
        }
        unset($field);

        return $fields;
    }

    /**
     * Resolve a single field's value from its source mapping.
     */
    private function resolveFieldValue(string $sourceType, ?string $sourceColumn, ?string $contactType, array $property, array $contactsByRole, array $details, $agent)
    {
        if (!$sourceColumn) {
            return null;
        }

        switch ($sourceType) {
            case 'property':
                return $this->resolvePropertyValue($sourceColumn, $property, $details);

            case 'contact':
                $contact = $contactsByRole[$contactType] ?? null;
                if (!$contact) return null;
                return $this->resolveContactValue($sourceColumn, $contact);

            case 'agent':
                if ($sourceColumn === 'name') return $agent->name ?? '';
                return null;

            case 'computed':
                return $this->resolveComputedValue($sourceColumn, $property, $details);

            case 'static':
                return $sourceColumn; // The column IS the literal value

            default:
                return null;
        }
    }

    private function resolvePropertyValue(string $column, array $property, array $details)
    {
        return match ($column) {
            'address'           => $property['address'] ?? $property['title'] ?? '',
            'suburb'            => $property['suburb'] ?? '',
            'address+suburb'    => trim(($property['address'] ?? $property['title'] ?? '') . ', ' . ($property['suburb'] ?? ''), ', '),
            'rental_amount'     => $details['monthly_rental'] ?? $property['rental_amount'] ?? '',
            'deposit_amount'    => $details['deposit'] ?? $property['deposit_amount'] ?? '',
            'commission_percent'=> $details['commission'] ?? $details['commission_percent'] ?? '',
            'lease_start_date'  => $details['lease_start'] ?? '',
            'lease_end_date'    => $details['lease_end'] ?? '',
            'property_number'   => $property['erf'] ?? $property['erf_number'] ?? $property['property_number'] ?? '',
            'complex_name'      => $property['complex_name'] ?? '',
            'unit_number'       => $property['unit_number'] ?? '',
            'district'          => $property['district'] ?? '',
            'price'             => $details['price'] ?? $property['price'] ?? '',
            'expiry_date'       => $details['expiry_date'] ?? $property['expiry_date'] ?? '',
            default             => '',
        };
    }

    private function resolveContactValue(string $column, array $contact)
    {
        return match ($column) {
            'first_name+last_name' => $contact['name'] ?? '',
            'address'              => $contact['address'] ?? '',
            'id_number'            => $contact['id_number'] ?? '',
            'email'                => $contact['email'] ?? '',
            'phone'                => $contact['cell'] ?? $contact['phone'] ?? '',
            'bank_name'            => $contact['bank_name'] ?? '',
            'bank_account_name'    => $contact['bank_account_name'] ?? '',
            'bank_account_number'  => $contact['bank_account_number'] ?? '',
            'bank_branch_name'     => $contact['bank_branch_name'] ?? '',
            default                => '',
        };
    }

    private function resolveComputedValue(string $column, array $property, array $details)
    {
        $leaseStart = $details['lease_start'] ?? '';
        $price = $details['price'] ?? $details['monthly_rental'] ?? $property['price'] ?? '';

        return match ($column) {
            'lease_start_day' => $leaseStart ? (int) date('d', strtotime($leaseStart)) : '',
            'lease_start_month' => $leaseStart ? date('F', strtotime($leaseStart)) : '',
            'lease_start_year' => $leaseStart ? date('Y', strtotime($leaseStart)) : '',
            'price_in_words' => $price ? $this->numberToWords((int) $price) : '',
            default => '',
        };
    }

    private function numberToWords(int $number): string
    {
        if ($number === 0) return 'zero';

        $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
                 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
                 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        $convert = function (int $n) use (&$convert, $ones, $tens): string {
            if ($n < 20) return $ones[$n];
            if ($n < 100) return $tens[(int)($n / 10)] . ($n % 10 ? '-' . $ones[$n % 10] : '');
            if ($n < 1000) return $ones[(int)($n / 100)] . ' hundred' . ($n % 100 ? ' and ' . $convert($n % 100) : '');
            if ($n < 1000000) return $convert((int)($n / 1000)) . ' thousand' . ($n % 1000 ? ' ' . $convert($n % 1000) : '');
            return $convert((int)($n / 1000000)) . ' million' . ($n % 1000000 ? ' ' . $convert($n % 1000000) : '');
        };

        return ucfirst($convert($number));
    }

    private function stepKey(int $step): string
    {
        return match ($step) {
            1 => 'template',
            2 => 'property',
            3 => 'recipients',
            4 => 'details',
            5 => 'fill_review',
            6 => 'signing_setup',
            default => "step_{$step}",
        };
    }
}
