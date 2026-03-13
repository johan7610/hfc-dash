<?php

namespace Database\Seeders;

use App\Models\Docuperfect\FieldGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FieldGroupSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate existing default groups before re-seeding
        DB::table('docuperfect_field_groups')->truncate();

        // Get any user as created_by for global groups
        $userId = DB::table('users')->where('is_active', 1)->value('id')
            ?? DB::table('users')->value('id');

        if (!$userId) {
            return;
        }

        // Define the 6 default global groups with confirmed named_field IDs
        $groupDefs = [
            [
                'name' => 'Lessor — Name only',
                'fields' => [
                    ['named_field_id' => 1, 'label_override' => null],  // Rental - Lessor
                ],
            ],
            [
                'name' => 'Lessor — Name + ID',
                'fields' => [
                    ['named_field_id' => 1, 'label_override' => null],  // Rental - Lessor
                    ['named_field_id' => 3, 'label_override' => null],  // Lessor ID
                ],
            ],
            [
                'name' => 'Lessor — Full',
                'fields' => [
                    ['named_field_id' => 1, 'label_override' => null],   // Rental - Lessor
                    ['named_field_id' => 3, 'label_override' => null],   // Lessor ID
                    ['named_field_id' => 2, 'label_override' => null],   // Lessor Address
                    ['named_field_id' => 34, 'label_override' => null],  // Lessor Contact Number
                    ['named_field_id' => 35, 'label_override' => null],  // Lessor email
                ],
            ],
            [
                'name' => 'Lessee — Name + ID',
                'fields' => [
                    ['named_field_id' => 4, 'label_override' => null],  // Lessee Name
                    ['named_field_id' => 6, 'label_override' => null],  // Lessee ID
                ],
            ],
            [
                'name' => 'Property — Address',
                'fields' => [
                    ['named_field_id' => 19, 'label_override' => null],  // Property Address
                    ['named_field_id' => 24, 'label_override' => null],  // Suburb
                ],
            ],
            [
                'name' => 'Property — Full',
                'fields' => [
                    ['named_field_id' => 19, 'label_override' => null],  // Property Address
                    ['named_field_id' => 10, 'label_override' => null],  // Property Number
                    ['named_field_id' => 11, 'label_override' => null],  // Complex
                    ['named_field_id' => 24, 'label_override' => null],  // Suburb
                    ['named_field_id' => 25, 'label_override' => null],  // District
                ],
            ],
        ];

        foreach ($groupDefs as $sortOrder => $def) {
            FieldGroup::create([
                'agency_id' => null,
                'created_by' => $userId,
                'name' => $def['name'],
                'description' => null,
                'fields' => $def['fields'],
                'layout' => 'vertical',
                'is_global' => true,
                'sort_order' => ($sortOrder + 1) * 10,
            ]);
        }
    }
}
