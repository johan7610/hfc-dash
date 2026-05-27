<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\MarketReports\SchemeOwner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 3h Step 5 — synthetic scheme owners.
 *
 * Per suburb's 12 schemes:
 *   - 1 fake "Scheme Owners List" report (cma_info_scheme_owners_list)
 *   - 8-15 owner records with plausible SA names
 *   - All owners of a scheme share the building GPS (joined through
 *     market_reports.subject_scheme_name → subject_lat/lng for the
 *     map service to pick up)
 *
 * 6 suburbs × 12 schemes = 72 schemes total = 72 owners-list reports.
 *
 * Architectural call-out: scheme_owners has lat/lng columns natively (Phase 3a
 * migration added them), so we populate them directly here AND via the
 * inheriting join in MapPinService. Either path produces the right pin.
 */
final class DemoSchemeOwnersSeeder
{
    /** @return array{reports:int, owners:int} */
    public function run(int $agencyId): array
    {
        $gazetteer = require database_path('seeders/data/kzn_south_coast_suburbs.php');

        $uploader = DB::table('users')->where('agency_id', $agencyId)
            ->whereIn('role', ['agent', 'admin', 'branch_manager'])
            ->orderBy('id')
            ->value('id');
        if (!$uploader) {
            return ['reports' => 0, 'owners' => 0, 'note' => "Skipped — agency {$agencyId} has no agents."];
        }

        $ownersTypeId = DB::table('market_report_types')
            ->where('key', 'cma_info_scheme_owners_list')
            ->value('id');
        if (!$ownersTypeId) {
            return ['reports' => 0, 'owners' => 0, 'note' => 'Skipped — scheme owners type missing.'];
        }

        $reportsInserted = 0;
        $ownersInserted = 0;

        foreach ($gazetteer as $suburbKey => $suburb) {
            foreach ($suburb['schemes'] as $schemeName) {
                $schemeGps = $this->seededGps($schemeName . '|scheme', $suburb['bounds']);
                $reportDate = Carbon::now()->subDays(random_int(7, 540));

                // Create the owners-list report. subject_scheme_name is the
                // hinge field — MapPinService's join uses it.
                $uuid = (string) Str::uuid();
                $reportId = DB::table('market_reports')->insertGetId([
                    'agency_id'           => $agencyId,
                    'uploaded_by_user_id' => $uploader,
                    'report_type_id'      => $ownersTypeId,
                    'file_path'           => 'demo/owners/' . $uuid . '.pdf',
                    'file_name'           => 'demo_owners_' . $schemeName . '_' . $uuid . '.pdf',
                    'file_hash'           => hash('sha256', 'owners:' . $uuid),
                    'source_suburb'       => $suburb['name'],
                    'source_town'         => $suburb['town'],
                    'report_date'         => $reportDate->toDateString(),
                    'parse_status'        => 'parsed',
                    'parse_completed_at'  => $reportDate,
                    'parser_version'      => 'demo_v1',
                    'raw_extracted_json'  => json_encode(['note' => 'Demo-seeded owners list']),
                    'spot_check_status'   => 'passed',
                    'subject_scheme_name' => $schemeName,
                    'subject_latitude'    => $schemeGps['lat'],
                    'subject_longitude'   => $schemeGps['lng'],
                    'is_demo'             => true,
                    'created_at'          => $reportDate,
                    'updated_at'          => $reportDate,
                ]);
                $reportsInserted++;

                $ownerCount = random_int(8, 15);
                for ($i = 0; $i < $ownerCount; $i++) {
                    // Some duplicates for joint ownership — every 5th-7th owner
                    // shares a section with the previous one.
                    $section = (string) (intdiv($i, random_int(5, 7)) + 1);
                    $ownerSeed = $schemeName . '|' . $suburbKey . '|' . $i;
                    SchemeOwner::create([
                        'agency_id'        => $agencyId,
                        'market_report_id' => $reportId,
                        'scheme_name'      => $schemeName,
                        'section_number'   => $section,
                        'owner_name'       => DemoNames::name($ownerSeed),
                        'extent_m2'        => random_int(60, 170),
                        'property_type'    => 'Sectional Title',
                        'latitude'         => $schemeGps['lat'],
                        'longitude'        => $schemeGps['lng'],
                        'is_demo'          => true,
                    ]);
                    $ownersInserted++;
                }
            }
        }

        return ['reports' => $reportsInserted, 'owners' => $ownersInserted];
    }

    private function seededGps(string $seed, array $bounds): array
    {
        $hash = crc32($seed);
        $cellX = $hash % 4;
        $cellY = intdiv($hash, 4) % 4;
        $cellWidth  = ($bounds['east']  - $bounds['west'])  / 4;
        $cellHeight = ($bounds['north'] - $bounds['south']) / 4;
        $jitterX = (($hash >> 8)  & 0xFF) / 0xFF;
        $jitterY = (($hash >> 16) & 0xFF) / 0xFF;
        $lng = $bounds['west']  + ($cellX * $cellWidth)  + ($jitterX * $cellWidth);
        $lat = $bounds['south'] + ($cellY * $cellHeight) + ($jitterY * $cellHeight);
        return ['lat' => round($lat, 7), 'lng' => round($lng, 7)];
    }
}
