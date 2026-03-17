<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncNamedFields extends Command
{
    protected $signature = 'docuperfect:sync-fields';

    protected $description = 'Sync named fields from database schema for contacts, properties, users, and deals';

    /**
     * Tables to scan and their source_type mapping.
     */
    private const SOURCE_MAP = [
        'contacts'   => 'contact',
        'properties' => 'property',
        'users'      => 'agent',
        'deals'      => 'deal',
    ];

    /**
     * System columns to skip — these are never useful as document fields.
     */
    private const SKIP_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'remember_token',
        'password',
        'email_verified_at',
        'api_token',
    ];

    /**
     * Contact types — each contact column gets one named field per type.
     */
    private const CONTACT_TYPES = ['Lessor', 'Lessee', 'Seller', 'Buyer'];

    /**
     * Composite/computed fields to ensure exist per contact type.
     * Format: source_column => base label
     */
    private const CONTACT_COMPOSITE_FIELDS = [
        'first_name+last_name' => 'Full Name',
    ];

    public function handle(): int
    {
        $added = 0;
        $existed = 0;
        $deleted = 0;

        // Clean up generic contact fields with null source_contact_type
        $deleted = DB::table('docuperfect_named_fields')
            ->where('source_type', 'contact')
            ->whereNull('source_contact_type')
            ->delete();

        if ($deleted > 0) {
            $this->warn("Removed {$deleted} generic contact fields (null source_contact_type).");
        }

        foreach (self::SOURCE_MAP as $table => $sourceType) {
            if (!Schema::hasTable($table)) {
                $this->warn("Table [{$table}] does not exist — skipping.");
                continue;
            }

            $columns = Schema::getColumnListing($table);

            if ($sourceType === 'contact') {
                // Contact fields: one entry per contact type per column
                foreach (self::CONTACT_TYPES as $contactType) {
                    $maxSort = (int) (DB::table('docuperfect_named_fields')
                        ->where('source_type', 'contact')
                        ->where('source_contact_type', $contactType)
                        ->max('sort_order') ?? 0);

                    foreach ($columns as $column) {
                        if (in_array($column, self::SKIP_COLUMNS, true)) {
                            continue;
                        }

                        $exists = DB::table('docuperfect_named_fields')
                            ->where('source_type', 'contact')
                            ->where('source_column', $column)
                            ->where('source_contact_type', $contactType)
                            ->exists();

                        if ($exists) {
                            $existed++;
                            continue;
                        }

                        $maxSort++;
                        $label = "{$contactType} " . $this->humanLabel($column);

                        DB::table('docuperfect_named_fields')->insert([
                            'name'                => $label,
                            'field_type'          => $this->inferFieldType($table, $column),
                            'source_type'         => 'contact',
                            'source_column'       => $column,
                            'source_contact_type' => $contactType,
                            'sort_order'          => $maxSort,
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ]);

                        $this->line("  + [contact/{$contactType}] {$column} → {$label}");
                        $added++;
                    }

                    // Composite fields per contact type
                    foreach (self::CONTACT_COMPOSITE_FIELDS as $sourceColumn => $baseLabel) {
                        $exists = DB::table('docuperfect_named_fields')
                            ->where('source_type', 'contact')
                            ->where('source_column', $sourceColumn)
                            ->where('source_contact_type', $contactType)
                            ->exists();

                        if ($exists) {
                            $existed++;
                            continue;
                        }

                        $maxSort++;
                        $label = "{$contactType} {$baseLabel}";

                        DB::table('docuperfect_named_fields')->insert([
                            'name'                => $label,
                            'field_type'          => 'text',
                            'source_type'         => 'contact',
                            'source_column'       => $sourceColumn,
                            'source_contact_type' => $contactType,
                            'sort_order'          => $maxSort,
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ]);

                        $this->line("  + [contact/{$contactType}] {$sourceColumn} → {$label} (composite)");
                        $added++;
                    }
                }
            } else {
                // Property, agent, deal — no contact type
                $maxSort = (int) (DB::table('docuperfect_named_fields')
                    ->where('source_type', $sourceType)
                    ->max('sort_order') ?? 0);

                foreach ($columns as $column) {
                    if (in_array($column, self::SKIP_COLUMNS, true)) {
                        continue;
                    }

                    $exists = DB::table('docuperfect_named_fields')
                        ->where('source_type', $sourceType)
                        ->where('source_column', $column)
                        ->exists();

                    if ($exists) {
                        $existed++;
                        continue;
                    }

                    $maxSort++;
                    DB::table('docuperfect_named_fields')->insert([
                        'name'          => $this->humanLabel($column),
                        'field_type'    => $this->inferFieldType($table, $column),
                        'source_type'   => $sourceType,
                        'source_column' => $column,
                        'sort_order'    => $maxSort,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);

                    $this->line("  + [{$sourceType}] {$column} → " . $this->humanLabel($column));
                    $added++;
                }
            }
        }

        $this->info("Added {$added} new fields. {$existed} already existed.");

        return 0;
    }

    /**
     * Convert snake_case column name to Title Case label.
     */
    private function humanLabel(string $column): string
    {
        return Str::of($column)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    /**
     * Infer field_type from the database column type.
     */
    private function inferFieldType(string $table, string $column): string
    {
        $type = Schema::getColumnType($table, $column);

        if (in_array($type, ['date', 'datetime', 'timestamp'], true)) {
            return 'date';
        }

        if (in_array($type, ['integer', 'bigint', 'smallint', 'decimal', 'float', 'double'], true)) {
            return 'number';
        }

        return 'text';
    }
}
