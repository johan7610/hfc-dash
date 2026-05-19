<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reproduces the CDS "Seller Mandatory Addendum" template — the LIVE
 * docuperfect_templates#120 row (blade_view
 * docuperfect.web-templates.cds.template-120), captured byte-for-byte to
 * database/seeders/data/seller-mandatory-addendum.json so demo:seed /
 * migrate:fresh rebuilds it identically. This is "Addendum B" for the
 * demo (#119 SALES ADDENDUM B is intentionally excluded).
 *
 * document_type_id is corrected to 13 (addendum) in the capture — the
 * live row was mis-tagged 5 (Condition Report). Same find-or-create
 * named-field resolution as MarketingPermissionEsignSeeder. Idempotent:
 * updates the existing active row, never duplicates.
 *
 * The blade view (resources/views/docuperfect/web-templates/cds/template-120
 * .blade.php) is committed in the repo — a DB reset never touches view files.
 */
class SellerMandatoryAddendumSeeder extends Seeder
{
    public const TEMPLATE_NAME = 'Seller Mandatory Addendum';

    private const DATA_FILE = 'database/seeders/data/seller-mandatory-addendum.json';

    private function nf(array $ref): int
    {
        $sourceType   = $ref['source_type'];
        $sourceColumn = $ref['source_column'];
        $contactType  = $ref['source_contact_type'] ?? null;

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
            'name'                => $ref['name'] ?? ($sourceType . '.' . $sourceColumn),
            'field_type'          => $ref['field_type'] ?? 'text',
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
        $path = base_path(self::DATA_FILE);
        if (! is_file($path)) {
            throw new \RuntimeException(static::class . ': missing data file ' . self::DATA_FILE);
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || empty($data['columns'])) {
            throw new \RuntimeException(static::class . ': data file is invalid JSON.');
        }

        $idMap = [];
        foreach (($data['named_field_refs'] ?? []) as $oldId => $ref) {
            $idMap[(int) $oldId] = $this->nf($ref);
        }

        $fieldMappings = $data['field_mappings'] ?? [];
        $remap = function (&$node) use (&$remap, $idMap) {
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $k => &$v) {
                if ($k === 'namedFieldId' && $v !== null && $v !== '' && isset($idMap[(int) $v])) {
                    $v = $idMap[(int) $v];
                } elseif (is_array($v)) {
                    $remap($v);
                }
            }
            unset($v);
        };
        $remap($fieldMappings);

        $cols = $data['columns'];
        $row = array_merge($cols, [
            'cds_json'        => $data['cds_json']        ?? null,
            'fields_json'     => $data['fields_json']     ?? null,
            'signing_parties' => $data['signing_parties'] ?? null,
            'editor_state'    => $data['editor_state']    ?? null,
            'sections'        => ($data['sections'] ?? '') !== '' ? $data['sections'] : null,
            'field_mappings'  => json_encode($fieldMappings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at'      => now(),
        ]);

        $existingId = DB::table('docuperfect_templates')
            ->where('name', self::TEMPLATE_NAME)
            ->where('template_type', 'cds')
            ->whereNull('deleted_at')
            ->value('id');

        if ($existingId) {
            DB::table('docuperfect_templates')->where('id', $existingId)->update($row);
        } else {
            DB::table('docuperfect_templates')->insert($row + ['created_at' => now()]);
        }
    }
}
