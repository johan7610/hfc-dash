<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CompanyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 9c-3 — CompanyDocument lifecycle + public access tests.
 */
final class CompanyDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_company_document_for_agency(): void
    {
        $agencyId = $this->makeAgency();

        $doc = CompanyDocument::create([
            'agency_id'     => $agencyId,
            'document_type' => 'privacy_policy',
            'title'         => 'Privacy Policy',
            'content'       => '# Privacy Policy',
        ]);

        $this->assertNotEmpty($doc->public_token);
        $this->assertGreaterThanOrEqual(40, strlen($doc->public_token));
        // Refresh to pick up DB default (is_published defaults to false).
        $this->assertFalse((bool) $doc->fresh()->is_published);
    }

    public function test_token_uniqueness_enforced(): void
    {
        $a1 = $this->makeAgency();
        $a2 = $this->makeAgency();

        $doc1 = CompanyDocument::create([
            'agency_id' => $a1, 'document_type' => 'privacy_policy',
            'title' => 'P', 'content' => '',
        ]);
        $doc2 = CompanyDocument::create([
            'agency_id' => $a2, 'document_type' => 'privacy_policy',
            'title' => 'P', 'content' => '',
        ]);

        $this->assertNotSame($doc1->public_token, $doc2->public_token);
    }

    public function test_public_url_renders_when_published(): void
    {
        $agencyId = $this->makeAgency();
        $doc = CompanyDocument::create([
            'agency_id'    => $agencyId,
            'document_type'=> 'privacy_policy',
            'title'        => 'Privacy Policy',
            'content'      => "# Privacy Policy\n\nThis is our policy.",
            'is_published' => true,
            'published_at' => now(),
        ]);

        $resp = $this->get(route('public.company-document', ['token' => $doc->public_token]));
        $resp->assertOk();
        $resp->assertSee('Privacy Policy', false);
        $resp->assertSee('This is our policy.', false);
    }

    public function test_public_url_returns_404_when_unpublished(): void
    {
        $agencyId = $this->makeAgency();
        $doc = CompanyDocument::create([
            'agency_id'    => $agencyId,
            'document_type'=> 'privacy_policy',
            'title'        => 'Privacy Policy',
            'content'      => 'draft',
            'is_published' => false,
        ]);

        $this->get(route('public.company-document', ['token' => $doc->public_token]))
            ->assertNotFound();
    }

    public function test_public_url_returns_404_for_bogus_token(): void
    {
        // Token must satisfy 40-64 char regex on the route — use a valid-shape value
        // that doesn't exist in the DB.
        $bogus = str_repeat('z', 48);
        $this->get('/legal/' . $bogus)->assertNotFound();
    }

    public function test_agency_privacy_policy_url_prefers_published_doc(): void
    {
        $agencyId = $this->makeAgency();
        $agency = Agency::find($agencyId);
        $agency->popi_url = 'https://example.com/legacy-privacy';
        $agency->save();

        $this->assertSame(
            'https://example.com/legacy-privacy',
            $agency->fresh()->privacy_policy_url,
            'falls back to legacy popi_url when no published CompanyDocument exists'
        );

        $doc = CompanyDocument::create([
            'agency_id'    => $agencyId,
            'document_type'=> 'privacy_policy',
            'title'        => 'Privacy Policy',
            'content'      => '...',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->assertStringContainsString(
            $doc->public_token,
            $agency->fresh()->privacy_policy_url,
            'published CompanyDocument wins over popi_url'
        );
    }

    public function test_unpublished_doc_falls_back_to_popi_url(): void
    {
        $agencyId = $this->makeAgency();
        $agency = Agency::find($agencyId);
        $agency->popi_url = 'https://example.com/legacy';
        $agency->save();

        CompanyDocument::create([
            'agency_id'    => $agencyId,
            'document_type'=> 'privacy_policy',
            'title'        => 'Privacy Policy',
            'content'      => 'draft',
            'is_published' => false,
        ]);

        $this->assertSame('https://example.com/legacy', $agency->fresh()->privacy_policy_url);
    }

    // ── Helpers ──

    private function makeAgency(): int
    {
        return (int) DB::table('agencies')->insertGetId([
            'name' => 'Test ' . Str::random(6),
            'slug' => 'test-' . Str::random(8),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
