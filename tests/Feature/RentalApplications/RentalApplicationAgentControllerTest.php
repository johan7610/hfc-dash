<?php

declare(strict_types=1);

namespace Tests\Feature\RentalApplications;

use App\Models\Agency;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Property;
use App\Models\RentalApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Mail\RentalApplicationInviteMail;

/**
 * AT-392 — agent-facing Rental Applications. Spec: .ai/specs/rental-applications.md
 *
 * Johan tested the applicant side himself and hit a blocker within minutes;
 * this build assumed the agent side was equally under-tested and rendered
 * every screen through the kernel as a real user to check. It was — these
 * tests lock in the four real bugs found and fixed that session:
 *
 * 1. searchProperties() returned every listing type, not just rentals.
 * 2. show.blade.php rendered only ~40% of the V8 field list, so an agent
 *    could not see or edit most of what an applicant would submit.
 * 3. There was no document-download route at all on the agent side.
 * 4. There was no destroy/archive route — a full-CRUD gap.
 *
 * There was zero test coverage for this controller before this file.
 */
final class RentalApplicationAgentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private Agency $agency;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        // This worktree has no built frontend bundle (no npm install/build
        // run here) — irrelevant to what these tests assert (rendered HTML
        // text, DB state), so fake Vite rather than requiring an asset
        // build just to exercise this controller.
        $this->withoutVite();
        $this->agency = Agency::create(['name' => 'Home Finders Coastal', 'slug' => 'hfc-' . uniqid()]);
        $this->branch = Branch::create(['agency_id' => $this->agency->id, 'name' => 'Ramsgate']);
        $this->agent = User::factory()->create(['agency_id' => $this->agency->id, 'branch_id' => $this->branch->id, 'role' => 'admin']);
    }

    private function rentalProperty(string $title = 'House to let in Ramsgate'): Property
    {
        return Property::create([
            'agency_id' => $this->agency->id, 'branch_id' => $this->branch->id, 'agent_id' => $this->agent->id,
            'title' => $title, 'status' => 'active', 'property_type' => 'house', 'listing_type' => 'rental',
            'suburb' => 'Ramsgate', 'city' => 'Margate', 'province' => 'KwaZulu-Natal', 'address' => '1 Test Road',
        ]);
    }

    private function saleProperty(string $title = 'House for sale in Ramsgate'): Property
    {
        return Property::create([
            'agency_id' => $this->agency->id, 'branch_id' => $this->branch->id, 'agent_id' => $this->agent->id,
            'title' => $title, 'status' => 'active', 'property_type' => 'house', 'listing_type' => 'sale',
            'suburb' => 'Ramsgate', 'city' => 'Margate', 'province' => 'KwaZulu-Natal', 'address' => '2 Test Road',
        ]);
    }

    private function contact(): Contact
    {
        return Contact::create([
            'agency_id' => $this->agency->id, 'branch_id' => $this->branch->id,
            'first_name' => 'Sipho', 'last_name' => 'Ndlovu', 'email' => 'sipho@example.co.za', 'phone' => '0821234567',
        ]);
    }

    private function application(Contact $contact, array $attrs = []): RentalApplication
    {
        return RentalApplication::create(array_merge([
            'agency_id' => $this->agency->id, 'branch_id' => $this->branch->id, 'contact_id' => $contact->id,
            'created_by_user_id' => $this->agent->id, 'status' => 'sent',
        ], $attrs));
    }

    // ── Bug 1: property search must only return rental listings ─────────

    public function test_search_properties_returns_only_rental_listings(): void
    {
        $this->rentalProperty('Cosy flat to let in Ramsgate');
        $this->saleProperty('Beach house for sale in Ramsgate');

        $response = $this->actingAs($this->agent)->getJson(route('corex.rental-applications.search-properties', ['q' => 'Ramsgate']));

        $response->assertOk();
        $labels = collect($response->json())->pluck('label');
        $this->assertTrue($labels->contains('Cosy flat to let in Ramsgate'));
        $this->assertFalse($labels->contains('Beach house for sale in Ramsgate'), 'A for-sale listing must never appear in the rental-application property picker.');
    }

    // ── Bug 2: the show/edit form must render every V8 field ────────────

    public function test_show_renders_every_v8_field_and_the_applicants_real_data(): void
    {
        $app = $this->application($this->contact(), [
            'current_residential_address' => '14 Beacon Road, Ramsgate',
            'emergency_contact_name' => 'S Ndlovu',
            'current_landlord_name' => 'Coastal Rentals CC',
            'current_rental_amount' => 8500,
            'employer_address' => '1 Main Road, Margate',
            'employer_tel' => '0399121111',
            'occupation_date' => '2026-10-01',
            'special_conditions' => 'Has one small dog',
            'adults' => 2,
            'children' => 1,
        ]);

        $response = $this->actingAs($this->agent)->get(route('corex.rental-applications.show', $app));

        $response->assertOk();
        foreach ([
            'property_address_override', 'current_residential_address', 'emergency_contact_name',
            'emergency_contact_cell', 'emergency_contact_work', 'current_landlord_name', 'current_landlord_tel',
            'current_rental_amount', 'current_rental_from', 'current_rental_to', 'employer_address',
            'employer_tel', 'occupation_date', 'rental_terms', 'special_conditions', 'adults', 'children',
        ] as $field) {
            $response->assertSee('name="' . $field . '"', false);
        }
        $response->assertSee('Coastal Rentals CC', false);
        $response->assertSee('Has one small dog', false);
    }

    public function test_update_saves_every_v8_field_including_the_previously_unrenderable_ones(): void
    {
        $app = $this->application($this->contact());

        $response = $this->actingAs($this->agent)->put(route('corex.rental-applications.update', $app), [
            'current_residential_address' => '9 Beach Road',
            'emergency_contact_name' => 'Emergency Person',
            'current_landlord_name' => 'Old Landlord',
            'current_rental_amount' => 5000,
            'occupation_date' => '2026-11-01',
            'adults' => 3,
            'children' => 0,
        ]);

        $response->assertRedirect(route('corex.rental-applications.show', $app));
        $app->refresh();
        $this->assertSame('9 Beach Road', $app->current_residential_address);
        $this->assertSame('Emergency Person', $app->emergency_contact_name);
        $this->assertSame('Old Landlord', $app->current_landlord_name);
        $this->assertEquals(5000, $app->current_rental_amount);
        $this->assertSame(3, $app->adults);
    }

    // ── Bug 3: document download route + access control ─────────────────

    public function test_document_download_works_for_the_owning_agency(): void
    {
        Storage::fake('local');
        $app = $this->application($this->contact());
        Storage::disk('local')->put('rental-applications/' . $app->id . '/id.pdf', 'fake-pdf-bytes');
        $doc = Document::create([
            'agency_id' => $this->agency->id, 'branch_id' => $this->branch->id,
            'original_name' => 'ID.pdf', 'storage_path' => 'rental-applications/' . $app->id . '/id.pdf',
            'disk' => 'local', 'mime_type' => 'application/pdf', 'size' => 14,
            'source_type' => 'rental_application', 'source_id' => $app->id,
        ]);

        $response = $this->actingAs($this->agent)->get(route('corex.rental-applications.documents.download', [$app, $doc]));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=ID.pdf');
    }

    public function test_document_download_is_blocked_across_agencies(): void
    {
        Storage::fake('local');
        $app = $this->application($this->contact());
        Storage::disk('local')->put('rental-applications/' . $app->id . '/id.pdf', 'fake-pdf-bytes');
        $doc = Document::create([
            'agency_id' => $this->agency->id, 'branch_id' => $this->branch->id,
            'original_name' => 'ID.pdf', 'storage_path' => 'rental-applications/' . $app->id . '/id.pdf',
            'disk' => 'local', 'mime_type' => 'application/pdf', 'size' => 14,
            'source_type' => 'rental_application', 'source_id' => $app->id,
        ]);

        $otherAgency = Agency::create(['name' => 'A Different Agency', 'slug' => 'other-' . uniqid()]);
        $otherBranch = Branch::create(['agency_id' => $otherAgency->id, 'name' => 'HQ']);
        $otherAdmin = User::factory()->create(['agency_id' => $otherAgency->id, 'branch_id' => $otherBranch->id, 'role' => 'admin']);

        $response = $this->actingAs($otherAdmin)->get(route('corex.rental-applications.documents.download', [$app, $doc]));

        $response->assertStatus(404);
    }

    // ── Bug 4: full CRUD — archive is a soft delete, never a hard delete ─

    public function test_destroy_soft_deletes_and_never_hard_deletes(): void
    {
        $app = $this->application($this->contact());

        $response = $this->actingAs($this->agent)->delete(route('corex.rental-applications.destroy', $app));

        $response->assertRedirect(route('corex.rental-applications.index'));
        $this->assertSoftDeleted('rental_applications', ['id' => $app->id]);
        $this->assertDatabaseHas('rental_applications', ['id' => $app->id]);
    }

    public function test_archived_application_disappears_from_the_index(): void
    {
        $app = $this->application($this->contact(), ['full_name' => 'Archived Applicant']);
        $app->delete();

        $response = $this->actingAs($this->agent)->get(route('corex.rental-applications.index'));

        $response->assertOk();
        $response->assertDontSee('Archived Applicant');
    }

    // ── Cross-agency isolation, direct model-level guarantee ─────────────

    public function test_a_second_agencys_application_is_never_visible(): void
    {
        $app = $this->application($this->contact());

        $otherAgency = Agency::create(['name' => 'A Different Agency', 'slug' => 'other-' . uniqid()]);
        $otherBranch = Branch::create(['agency_id' => $otherAgency->id, 'name' => 'HQ']);
        $otherAdmin = User::factory()->create(['agency_id' => $otherAgency->id, 'branch_id' => $otherBranch->id, 'role' => 'admin']);

        $this->actingAs($otherAdmin)->get(route('corex.rental-applications.show', $app))->assertStatus(404);
        $this->actingAs($otherAdmin)->get(route('corex.rental-applications.index'))
            ->assertDontSee(route('corex.rental-applications.show', $app), false);
    }

    // ── Bug 5: "moans no email correctly, but adding and saving do not
    // persist" (Johan, QA1) — the email genuinely persisted the whole time;
    // send()/sendInvite() just never read the field this screen lets the
    // agent edit. See RentalApplication::recipientEmail(). ─────────────────

    public function test_editing_the_applications_own_email_field_is_what_send_actually_uses(): void
    {
        Mail::fake();

        $contactWithNoEmail = Contact::create([
            'agency_id' => $this->agency->id, 'branch_id' => $this->branch->id,
            'first_name' => 'No', 'last_name' => 'Email', 'email' => null,
        ]);
        $app = $this->application($contactWithNoEmail);

        // Send correctly refuses to mail and says so — the contact has no email.
        $this->actingAs($this->agent)->post(route('corex.rental-applications.send', $app))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'no email on file'));
        Mail::assertNothingSent();

        // The agent types an email into the application's OWN field (the only
        // one this screen offers) and saves.
        $this->actingAs($this->agent)->put(route('corex.rental-applications.update', $app), [
            'email' => 'corrected@example.com',
        ])->assertRedirect(route('corex.rental-applications.show', $app));

        $app->refresh();
        $this->assertSame('corrected@example.com', $app->email, 'The email must actually persist — it always did; this asserts it still does.');

        // Sending again must now use exactly that address — not the still-empty contact email.
        $this->assertNull($contactWithNoEmail->fresh()->email, 'This fix must not write back to the contact record — out of scope for this bug.');
        $this->actingAs($this->agent)->post(route('corex.rental-applications.send', $app))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Sent to corrected@example.com'));

        Mail::assertSent(RentalApplicationInviteMail::class, function ($mail) {
            return $mail->hasTo('corrected@example.com');
        });
    }

    // ── Regression: validation fails, user corrects, save persists — and
    // every OTHER field on the form survives too, not just the one being
    // fixed. Root cause: three <textarea> fields and one <select> read
    // straight from the DB value on redisplay instead of old(), so ANY
    // validation failure elsewhere on this one-big-form silently reverted
    // them (while old()-aware fields correctly kept what was typed). ──────

    public function test_every_field_on_the_form_survives_a_validation_failure_not_just_the_corrected_one(): void
    {
        $app = $this->application($this->contact(), [
            'full_name' => 'Original Name',
            'current_residential_address' => 'Original Address',
            'employer_address' => 'Original Employer Address',
            'special_conditions' => 'Original Conditions',
            'employment_type' => 'permanently_employed',
        ]);

        // Fill the whole form with new values, but deliberately break ONE
        // field (adults must be an integer) to force a validation failure.
        $response = $this->actingAs($this->agent)->from(route('corex.rental-applications.show', $app))
            ->put(route('corex.rental-applications.update', $app), [
                'full_name' => 'New Name',
                'email' => 'new@example.com',
                'current_residential_address' => 'New Address',
                'employer_address' => 'New Employer Address',
                'special_conditions' => 'New Conditions',
                'employment_type' => 'business_owner_personal_account',
                'adults' => 'not-a-number',
            ]);

        $response->assertRedirect(route('corex.rental-applications.show', $app));
        $response->assertSessionHasErrors('adults');

        // Nothing was written to the DB — this is an all-or-nothing form save.
        $app->refresh();
        $this->assertSame('Original Name', $app->full_name);
        $this->assertSame('Original Address', $app->current_residential_address);

        // Every field the agent typed — including the three raw <textarea>s
        // and the <select> that had no old() at all — must still show what
        // was typed on the redisplayed page, not the stale DB value.
        $show = $this->actingAs($this->agent)->get(route('corex.rental-applications.show', $app));
        $show->assertSee('New Name');
        $show->assertSee('new@example.com');
        $show->assertSee('New Address');
        $show->assertSee('New Employer Address');
        $show->assertSee('New Conditions');
        $show->assertSee('business_owner_personal_account', false);
        $show->assertDontSee('Original Address');
        $show->assertDontSee('Original Employer Address');
        $show->assertDontSee('Original Conditions');

        // The agent then fixes the one broken field and saves again —
        // everything, including the previously-reverting fields, must land.
        $this->actingAs($this->agent)->put(route('corex.rental-applications.update', $app), [
            'full_name' => 'New Name',
            'email' => 'new@example.com',
            'current_residential_address' => 'New Address',
            'employer_address' => 'New Employer Address',
            'special_conditions' => 'New Conditions',
            'employment_type' => 'business_owner_personal_account',
            'adults' => 3,
        ])->assertSessionDoesntHaveErrors();

        $app->refresh();
        $this->assertSame('New Name', $app->full_name);
        $this->assertSame('new@example.com', $app->email);
        $this->assertSame('New Address', $app->current_residential_address);
        $this->assertSame('New Employer Address', $app->employer_address);
        $this->assertSame('New Conditions', $app->special_conditions);
        $this->assertSame('business_owner_personal_account', $app->employment_type);
        $this->assertSame(3, $app->adults);
    }
}
