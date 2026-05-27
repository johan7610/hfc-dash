<?php

declare(strict_types=1);

namespace Tests\Feature\RecipientLoop;

use App\Models\Docuperfect\SignatureRequest;
use App\Models\Docuperfect\SignatureTemplate;
use App\Models\Docuperfect\Template as DocuperfectTemplate;
use App\Services\Docuperfect\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Recipient Loop Engine — B1 indexed recipient identity.
 *
 * Schema: role_index column on signature_requests + migration backfill.
 * Helpers: mapSigningPartyKeys() auto-numbers duplicates; new
 * roleDisplayLabel() per-token method.
 * Service: createSigningRequest() splits legacy suffixed party_role
 * into clean (party_role, role_index) at insert time.
 */
final class IndexedIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_identity_accessor_returns_suffixed_form(): void
    {
        $req = new SignatureRequest();
        $req->party_role = 'seller';
        $req->role_index = 2;
        $this->assertSame('seller_2', $req->role_identity);

        $req2 = new SignatureRequest();
        $req2->party_role = 'agent';
        $req2->role_index = 1;
        $this->assertSame('agent_1', $req2->role_identity);
    }

    public function test_role_identity_defaults_to_index_1_when_role_index_missing(): void
    {
        $req = new SignatureRequest();
        $req->party_role = 'seller';
        // role_index unset → accessor falls back to 1.
        $this->assertSame('seller_1', $req->role_identity);
    }

    public function test_map_signing_party_keys_auto_numbers_duplicates(): void
    {
        $result = DocuperfectTemplate::mapSigningPartyKeys(
            ['owner_party', 'owner_party', 'acquiring_party', 'agent'],
            true, // sales
        );
        $this->assertSame(['Seller 1', 'Seller 2', 'Buyer', 'Agent'], $result);
    }

    public function test_map_signing_party_keys_singletons_unchanged(): void
    {
        // Backward-compat: existing single-recipient documents must look identical.
        $result = DocuperfectTemplate::mapSigningPartyKeys(
            ['owner_party', 'acquiring_party', 'agent'],
            true,
        );
        $this->assertSame(['Seller', 'Buyer', 'Agent'], $result);
    }

    public function test_map_signing_party_keys_rentals_multi_lessor_multi_lessee(): void
    {
        $result = DocuperfectTemplate::mapSigningPartyKeys(
            ['owner_party', 'owner_party', 'acquiring_party', 'acquiring_party', 'agent'],
            false, // rentals
        );
        $this->assertSame(['Lessor 1', 'Lessor 2', 'Lessee 1', 'Lessee 2', 'Agent'], $result);
    }

    public function test_role_display_label_indexed_when_multi(): void
    {
        $this->assertSame(
            'Seller 2',
            DocuperfectTemplate::roleDisplayLabel('owner_party', true, 2, 4),
        );
    }

    public function test_role_display_label_singleton_no_index(): void
    {
        $this->assertSame(
            'Seller',
            DocuperfectTemplate::roleDisplayLabel('owner_party', true, 1, 1),
        );
    }

    public function test_role_display_label_handles_wizard_raw_tokens(): void
    {
        // Wizard stores 'seller' / 'lessor' / 'lessee' / 'landlord' / 'tenant'
        // tokens directly on signature_requests.party_role. The helper must
        // recognise both the canonical owner_party AND these aliases.
        $this->assertSame('Seller', DocuperfectTemplate::roleDisplayLabel('seller', true, 1, 1));
        $this->assertSame('Lessor 2', DocuperfectTemplate::roleDisplayLabel('lessor', false, 2, 3));
        $this->assertSame('Lessor', DocuperfectTemplate::roleDisplayLabel('landlord', false, 1, 1));
        $this->assertSame('Lessee', DocuperfectTemplate::roleDisplayLabel('tenant', false, 1, 1));
    }

    public function test_create_signing_request_splits_legacy_suffixed_party_role(): void
    {
        $template = $this->seedSignatureTemplate();
        $svc = app(SignatureService::class);

        // Legacy caller passes 'seller_3' as party_role; service must split it.
        $req = $svc->createSigningRequest(
            $template,
            'seller_3',
            'Alice',
            'alice@example.com',
        );

        $this->assertSame('seller', $req->party_role);
        $this->assertSame(3, $req->role_index);
        $this->assertSame('seller_3', $req->role_identity);
    }

    public function test_create_signing_request_honours_explicit_role_index(): void
    {
        $template = $this->seedSignatureTemplate();
        $svc = app(SignatureService::class);

        // New caller passes clean party_role + explicit role_index.
        $req = $svc->createSigningRequest(
            template:         $template,
            partyRole:        'seller',
            signerName:       'Bob',
            signerEmail:      'bob@example.com',
            signerIdNumber:   null,
            message:          null,
            sentBy:           null,
            ficaRequired:     false,
            contactId:        null,
            ficaSubmissionId: null,
            roleIndex:        2,
        );

        $this->assertSame('seller', $req->party_role);
        $this->assertSame(2, $req->role_index);
    }

    public function test_create_signing_request_defaults_to_index_1_when_neither_supplied(): void
    {
        $template = $this->seedSignatureTemplate();
        $svc = app(SignatureService::class);

        $req = $svc->createSigningRequest($template, 'agent', 'Agent', 'agent@example.com');

        $this->assertSame('agent', $req->party_role);
        $this->assertSame(1, $req->role_index);
    }

    public function test_for_role_instance_scope_filters_by_role_and_index(): void
    {
        $template = $this->seedSignatureTemplate();
        $svc = app(SignatureService::class);
        $svc->createSigningRequest($template, 'seller_1', 'A', 'a@x.com');
        $svc->createSigningRequest($template, 'seller_2', 'B', 'b@x.com');
        $svc->createSigningRequest($template, 'seller_3', 'C', 'c@x.com');

        $row = SignatureRequest::where('signature_template_id', $template->id)
            ->forRoleInstance('seller', 2)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('B', $row->signer_name);
    }

    public function test_migration_backfill_strips_suffix_and_populates_role_index(): void
    {
        // Simulate the pre-migration state: insert a row with suffixed party_role
        // and role_index=1 (the default), then run the backfill chunk logic
        // manually against this row. This isolates the backfill correctness
        // without needing to roll back the live migration.
        $template = $this->seedSignatureTemplate();
        $svc = app(SignatureService::class);

        // Use the service's auto-split to land a clean row, then deliberately
        // re-suffix to mimic legacy state.
        $req = $svc->createSigningRequest($template, 'seller', 'Legacy', 'l@x.com');
        DB::table('signature_requests')->where('id', $req->id)->update([
            'party_role' => 'seller_4',
            'role_index' => 1, // pre-backfill default
        ]);

        // Replay the migration's backfill regex on this single row.
        $row = DB::table('signature_requests')->where('id', $req->id)->first();
        if (preg_match('/^(.+)_(\d+)$/', (string) $row->party_role, $m)) {
            DB::table('signature_requests')->where('id', $req->id)->update([
                'party_role' => $m[1],
                'role_index' => (int) $m[2],
            ]);
        }

        $fresh = SignatureRequest::find($req->id);
        $this->assertSame('seller', $fresh->party_role);
        $this->assertSame(4, $fresh->role_index);
        $this->assertSame('seller_4', $fresh->role_identity);
    }

    // ── Helpers ──

    private function seedSignatureTemplate(): SignatureTemplate
    {
        $userId = (int) DB::table('users')->insertGetId([
            'name'       => 'Test User',
            'email'      => 'test-' . Str::random(8) . '@example.test',
            'password'   => bcrypt('password'),
            'role'       => 'agent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $documentId = (int) DB::table('docuperfect_documents')->insertGetId([
            'name'         => 'Test ' . Str::random(6),
            'document_type'=> 'agreement',
            'owner_id'     => $userId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return SignatureTemplate::create([
            'document_id'   => $documentId,
            'document_hash' => Str::random(64),
            'status'        => 'draft',
            'created_by'    => $userId,
        ]);
    }
}
