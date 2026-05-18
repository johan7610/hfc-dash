<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sales Mandatory Disclosure — sale-context condition report (PPA 22/2019
 * s70 + Regs 2022 s36). Replaces the wrong "Letting Mandatory Disclosure"
 * in the Seller Onboarding pack.
 *
 * Blade : resources/views/docuperfect/web-templates/sales-mandatory-disclosure.blade.php
 * Content reproduced verbatim from cds/template-117.blade.php.
 *
 * Field mechanism (proven, same as Marketing Permission V6): fields_json
 * drives the wizard; field_mappings stays NULL (web+field_mappings would
 * rebuild via buildFieldsFromMappings() and drop `options`). The 11 defect
 * statements are type:'selection' (Yes/No/N/A) so the wizard renders them
 * as interactive <select>s that save. The owner discloses → defect +
 * additional-info fields assignedTo='seller'; the agent prepares the
 * property address. Idempotent: updateOrInsert keyed by name.
 */
class SalesMandatoryDisclosureSeeder extends Seeder
{
    public const TEMPLATE_NAME = 'Sales Mandatory Disclosure';

    public function run(): void
    {
        $text = fn (string $id, string $label, string $assignedTo) => [
            'id' => $id, 'field_name' => $id, 'name' => $id,
            'label' => $label, 'type' => 'input', 'tag_type' => 'input',
            'assignedTo' => $assignedTo, 'render_type' => 'web',
        ];
        $yesno = fn (string $id, string $label) => [
            'id' => $id, 'field_name' => $id, 'name' => $id,
            'label' => $label, 'type' => 'selection', 'tag_type' => 'selection',
            'options' => ['Yes', 'No', 'N/A'], 'assignedTo' => 'seller', 'render_type' => 'web',
        ];

        $fields = [
            $text('property_address', 'Property Address', 'agent'),
            $yesno('defect_roof', 'Defects in the roof'),
            $yesno('defect_electrical', 'Defects in the electrical systems'),
            $yesno('defect_plumbing', 'Defects in the plumbing system (incl. pool)'),
            $yesno('defect_hvac', 'Defects in heating / air conditioning systems'),
            $yesno('defect_septic', 'Defects in septic / sanitary disposal systems'),
            $yesno('defect_foundations', 'Defects in basement / foundations / damp / mould'),
            $yesno('defect_structural', 'Structural defects in the Property'),
            $yesno('defect_boundary', 'Boundary dispute / encroachments / encumbrances'),
            $yesno('defect_remodelling', 'Remodelling / refurbishment affected the structure'),
            $yesno('defect_consents', 'Additions/improvements made with required consents/permits'),
            $yesno('defect_heritage', 'Structure earmarked as historic / heritage site'),
            $text('additional_information', 'Additional information (explain any "Yes")', 'seller'),
        ];

        DB::table('docuperfect_templates')->updateOrInsert(
            ['name' => self::TEMPLATE_NAME],
            [
                'template_type'          => 'sales',
                'render_type'            => 'web',
                'blade_view'             => 'docuperfect.web-templates.sales-mandatory-disclosure',
                'page_count'             => 2,
                'fields_json'            => json_encode($fields),
                'field_mappings'         => null,
                'is_global'              => true,
                'is_esign'               => true,
                'signing_parties'        => json_encode(['seller', 'agent', 'buyer']),
                'allowed_delivery_modes' => 'esign,wet_ink,download',
                'updated_at'             => now(),
            ]
        );

        // Retire the legacy CDS source (cds/template-117) from the e-sign
        // wizard. Its YES/NO/N/A disclosure cells are bare <td></td> with no
        // data-field, so the owner can never fill them — superseded by the
        // blade above. Soft retire only (no hard delete; row stays, is
        // recoverable); idempotent, keyed by the stable blade_view.
        DB::table('docuperfect_templates')
            ->where('blade_view', 'docuperfect.web-templates.cds.template-117')
            ->whereNull('deleted_at')
            ->where('is_esign', true)
            ->update(['is_esign' => false, 'updated_at' => now()]);
    }
}
