<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "Marketing Permission" web pack — the canonical seller onboarding bundle
 * for the e-sign wizard (mirrors the live web_packs#5 definition):
 *   1. Marketing Permission Esign  (MarketingPermissionEsignSeeder — CDS)
 *   2. Sales Mandatory Disclosure  (SalesMandatoryDisclosureEsignSeeder — #123)
 *   3. Seller Mandatory Addendum   (SellerMandatoryAddendumSeeder — #120)
 *
 * FICA is intentionally NOT a pack item — it stays the per-recipient
 * "FICA verification required" toggle at wizard Step 6.
 *
 * web_packs.created_by is a NOT-NULL FK→users, so this must run AFTER
 * users exist (wired into DemoDataSeeder after Stage 1, alongside
 * SellerOnboardingPackSeeder). Idempotent: pack keyed by
 * (agency_id,name); items keyed by (web_pack_id,template_id);
 * created_at set ONLY on insert (raw query builder has no timestamp
 * magic — a NULL created_at crashes the /web-packs list).
 */
class MarketingPermissionPackSeeder extends Seeder
{
    public const PACK_NAME = 'Marketing Permission';

    public function run(): void
    {
        $agencyId = 1;

        $createdBy = DB::table('users')->where('agency_id', $agencyId)
                ->whereIn('role', ['admin', 'super_admin'])->whereNull('deleted_at')->value('id')
            ?? DB::table('users')->where('agency_id', $agencyId)->whereNull('deleted_at')->value('id')
            ?? DB::table('users')->whereNull('deleted_at')->value('id');

        if (! $createdBy) {
            throw new \RuntimeException(static::class . ' needs ≥1 user (web_packs.created_by is NOT NULL).');
        }

        // Resolve member templates by the SAME stable key the template
        // seeders use: (name, template_type='cds', not deleted).
        $resolve = fn (string $name) => DB::table('docuperfect_templates')
            ->where('name', $name)
            ->where('template_type', 'cds')
            ->whereNull('deleted_at')
            ->value('id');

        $marketingId  = $resolve(MarketingPermissionEsignSeeder::TEMPLATE_NAME);
        $disclosureId = $resolve(SalesMandatoryDisclosureEsignSeeder::TEMPLATE_NAME);
        $addendumId   = $resolve(SellerMandatoryAddendumSeeder::TEMPLATE_NAME);

        if (! $marketingId || ! $disclosureId || ! $addendumId) {
            throw new \RuntimeException(
                static::class . ' needs all three templates seeded first '
                . '(Marketing Permission Esign + Sales Mandatory Disclosure + Seller Mandatory Addendum).'
            );
        }

        $values = [
            'created_by'  => $createdBy,
            'description' => 'Marketing Permission + Sales Mandatory Disclosure + Seller Mandatory '
                . 'Addendum for a new seller. FICA via the per-recipient toggle.',
            'updated_at'  => now(),
        ];
        $existing = DB::table('web_packs')
            ->where('agency_id', $agencyId)->where('name', self::PACK_NAME)->first();
        if ($existing) {
            DB::table('web_packs')->where('id', $existing->id)->update($values);
            $packId = $existing->id;
        } else {
            $packId = DB::table('web_packs')->insertGetId(
                $values + ['agency_id' => $agencyId, 'name' => self::PACK_NAME, 'created_at' => now()]
            );
        }

        $items = [
            ['template_id' => $marketingId,  'sort_order' => 0,  'slot_label' => 'Marketing Permission Esign'],
            ['template_id' => $disclosureId, 'sort_order' => 10, 'slot_label' => 'Sales Mandatory Disclosure'],
            ['template_id' => $addendumId,   'sort_order' => 20, 'slot_label' => 'Seller Mandatory Addendum'],
        ];

        foreach ($items as $item) {
            DB::table('web_pack_items')->updateOrInsert(
                ['web_pack_id' => $packId, 'template_id' => $item['template_id']],
                [
                    'sort_order' => $item['sort_order'],
                    'slot_type'  => 'required',
                    'slot_group' => null,
                    'slot_label' => $item['slot_label'],
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
