<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the new access_prospecting and access_evaluation permissions
 * into all roles that already have access_calculators (i.e. they have
 * the "tools" section).  Also syncs these two new permission definitions
 * into the nexus_permissions table so they appear in Role Manager.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ── 1. Upsert permission definitions ──
        $definitions = [
            ['key' => 'access_prospecting', 'label' => 'Access Prospecting',        'section' => 'prospecting', 'type' => 'access', 'module' => 'prospecting', 'sort_order' => 1],
            ['key' => 'access_evaluation',  'label' => 'Access Evaluation Reports', 'section' => 'evaluation',  'type' => 'access', 'module' => 'evaluation',  'sort_order' => 1],
        ];

        foreach ($definitions as $def) {
            $exists = DB::table('nexus_permissions')->where('key', $def['key'])->exists();
            if (!$exists) {
                DB::table('nexus_permissions')->insert(array_merge($def, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        // ── 2. Grant to every role that already has access_calculators ──
        $rolesWithCalcs = DB::table('role_permissions')
            ->where('permission_key', 'access_calculators')
            ->whereNull('deleted_at')
            ->pluck('role')
            ->unique();

        $newKeys = ['access_prospecting', 'access_evaluation'];
        $rows = [];

        foreach ($rolesWithCalcs as $role) {
            foreach ($newKeys as $key) {
                $alreadyExists = DB::table('role_permissions')
                    ->where('role', $role)
                    ->where('permission_key', $key)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$alreadyExists) {
                    $rows[] = [
                        'role'           => $role,
                        'permission_key' => $key,
                        'scope'          => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
            }
        }

        if (count($rows)) {
            DB::table('role_permissions')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->whereIn('permission_key', ['access_prospecting', 'access_evaluation'])
            ->delete();

        DB::table('nexus_permissions')
            ->whereIn('key', ['access_prospecting', 'access_evaluation'])
            ->delete();
    }
};
