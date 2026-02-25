<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KnowledgeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Company Policies & Procedures', 'slug' => 'company-policies', 'description' => 'Internal policies, SOPs, and operational procedures', 'icon' => 'fa-building', 'sort_order' => 1],
            ['name' => 'Legal Documents & Acts', 'slug' => 'legal-documents', 'description' => 'Property Practitioners Act, Consumer Protection Act, and related legislation', 'icon' => 'fa-gavel', 'sort_order' => 2],
            ['name' => 'OTP & Contract Templates', 'slug' => 'otp-contracts', 'description' => 'Offer to Purchase templates, sale agreements, and contract references', 'icon' => 'fa-file-signature', 'sort_order' => 3],
            ['name' => 'Mandate Templates', 'slug' => 'mandate-templates', 'description' => 'Sole and dual mandate templates and guidelines', 'icon' => 'fa-handshake', 'sort_order' => 4],
            ['name' => 'FICA & Compliance', 'slug' => 'fica-compliance', 'description' => 'FICA requirements, compliance checklists, and regulatory guidance', 'icon' => 'fa-shield-alt', 'sort_order' => 5],
            ['name' => 'Training Materials', 'slug' => 'training-materials', 'description' => 'Agent onboarding, CPD materials, and training resources', 'icon' => 'fa-graduation-cap', 'sort_order' => 6],
            ['name' => 'Market Reports & Research', 'slug' => 'market-reports', 'description' => 'Market analysis reports, property indices, and research papers', 'icon' => 'fa-chart-line', 'sort_order' => 7],
            ['name' => 'Conveyancing & Transfers', 'slug' => 'conveyancing-transfers', 'description' => 'Transfer process guides, conveyancer checklists, and registration procedures', 'icon' => 'fa-exchange-alt', 'sort_order' => 8],
            ['name' => 'Rental & Property Management', 'slug' => 'rental-management', 'description' => 'Lease agreements, tenant management, and rental guidelines', 'icon' => 'fa-key', 'sort_order' => 9],
            ['name' => 'Marketing & Branding', 'slug' => 'marketing-branding', 'description' => 'Brand guidelines, marketing templates, and advertising policies', 'icon' => 'fa-bullhorn', 'sort_order' => 10],
        ];

        foreach ($categories as $category) {
            DB::table('knowledge_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                array_merge($category, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
