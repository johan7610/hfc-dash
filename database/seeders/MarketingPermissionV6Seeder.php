<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Marketing Permission V6 — sales mandate web template.
 *
 * Source: resources/docs/source/HFC_Marketing_Permission_V6.docx
 * Blade : resources/views/docuperfect/web-templates/marketing-permission-v6.blade.php
 *
 * Field mechanism:
 *  - render_type='web' + blade_view + is_esign=true → e-sign wizard Step 1.
 *  - fields_json drives the wizard; field_mappings stays NULL so the
 *    wizard uses fields_json directly and preserves choice `options`
 *    (a web template WITH field_mappings rebuilds via
 *    buildFieldsFromMappings() which drops options).
 *  - POPULATION: recipient/property fields carry a named_field_id →
 *    autoFillFields() resolves seller + property data straight from the
 *    linked recipient/property (the proven source-mapping path; does NOT
 *    need field_mappings or template_type=cds). Choice fields stay
 *    agent/seller-selected (not auto-resolvable).
 *  - Signatures: the blade uses the shared recipient-driven
 *    signature-block partial.
 * Idempotent: updateOrInsert keyed by name; named fields find-or-create
 * by (source_type, source_column, source_contact_type) so ids are
 * environment-independent.
 */
class MarketingPermissionV6Seeder extends Seeder
{
    public const TEMPLATE_NAME = 'Marketing Permission V6';

    /**
     * Find-or-create a docuperfect_named_fields row by its source triple
     * and return its id. Keeps autoFillFields source resolution stable
     * across environments without hard-coding ids.
     */
    private function nf(string $name, string $sourceType, string $sourceColumn, ?string $contactType): int
    {
        $q = DB::table('docuperfect_named_fields')
            ->where('source_type', $sourceType)
            ->where('source_column', $sourceColumn)
            ->whereNull('deleted_at');
        $q = $contactType === null
            ? $q->whereNull('source_contact_type')
            : $q->where('source_contact_type', $contactType);

        $id = $q->value('id');
        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('docuperfect_named_fields')->insertGetId([
            'name'                => $name,
            'field_type'          => 'text',
            'source_type'         => $sourceType,
            'source_column'       => $sourceColumn,
            'source_contact_type' => $contactType,
            'sort_order'          => 900,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    public function run(): void
    {
        // Resolvable source-mapped named fields (find-or-create).
        $nfSellerName  = $this->nf('Seller Name', 'contact', 'first_name+last_name', 'Seller');
        $nfSellerId    = $this->nf('Seller ID Number', 'contact', 'id_number', 'Seller');
        $nfSellerEmail = $this->nf('Seller Contact (Email)', 'contact', 'email', 'Seller');
        $nfPropAddr    = $this->nf('Property Address', 'property', 'address+suburb', null);
        $nfPropSuburb  = $this->nf('Property Suburb', 'property', 'suburb', null);
        $nfPropDistrict= $this->nf('Property District', 'property', 'district', null);
        $nfPropComplex = $this->nf('Property Complex', 'property', 'complex_name', null);
        $nfPropErf     = $this->nf('Property Erf / Unit No', 'property', 'property_number', null);
        $nfPropPrice   = $this->nf('Property Asking Price', 'property', 'price', null);

        // text(): a fillable text field. $nfId links a named-field source
        // mapping so autoFillFields() auto-populates it.
        $text = fn (string $id, string $label, string $assignedTo = 'agent', ?int $nfId = null) => array_filter([
            'id' => $id, 'field_name' => $id, 'name' => $id,
            'label' => $label, 'type' => 'input', 'tag_type' => 'input',
            'assignedTo' => $assignedTo, 'render_type' => 'web',
            'named_field_id' => $nfId,
        ], fn ($v) => $v !== null);

        $choice = fn (string $id, string $label, array $opts, string $assignedTo = 'agent') => [
            'id' => $id, 'field_name' => $id, 'name' => $id,
            'label' => $label, 'type' => 'selection', 'tag_type' => 'selection',
            'options' => $opts, 'assignedTo' => $assignedTo, 'render_type' => 'web',
        ];

        $fields = [
            $text('seller1_name', 'Seller 1 Name', 'seller', $nfSellerName),
            $text('seller1_id', 'Seller 1 ID / Entity No', 'seller', $nfSellerId),
            // Seller 2 is an optional second owner — resolveFieldValue uses
            // only the first contact of a role, so leave it agent/seller-
            // fillable rather than mis-populate it with seller 1's data.
            $text('seller2_name', 'Seller 2 Name', 'seller'),
            $text('seller2_id', 'Seller 2 ID / Entity No', 'seller'),
            $choice('marital_status', 'Marital Status', [
                'Unmarried', 'In Community of Property', 'Out of Community (ANC)', 'Other',
            ], 'seller'),
            $choice('sa_resident', 'SA Resident', ['Yes', 'No'], 'seller'),
            $text('contact_tel_email', 'Contact (Tel / Email)', 'seller', $nfSellerEmail),
            $text('property_address', 'Property Address', 'agent', $nfPropAddr),
            $text('erf_unit_no', 'Erf / Unit No', 'agent', $nfPropErf),
            $text('complex_estate', 'Complex / Estate', 'agent', $nfPropComplex),
            $text('township_suburb', 'Township / Suburb', 'agent', $nfPropSuburb),
            $text('district', 'District', 'agent', $nfPropDistrict),
            $text('asking_price', 'Asking Price (R)', 'agent', $nfPropPrice),
            $text('amount_in_words', 'Amount in Words'),
            $choice('price_basis', 'Price is', [
                'Inclusive of our professional fee', 'Exclusive of our professional fee',
            ]),
            $text('signed_at_location', 'Signed at (location)'),
            $text('signed_day', 'Signed day'),
            $text('signed_month', 'Signed month'),
            $text('signed_year', 'Signed year (20__)'),
        ];

        DB::table('docuperfect_templates')->updateOrInsert(
            ['name' => self::TEMPLATE_NAME],
            [
                'template_type'          => 'sales',
                'render_type'            => 'web',
                'blade_view'             => 'docuperfect.web-templates.marketing-permission-v6',
                'page_count'             => 1,
                'fields_json'            => json_encode(array_values($fields)),
                'field_mappings'         => null,
                'is_global'              => true,
                'is_esign'               => true,
                'signing_parties'        => json_encode(['seller', 'agent']),
                'allowed_delivery_modes' => 'esign,wet_ink,download',
                'updated_at'             => now(),
            ]
        );

        // Signature-block parties for agency 1 (spec §3 "Signature Block
        // Parties" = agency_signing_parties). Seller covers Seller 1/2;
        // Witness for the witness block. Idempotent.
        foreach ([['Seller', 4], ['Witness', 6]] as [$name, $sort]) {
            $exists = DB::table('agency_signing_parties')
                ->where('agency_id', 1)->where('name', $name)->exists();
            if (! $exists) {
                DB::table('agency_signing_parties')->insert([
                    'agency_id' => 1, 'name' => $name, 'sort_order' => $sort,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }
}
