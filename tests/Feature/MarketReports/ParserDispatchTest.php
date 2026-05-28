<?php

declare(strict_types=1);

namespace Tests\Feature\MarketReports;

use App\Jobs\MarketReports\ParseMarketReportJob;
use App\Models\MarketReports\MarketReport;
use App\Models\MarketReports\MarketReportType;
use App\Models\User;
use App\Services\MarketReports\Contracts\MarketReportParser;
use App\Services\MarketReports\MarketReportParserRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/**
 * Parser-dispatch guard tests. Three classes of bug surfaced when vicinity
 * reports kept landing on GenericFallback after the parser shipped:
 *
 *   A. A parser exists but isn't in MarketReportParserRegistry::V1_PARSERS,
 *      so detect() never asks it canParse(). (BUG 1)
 *   B. Re-parse trusts the old report_type_id stamp and bypasses detection,
 *      so once a misclassification is recorded it persists forever. (BUG 2)
 *   C. A parser is registered + tests pass, but its corresponding
 *      market_report_types row was never seeded — detect picks the parser,
 *      controller can't resolve a type_id, the index renders "—". (BUG 3)
 *
 * Each test below guards one of those classes. The registry-completeness
 * test (A) is the highest-leverage one — it fails at build time the moment
 * anyone adds a 7th parser without wiring it in.
 */
final class ParserDispatchTest extends TestCase
{
    use RefreshDatabase;

    private const VICINITY_FIXTURE = 'cma_info_vicinity_sale_residential.pdf';
    private const VICINITY_VACANT_FIXTURE = 'cma_info_vicinity_sale_vacant_land.pdf';
    private const SECTIONAL_FIXTURE = 'cma_info_sectional_title_sales.pdf';

    // ── Test A — registry completeness guard ─────────────────────────────

    public function test_every_concrete_parser_in_parsers_directory_is_registered(): void
    {
        $parserDir = base_path('app/Services/MarketReports/Parsers');
        $files = glob($parserDir . '/*.php');
        $this->assertNotEmpty($files, 'Parsers directory must not be empty');

        $concreteParsers = [];
        foreach ($files as $file) {
            $base = basename($file, '.php');
            $fqcn = 'App\\Services\\MarketReports\\Parsers\\' . $base;
            if (!class_exists($fqcn)) continue;

            $ref = new ReflectionClass($fqcn);
            if ($ref->isAbstract()) continue;
            if (!$ref->implementsInterface(MarketReportParser::class)) continue;

            $concreteParsers[] = $fqcn;
        }

        sort($concreteParsers);
        $registered = MarketReportParserRegistry::V1_PARSERS;
        sort($registered);

        $missing = array_diff($concreteParsers, $registered);
        $extra   = array_diff($registered, $concreteParsers);

        $this->assertEmpty(
            $missing,
            "These concrete parsers exist in app/Services/MarketReports/Parsers/ but are NOT in MarketReportParserRegistry::V1_PARSERS:\n  - "
            . implode("\n  - ", $missing)
            . "\nAdd them to the V1_PARSERS const (GenericFallbackParser stays last).",
        );

        $this->assertEmpty(
            $extra,
            "V1_PARSERS references classes that don't exist as concrete parsers:\n  - " . implode("\n  - ", $extra),
        );
    }

    // ── Test B — re-parse must re-detect (vicinity unstick) ─────────────

