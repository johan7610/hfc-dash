<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $roles = [
            [
                'name'           => 'super_admin',
                'label'          => 'System Owner',
                'description'    => 'Full system access. Bypasses all permission checks.',
                'color'          => '#0b2a4a',
                'is_owner'       => true,
                'can_be_deleted' => false,
                'sort_order'     => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'name'           => 'admin',
                'label'          => 'Administrator',
                'description'    => 'Full management access except agency-level settings.',
                'color'          => '#00b4d8',
                'is_owner'       => false,
                'can_be_deleted' => true,
                'sort_order'     => 2,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'name'           => 'branch_manager',
                'label'          => 'Branch Manager',
                'description'    => 'Manages branch operations, compliance, and supervision.',
                'color'          => '#0891b2',
                'is_owner'       => false,
                'can_be_deleted' => true,
                'sort_order'     => 3,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'name'           => 'agent',
                'label'          => 'Agent',
                'description'    => 'Core sales operations — listings, deals, presentations.',
                'color'          => '#64748b',
                'is_owner'       => false,
                'can_be_deleted' => true,
                'sort_order'     => 4,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'name'           => 'viewer',
                'label'          => 'Viewer',
                'description'    => 'Read-only access to most features.',
                'color'          => '#94a3b8',
                'is_owner'       => false,
                'can_be_deleted' => true,
                'sort_order'     => 5,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
        ];

        DB::table('roles')->insert($roles);
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('name', [
            'super_admin', 'admin', 'branch_manager', 'agent', 'viewer',
        ])->delete();
    }
};
