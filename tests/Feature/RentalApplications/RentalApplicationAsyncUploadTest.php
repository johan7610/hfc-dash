<?php

declare(strict_types=1);

namespace Tests\Feature\RentalApplications;

use App\Models\Agency;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\RentalApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AT-392 — Johan, QA1: "I select docs and click submit... no docs arrive
 * back because i never clicked upload" and, on the SAME root cause,
 * "I complete all the information... attach a file, click upload and the
 * screen refreshes, and all my typed info is gone."
 *
 * Both are the same defect: the old upload action was a synchronous
 * form-POST-redirect, so using it reloaded the whole page — this public
 * form has no separate "save" step, so anything typed but not yet
 * submitted lived only in the browser and was discarded by that reload.
 *
 * The fix is async: uploadDocuments()/removeDocument()/replaceDocument()
 * now respond with JSON when the caller asks for it (real browser JS sends
 * Accept: application/json), so a document attaches with NO navigation at
 * all. These tests drive that JSON contract directly — the actual
 * no-reload behaviour lives in show.blade.php's Alpine component and
 * can't be exercised by a server-side HTTP test, but what CAN be proven
 * here, and is the thing that actually matters, is: a document attached
 * via this endpoint before submit() is called is genuinely on the
 * application when submit() runs — exactly what "I never clicked upload"
 * used to lose.
 */
final class RentalApplicationAsyncUploadTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;
    private Branch $branch;
    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->agency = Agency::create(['name' => 'Home Finders Coastal', 'slug' => 'hfc-' . uniqid()]);
        $this->branch = Branch::create(['agency_id' => $this->agency->id, 'name' => 'Ramsgate']);
        $this->contact = Contact::create([
            'agency_id' => $this->agency->id, 'branch_id' => $this->branch->id,
            'first_name' => 'Sipho', 'last_name' => 'Ndlovu', 'email' => 'sipho@example.co.za',
        ]);
    }

    private function application(array $attrs = []): RentalApplication
    {
        $agent = User::factory()->create(['agency_id' => $this->agency->id, 'branch_id' => $this->branch->id, 'role' => 'admin']);

        return RentalApplication::create(array_merge([
            'agency_id' => $this->agency->id, 'branch_id' => $this->branch->id, 'contact_id' => $this->contact->id,
            'created_by_user_id' => $agent->id, 'status' => 'sent',
            'token' => Str::random(64), 'token_expires_at' => now()->addDays(14),
        ], $attrs));
    }

    public function test_uploading_via_the_json_endpoint_attaches_the_document_before_submit_is_ever_called(): void
    {
        $application = $this->application();

        // This mirrors exactly what the async JS does the moment a file is
        // chosen — well before Submit is clicked, let alone pressed.
        $response = $this->postJson(route('rental-applications.public.documents', $application->token), [
            'supporting_files' => [UploadedFile::fake()->create('payslip.pdf', 100, 'application/pdf')],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['documents' => [['id', 'name', 'view_url']]]);

        $this->assertSame(1, $application->refresh()->documents()->count(), 'The document must be attached immediately, with no separate upload/submit step.');

        // Now submit — the document that was NEVER part of this request must
        // still be there afterwards. This is the exact "I never clicked
        // upload... no docs arrive back" scenario, proven false.
        $sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $this->post(route('rental-applications.public.submit', $application->token), [
            'declaration_signature' => $sig,
            'tpn_consent_signature' => $sig,
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame('returned', $application->status);
        $this->assertSame(1, $application->documents()->count(), 'The document attached before submit must survive it.');
    }

    public function test_a_rejected_upload_via_json_reports_exactly_which_file_and_why(): void
    {
        $application = $this->application();

        $response = $this->postJson(route('rental-applications.public.documents', $application->token), [
            'supporting_files' => [UploadedFile::fake()->create('not-a-real-doc.exe', 100, 'application/octet-stream')],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['supporting_files.0']);
        $this->assertSame(0, $application->refresh()->documents()->count(), 'A rejected file must never attach.');
    }

    public function test_json_replace_and_remove_never_hard_delete_and_respond_without_a_redirect(): void
    {
        $application = $this->application();

        $upload = $this->postJson(route('rental-applications.public.documents', $application->token), [
            'supporting_files' => [UploadedFile::fake()->create('original.pdf', 100, 'application/pdf')],
        ]);
        $docId = $upload->json('documents.0.id');

        $replaceResponse = $this->postJson(route('rental-applications.public.documents.replace', [$application->token, $docId]), [
            'replacement_file' => UploadedFile::fake()->create('corrected.pdf', 100, 'application/pdf'),
        ]);
        $replaceResponse->assertOk();
        $replaceResponse->assertJsonStructure(['document' => ['id', 'name', 'view_url'], 'replaced_id']);

        $this->assertNotNull(\App\Models\Document::withTrashed()->find($docId)->deleted_at, 'Replace must archive the old row, not delete it.');
        $newDocId = $replaceResponse->json('document.id');

        $removeResponse = $this->postJson(route('rental-applications.public.documents.remove', [$application->token, $newDocId]));
        $removeResponse->assertOk();
        $this->assertNotNull(\App\Models\Document::withTrashed()->find($newDocId)->deleted_at);
        $this->assertDatabaseHas('documents', ['id' => $newDocId]); // still exists — soft delete only
    }
}