    public function test_reparse_clears_type_stamp_and_re_detects_correct_parser(): void
    {
        [$agencyId, $userId] = $this->seedAgency();
        $otherType   = $this->ensureType('other', 'Other / Unknown', \App\Services\MarketReports\Parsers\GenericFallbackParser::class);
        $vicinityType = $this->ensureType(
            'cma_info_vicinity_sale',
            'CMA Info — Vicinity Sale (Residential / Vacant Land)',
            \App\Services\MarketReports\Parsers\CmaInfoVicinitySaleParser::class,
        );

        // Stage exactly the misclassified state we see in prod: a vicinity
        // PDF on disk, but the report row is stamped as type 'other' from
        // when the parser wasn't registered. NB: real local disk (not
        // Storage::fake) — pdftotext needs a real file path on disk.
        $storedPath = $this->stageRealFixtureOnDisk($agencyId, self::VICINITY_FIXTURE);
        $this->assertTrue(
            is_file(Storage::disk('local')->path($storedPath)),
            'Staging step must put a real file on disk for pdftotext to read',
        );
        $report = MarketReport::create([
            'agency_id'           => $agencyId,
            'uploaded_by_user_id' => $userId,
            'report_type_id'      => $otherType->id,
            'file_path'           => $storedPath,
            'file_name'           => 'StuckOnOther.pdf',
            'file_hash'           => hash('sha256', Str::random(64)),
            'report_date'         => now()->toDateString(),
            'parse_status'        => MarketReport::PARSE_PARSED,
            'spot_check_status'   => MarketReport::SPOT_PASSED,
            'data_points_count'   => 0,
            'parser_version'      => 'generic_fallback_v1',
        ]);

        $this->actingAs(User::find($userId))
            ->post(route('market-intelligence.reports.reparse', $report))
            ->assertRedirect(route('market-intelligence.reports.show', $report));

        $report->refresh()->load('reportType');
        $this->assertGreaterThan(0, (int) $report->data_points_count,
            'Re-parsed vicinity report must extract facts now that detection runs');
        $this->assertSame(
            $vicinityType->id,
            (int) $report->report_type_id,
            'Re-parse must re-detect + write back the corrected type_id (not stay on Other)',
        );
    }

    // ── Test C — type row exists for every registered parser key ────────

    public function test_every_registered_parser_has_a_matching_market_report_types_row(): void
    {
        // Production guard: after deploy (migrations + seeder), every parser
        // in the registry must have a market_report_types row. The seeder
        // is the source of truth for the rows; we run it in-test so the
        // check matches the production deploy sequence. If anyone adds a
        // parser to the registry without adding a seeder entry (or a
        // backstop migration like 2026_06_16_122200_seed_cma_info_vicinity_sale_type),
        // this fails.
        $this->seed(\Database\Seeders\MarketReportTypesSeeder::class);

        $registry = app(MarketReportParserRegistry::class);
        $missing = [];

        foreach ($registry->all() as $parser) {
            $key = $parser->getReportTypeKey();
            $exists = MarketReportType::query()->where('key', $key)->exists();
            if (!$exists) {
                $missing[$key] = get_class($parser);
            }
        }

        $this->assertEmpty(
            $missing,
            "These parsers are registered but have no matching market_report_types row "
            . "after running migrations + MarketReportTypesSeeder. The parser will be "
            . "detected but report_type_id resolves to null and the index renders '—'.\n"
            . "Fix by adding a row to database/seeders/MarketReportTypesSeeder.php for each key below:\n  - "
            . implode("\n  - ", array_map(fn ($k, $v) => "key='{$k}' parser={$v}", array_keys($missing), $missing)),
        );
    }

    // ── BUILD_STANDARD input matrix — end-to-end paths ──────────────────

    public function test_fresh_upload_residential_vicinity_pdf_is_detected_as_vicinity(): void
    {
        $this->assertFreshUploadResolvesTo(self::VICINITY_FIXTURE, 'cma_info_vicinity_sale');
    }

    public function test_fresh_upload_vacant_land_vicinity_pdf_is_detected_as_vicinity(): void
    {
        $this->assertFreshUploadResolvesTo(self::VICINITY_VACANT_FIXTURE, 'cma_info_vicinity_sale');
    }

