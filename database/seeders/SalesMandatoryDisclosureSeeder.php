<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sales Mandatory Disclosure — sale-context condition report (PPA 22/2019
 * s70 + Regs 2022 s36).
 *
 * CDS-CONTRACT path (the proven path templates 117/120 use). The blade
 * resources/views/docuperfect/web-templates/sales-mandatory-disclosure.blade.php
 * emits the corex-table disclosure contract (a <thead> with separate
 * YES / NO / N/A <th> columns + empty option <td>s + an ADDITIONAL
 * INFORMATION row). At signing _processDisclosureTable injects real
 * radio inputs and enforces mandatory-before-complete;
 * _processAdditionalInfoSection turns the ADDITIONAL INFORMATION row
 * into an editable textarea. Signatures use the shared signature-block
 * partial (recipient-driven, emits data-marker-type="signature").
 *
 * template_type='cds' + field_mappings present → showStep rebuilds
 * wizard fields via buildFieldsFromMappings (Fill & Review shows
 * labelled inputs); property_address resolves through the
 * WebTemplateDataService canonical key. The 11 defect answers are NOT
 * fields_json fields — they are captured live by the radio machinery.
 * Idempotent: updateOrInsert keyed by name.
 */
class SalesMandatoryDisclosureSeeder extends Seeder
{
    public const TEMPLATE_NAME = 'Sales Mandatory Disclosure';

    /** The 11 PPA s70 / Reg 36 defect statements (verbatim, document order). */
    private const STATEMENTS = [
        'I am aware of the defects in the roof',
        'I am aware of the defects in the electrical systems',
        'I am aware of the defects in the plumbing system, including in the swimming pool (if any)',
        'I am aware of the defects in the heating and air conditioning systems, including the air filters and humidifiers',
        'I am aware of the defects in the septic or other sanitary disposal systems',
        'I am aware of any defects to the property and/or in the basement or foundations of the property, including cracks, seepage and bulges. Other such defects include, but are not limited to, flooding, dampness or wet walls and unsafe concentrations of mould or defects in drain tiling or sump pumps',
        'I am aware of structural defects in the Property',
        'I am aware of boundary line dispute, encroachments, or encumbrances in connection with the Property',
        'I am aware that remodelling and refurbishment have affected the structure of the Property',
        'I am aware that any additions or improvements made to or any erections made on the property, have been done or were made, only after the required consents, permissions and permits to do so were properly obtained.',
        'I am aware that a structure on the Property has been earmarked as a historic structure or heritage site',
    ];

    public function run(): void
    {
        // CDS disclosure_checklist section — mirrors the proven template-120
        // shape (CdsRendererService::renderDisclosureChecklist). has_na=true,
        // no conditional dates (sales defects are pure Yes/No/N/A).
        $items = array_map(fn (string $s) => [
            'type'                => 'checklist_item',
            'value'               => null,
            'statement'           => $s,
            'date_value'          => null,
            'has_conditional_date' => false,
        ], self::STATEMENTS);

        $cdsJson = [
            'sections' => [
                ['type' => 'heading', 'level' => 1, 'content' => [
                    ['type' => 'text', 'value' => 'IMMOVABLE PROPERTY CONDITION REPORT IN RELATION TO THE SALE OF ANY IMMOVABLE PROPERTY'],
                ]],
                [
                    'type'    => 'disclosure_checklist',
                    'items'   => $items,
                    'has_na'  => true,
                    'header'  => ['', 'YES', 'NO', 'N/A'],
                    'headers' => ['ADDITIONAL INFORMATION'],
                ],
                ['type' => 'signature_section', 'parties' => [
                    ['role' => 'seller', 'label' => 'Seller'],
                    ['role' => 'agent', 'label' => 'Agent'],
                    ['role' => 'buyer', 'label' => 'Buyer'],
                ], 'preamble' => ''],
            ],
        ];

        // field_mappings — the contract showStep uses (template_type=cds →
        // buildFieldsFromMappings). property_address derives field_name
        // 'property_address' (matches the blade data-field AND the
        // WebTemplateDataService canonical key → auto-populates).
        $fieldMappings = [
            'fld-sales-disclosure-property-address' => [
                'field_name'  => 'property_address',
                'label'       => 'Property Address',
                'type'        => 'input',
                'tag_type'    => 'input',
                'filled_by'   => 'agent',
                'mappingType' => 'mapped',
            ],
            'fld-sales-disclosure-additional-info' => [
                'field_name'  => 'additional_information',
                'label'       => 'Additional information (explain any "Yes")',
                'type'        => 'input',
                'tag_type'    => 'input',
                'filled_by'   => 'seller',
                'mappingType' => 'manual',
            ],
            // Signature tag (parties for the signature-block partial — same
            // shape as the proven template-120 sig mapping). Not a wizard
            // input field; the blade renders signatures via the partial.
            'tag-sales-disclosure-sig' => [
                'parties' => ['Seller', 'Agent', 'Buyer'],
                'variant' => 'sig_full',
            ],
        ];

        // fields_json fallback (used only if the buildFieldsFromMappings
        // rebuild path is ever skipped) — the 2 real input fields only;
        // the defect answers are runtime radios, not fields.
        $fieldsJson = [
            ['id' => 'property_address', 'field_name' => 'property_address', 'name' => 'property_address',
             'label' => 'Property Address', 'type' => 'input', 'tag_type' => 'input',
             'assignedTo' => 'agent', 'render_type' => 'web'],
            ['id' => 'additional_information', 'field_name' => 'additional_information', 'name' => 'additional_information',
             'label' => 'Additional information (explain any "Yes")', 'type' => 'input', 'tag_type' => 'input',
             'assignedTo' => 'seller', 'render_type' => 'web'],
        ];

        DB::table('docuperfect_templates')->updateOrInsert(
            ['name' => self::TEMPLATE_NAME],
            [
                'template_type'          => 'cds',
                'render_type'            => 'web',
                'blade_view'             => 'docuperfect.web-templates.sales-mandatory-disclosure',
                'page_count'             => 2,
                'cds_json'               => json_encode($cdsJson),
                'field_mappings'         => json_encode($fieldMappings),
                'fields_json'            => json_encode($fieldsJson),
                'is_global'              => true,
                'is_esign'               => true,
                'signing_parties'        => json_encode(['seller', 'agent', 'buyer']),
                'allowed_delivery_modes' => 'esign,wet_ink,download',
                'updated_at'             => now(),
            ]
        );

        // Retire the legacy CDS source (cds/template-117) from the e-sign
        // wizard — it is the duplicate "Sales Mandatory"; the corrected
        // "Sales Mandatory Disclosure" above reuses its proven markup under
        // the proper name. Soft retire only (no hard delete; recoverable);
        // idempotent, keyed by the stable blade_view.
        DB::table('docuperfect_templates')
            ->where('blade_view', 'docuperfect.web-templates.cds.template-117')
            ->whereNull('deleted_at')
            ->where('is_esign', true)
            ->update(['is_esign' => false, 'updated_at' => now()]);
    }
}
