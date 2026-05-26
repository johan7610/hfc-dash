<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 9c-3 rebuild — privacy policy as a Company Settings field with
 * per-branch override + public token route.
 */
final class PrivacyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_generated_when_content_first_saved(): void
    {
        $agency = $this->makeAgency();
        $this->assertNull($agency->privacy_policy_token);

        // Mimic the controller behaviour: when content is saved and token is
        // null, the controller generates one.
        $agency->privacy_policy_markdown = '## Test';
        if (empty($agency->privacy_policy_token)) {
            $agency->privacy_policy_token = $agency->generatePrivacyPolicyToken();
        }
        $agency->save();

        $this->assertNotEmpty($agency->fresh()->privacy_policy_token);
        $this->assertGreaterThanOrEqual(40, strlen($agency->fresh()->privacy_policy_token));
    }

    public function test_token_persists_across_edits(): void
    {
        $agency = $this->makeAgency();
        $agency->privacy_policy_markdown = '## first';
        $agency->privacy_policy_token = $agency->generatePrivacyPolicyToken();
        $agency->save();

        $original = $agency->fresh()->privacy_policy_token;

        // Subsequent content edit must NOT regenerate the token.
        $agency->privacy_policy_markdown = '## second';
        $agency->save();

        $this->assertSame($original, $agency->fresh()->privacy_policy_token,
            'token must persist across edits — agents may have shared the link');
    }

    public function test_effective_popi_url_cascade(): void
    {
        $agency = $this->makeAgency();

        // 1. Nothing set → null.
        $this->assertNull($agency->effectivePopiUrl());

        // 2. External popi_url only → returns the external URL.
        $agency->popi_url = 'https://example.com/legacy-popi';
        $agency->save();
        $this->assertSame('https://example.com/legacy-popi', $agency->fresh()->effectivePopiUrl());

        // 3. Internal published privacy policy → wins over external.
        $agency->privacy_policy_markdown = '## Policy';
        $agency->privacy_policy_token = $agency->generatePrivacyPolicyToken();
        $agency->privacy_policy_published_at = now();
        $agency->save();
        $url = $agency->fresh()->effectivePopiUrl();
        $this->assertStringContainsString('/legal/privacy/', $url);
        $this->assertStringContainsString($agency->privacy_policy_token, $url);

        // 4. Unpublish → falls back to external popi_url.
        $agency->privacy_policy_published_at = null;
        $agency->save();
        $this->assertSame('https://example.com/legacy-popi', $agency->fresh()->effectivePopiUrl());
    }

    public function test_public_route_renders_when_published(): void
    {
        $agency = $this->makeAgency();
        $agency->privacy_policy_markdown = "# Privacy\n\nWe respect your data.";
        $agency->privacy_policy_token = $agency->generatePrivacyPolicyToken();
        $agency->privacy_policy_published_at = now();
        $agency->save();

        $resp = $this->get(route('public.privacy-policy', ['token' => $agency->privacy_policy_token]));
        $resp->assertOk();
        $resp->assertSee('We respect your data.', false);
        $resp->assertSee($agency->name, false);
    }

    public function test_public_route_404s_when_unpublished(): void
    {
        $agency = $this->makeAgency();
        $agency->privacy_policy_markdown = '## draft';
        $agency->privacy_policy_token = $agency->generatePrivacyPolicyToken();
        // Not setting published_at.
        $agency->save();

        $this->get(route('public.privacy-policy', ['token' => $agency->privacy_policy_token]))
            ->assertNotFound();
    }

    public function test_public_route_404s_on_invalid_token(): void
    {
        // Token regex on the route requires 40-64 chars — use a valid-shape
        // value that doesn't match any row.
        $bogus = str_repeat('z', 48);
        $this->get('/legal/privacy/' . $bogus)->assertNotFound();
    }

    public function test_branch_override_wins_when_published(): void
    {
        $agencyId = $this->makeAgency()->id;
        $branch = Branch::create([
            'agency_id' => $agencyId,
            'name'      => 'Branch override',
        ]);

        // Agency publishes one version.
        $agency = Agency::find($agencyId);
        $agency->privacy_policy_markdown = '## Agency version';
        $agency->privacy_policy_token = $agency->generatePrivacyPolicyToken();
        $agency->privacy_policy_published_at = now();
        $agency->save();

        // Branch publishes its own override.
        $branch->privacy_policy_markdown = '## Branch override version';
        $branch->privacy_policy_token = $agency->generatePrivacyPolicyToken();
        $branch->privacy_policy_published_at = now();
        $branch->save();

        // effectivePrivacyPolicyUrl: branch wins.
        $this->assertStringContainsString(
            $branch->privacy_policy_token,
            $branch->fresh()->effectivePrivacyPolicyUrl()
        );

        // Public route serves the branch content under branch identity.
        $resp = $this->get(route('public.privacy-policy', ['token' => $branch->privacy_policy_token]));
        $resp->assertOk();
        $resp->assertSee('Branch override version', false);
        $resp->assertDontSee('Agency version', false);
    }

    public function test_branch_inherits_when_override_blank(): void
    {
        $agencyId = $this->makeAgency()->id;
        $agency = Agency::find($agencyId);
        $agency->privacy_policy_markdown = '## Agency version inherited';
        $agency->privacy_policy_token = $agency->generatePrivacyPolicyToken();
        $agency->privacy_policy_published_at = now();
        $agency->save();

        $branch = Branch::create([
            'agency_id' => $agencyId,
            'name'      => 'Inheriting branch',
        ]);

        // Branch has no override — effective URL uses agency token.
        $this->assertSame($agency->fresh()->privacy_policy_token, $branch->fresh()->effectivePrivacyPolicyToken());
        $this->assertStringContainsString(
            $agency->fresh()->privacy_policy_token,
            $branch->fresh()->effectivePrivacyPolicyUrl()
        );
    }

    private function makeAgency(): Agency
    {
        $id = (int) DB::table('agencies')->insertGetId([
            'name' => 'Test ' . Str::random(6),
            'slug' => 'test-' . Str::random(8),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return Agency::find($id);
    }
}