    public function test_sectional_title_pdf_still_routes_to_sectional_parser_no_regression(): void
    {
        $this->assertFreshUploadResolvesTo(self::SECTIONAL_FIXTURE, 'cma_info_sectional_title_sales');
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function assertFreshUploadResolvesTo(string $fixture, string $expectedKey): void
    {
        [$agencyId, $userId] = $this->seedAgency();
        Storage::fake('local');
        $this->ensureType('other', 'Other / Unknown', \App\Services\MarketReports\Parsers\GenericFallbackParser::class);
        $expected = $this->ensureTypeForKey($expectedKey);

        $upload = $this->fixtureUploadedFile($fixture);
        $this->actingAs(User::find($userId))
            ->post(route('market-intelligence.reports.store'), ['file' => $upload])
            ->assertRedirect();

        $report = MarketReport::query()->where('agency_id', $agencyId)->latest('id')->first();
        $this->assertNotNull($report, 'Upload must create a market_reports row');
        $report->load('reportType');
        $this->assertSame(
            $expectedKey,
            $report->reportType?->key,
            "Fresh upload of {$fixture} must detect as '{$expectedKey}', got '"
            . ($report->reportType?->key ?? 'NULL') . "'",
        );
        $this->assertGreaterThan(0, (int) $report->data_points_count,
            'A correctly-detected parse must extract at least one fact');
    }

    /** @return array{0:int,1:int} */
    private function seedAgency(): array
    {
        $agencyId = (int) DB::table('agencies')->insertGetId([
            'name' => 'Test ' . Str::random(6),
            'slug' => 'test-' . Str::random(8),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => $agencyId, 'agency_id' => $agencyId, 'name' => 'Default',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // promoteListingToProperty (not used here) plus other downstream code
        // needs an agency-level admin/agent; seed alongside super_admin.
        User::factory()->create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId,
            'role' => 'agent', 'name' => 'Agency Agent',
        ]);
        $user = User::factory()->create([
            'agency_id' => $agencyId, 'branch_id' => $agencyId, 'role' => 'super_admin',
        ]);
        return [$agencyId, $user->id];
    }

    private function ensureType(string $key, string $displayName, string $parserClass): MarketReportType
    {
        return MarketReportType::query()->firstOrCreate(
            ['key' => $key],
            [
                'display_name'         => $displayName,
                'parser_class'         => $parserClass,
                'expected_fields_json' => json_encode([]),
                'auto_approve'         => 1,
            ],
        );
    }

    private function ensureTypeForKey(string $key): MarketReportType
    {
        // Pull the FQCN from the registry so the test stays honest if the
        // mapping changes — same source of truth the controller uses.
        $registry = app(MarketReportParserRegistry::class);
        foreach ($registry->all() as $parser) {
            if ($parser->getReportTypeKey() === $key) {
                return $this->ensureType($key, ucfirst(str_replace('_', ' ', $key)), get_class($parser));
            }
        }
        $this->fail("No parser registered for key '{$key}' — guard test A should have caught this.");
    }

    private function fixtureUploadedFile(string $fixture): UploadedFile
    {
        $path = base_path('tests/Fixtures/market_reports/' . $fixture);
        $this->assertFileExists($path, "Test fixture missing: {$fixture}");
        return new UploadedFile($path, $fixture, 'application/pdf', null, true);
    }

    /**
     * Copy a real fixture PDF into the live local storage so pdftotext
     * (the parser actually reads bytes) sees a real file. Uses a
     * test-scope subdirectory under storage/app/market-reports/test/ that
     * the cleanup hook removes in tearDown. NOT using Storage::fake here
     * because the controller resolves the absolute path via the real disk
     * adapter; a faked disk's path doesn't always materialise on disk
     * across the request boundary inside RefreshDatabase's transaction.
     */
    private function stageRealFixtureOnDisk(int $agencyId, string $fixture): string
    {
        $relative = "market-reports/test/{$agencyId}/" . Str::random(12) . '.pdf';
        $absolute = Storage::disk('local')->path($relative);
        $dir = dirname($absolute);
        if (!is_dir($dir)) {
            $mkdirOk = mkdir($dir, 0777, true);
            $this->assertTrue($mkdirOk || is_dir($dir),
                "Could not create staging directory {$dir}");
        }
        $copyOk = copy(base_path('tests/Fixtures/market_reports/' . $fixture), $absolute);
        $this->assertTrue($copyOk,
            "Could not copy fixture {$fixture} to {$absolute}");
        $this->stagedPaths[] = $absolute;
        return $relative;
    }

    /** @var string[] */
    private array $stagedPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->stagedPaths as $absolute) {
            @unlink($absolute);
        }
        $this->stagedPaths = [];
        parent::tearDown();
    }
}
