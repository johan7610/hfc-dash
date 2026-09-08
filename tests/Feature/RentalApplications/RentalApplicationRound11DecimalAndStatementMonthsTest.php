<?php

declare(strict_types=1);

namespace Tests\Feature\RentalApplications;

use App\Models\Agency;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\RentalApplication;
use App\Models\RentalApplicationAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AT-392 — Round 11. Johan: "the comma rule is bust. everyone will enter
 * amounts like 40638.40, the std that everyone uses. right now hitting the
 * . on an amount clears the values." Root cause: type="number" inputs
 * bound with Alpine's x-model write the browser's own parsed .value back
 * into the DOM on every keystroke; a lone trailing "." doesn't parse as a
 * number yet, so the write-back silently drops it mid-type. Fixed by
 * switching every money input to type="text" inputmode="decimal" (no
 * native number parsing to interfere) and rewriting
 * RentalApplication::sanitizeNumericInput() to Johan's own disambiguation
 * rule: the LAST separator followed by exactly two digits is the decimal
 * point; everything else is a thousands mark.
 *
 * Also covers item 3 — the "number of months this bank statement covers"
 * field and the monthly-average DISPLAY it produces, deliberately NOT yet
 * wired into qualifyingResult()'s gross_income/meets_threshold pending
 * Johan's confirmation.
 */
final class RentalApplicationRound11DecimalAndStatementMonthsTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;
    private Branch $branch;
    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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
            'created_by_user_id' => $agent->id, 'status' => 'returned', 'submitted_at' => now()->subDay(),
        ], $attrs));
    }

    private function agent(): User
    {
        return User::factory()->create(['agency_id' => $this->agency->id, 'branch_id' => $this->branch->id, 'role' => 'admin']);
    }

    // ── Item 1 — Johan's exact disambiguation rule, every stated format ──

    #[DataProvider('johansExactFormats')]
    public function test_disambiguation_rule_resolves_every_format_johan_gave(string $typed, string $expected): void
    {
        $result = RentalApplication::sanitizeNumericInput(['amount' => $typed], ['amount']);

        $this->assertSame($expected, $result['amount'], "typed '{$typed}' must resolve to {$expected}");
    }

    public static function johansExactFormats(): array
    {
        return [
            'plain decimal, the standard everyone uses' => ['40638.40', '40638.40'],
            'comma thousands, dot decimal' => ['40,638.40', '40638.40'],
            'space thousands, dot decimal' => ['40 638.40', '40638.40'],
            'R-prefixed, space thousands, dot decimal' => ['R40 638.40', '40638.40'],
            'comma AS the decimal point' => ['40638,40', '40638.40'],
            'comma thousands, no decimal at all' => ['40,638', '40638'],
            'plain whole number, untouched' => ['40638', '40638'],
            // Existing Round 8 cases — must still resolve identically.
            'existing case: comma thousands' => ['8,500', '8500'],
            'existing case: space thousands' => ['8 500', '8500'],
            'existing case: R-prefixed space thousands' => ['R8 500', '8500'],
            'existing case: plain decimal' => ['8500.50', '8500.50'],
        ];
    }

    public function test_a_keystroke_never_wipes_what_was_already_typed(): void
    {
        // The literal failure mode: typing the "." character on its own
        // must never be treated as invalid/rejected by the server-side
        // sanitizer (the CLIENT-side fix is the input type change; this
        // proves the server never makes the situation worse either).
        $result = RentalApplication::sanitizeNumericInput(['amount' => '40638.'], ['amount']);

        // No 2 digits after the last separator -> treated as a thousands
        // mark and stripped, same as any other incomplete/ambiguous case
        // — but critically, it does NOT throw, error, or return empty.
        $this->assertNotSame('', $result['amount']);
        $this->assertSame('40638', $result['amount']);
    }

    public function test_review_screen_income_amount_field_is_not_a_native_number_input(): void
    {
        // Regression guard for the actual root cause: a native
        // type="number" input combined with Alpine's x-model write-back
        // is what silently ate the "." — never let this field revert to
        // type="number".
        $agent = $this->agent();
        $app = $this->application();

        $response = $this->actingAs($agent)->get(route('corex.rental-applications.review', $app));

        $response->assertOk();
        $response->assertDontSee('type="number" inputmode="decimal"', false);
        $response->assertSee('type="text" inputmode="decimal"', false);
    }

    public function test_authoriser_approve_amount_field_is_not_a_native_number_input(): void
    {
        $ro = User::factory()->create(['agency_id' => $this->agency->id, 'branch_id' => $this->branch->id, 'role' => 'admin']);
        $this->agency->update(['rental_application_ro_user_ids' => [$ro->id]]);
        $this->agency->refresh();
        $app = $this->application(['status' => 'under_assessment', 'submitted_for_approval_at' => now()]);

        $response = $this->actingAs($ro)->get(route('corex.rental-applications.authorisation.show', $app));

        $response->assertOk();
        $response->assertSee('type="text" inputmode="decimal" x-model="approveAmount"', false);
    }

    public function test_settings_percentage_field_is_not_run_through_the_money_disambiguation_rule(): void
    {
        // Johan's own rule assumes a Rand-and-cents shape (exactly two
        // decimal digits). "28.5" has only one — the money rule would
        // misread it as a thousands-separated whole number ("285"). This
        // was flagged to Johan rather than silently applied; confirms the
        // settings endpoint's own plain-trim path instead.
        $owner = User::factory()->create(['agency_id' => $this->agency->id, 'branch_id' => $this->branch->id, 'role' => 'admin']);

        $this->actingAs($owner)->post(
            route('corex.settings.rental-applications.qualifying-formula'),
            ['max_rent_percent_of_gross_income' => '28.5']
        )->assertSessionDoesntHaveErrors();

        $this->assertSame(28.50, \App\Models\RentalApplicationQualifyingSetting::maxRentPercentFor($this->agency->id));
    }

    // ── Item 3 — statement months + monthly average, NOT wired into the decision ──

    public function test_statement_months_persists_and_produces_a_monthly_average_display_value(): void
    {
        $agent = $this->agent();
        $app = $this->application(['current_rental_amount' => 5400]);

        $response = $this->actingAs($agent)->post(
            route('corex.rental-applications.review.assessment', $app),
            [
                'income_items' => [['description' => 'Salary', 'amount' => '54000']],
                'statement_months' => 3,
            ]
        );

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals(54000.0, $data['result']['gross_income']);
        $this->assertEquals(3, $data['result']['statement_months']);
        $this->assertEquals(18000.0, $data['result']['monthly_average_gross_income']);

        $assessment = RentalApplicationAssessment::where('rental_application_id', $app->id)->first();
        $this->assertSame(3, $assessment->statement_months);
    }

    public function test_monthly_average_does_not_affect_the_affordability_decision_yet(): void
    {
        // Deliberate — Johan asked to confirm before wiring this in. Two
        // assessments with the SAME raw total but different statement
        // lengths must produce the IDENTICAL meets_threshold/gross_income,
        // proving the decision still runs off the raw sum.
        $agent = $this->agent();

        $oneMonth = $this->application(['current_rental_amount' => 5400]);
        $this->actingAs($agent)->post(route('corex.rental-applications.review.assessment', $oneMonth), [
            'income_items' => [['description' => 'Salary', 'amount' => '18000']],
            'statement_months' => 1,
        ]);

        $threeMonths = $this->application(['current_rental_amount' => 5400]);
        $threeMonthsResult = $this->actingAs($agent)->post(route('corex.rental-applications.review.assessment', $threeMonths), [
            'income_items' => [['description' => 'Salary', 'amount' => '18000']],
            'statement_months' => 3,
        ])->json();

        // Same raw total (18000) either way — the decision must not
        // silently divide by statement_months internally.
        $this->assertEquals(18000.0, $threeMonthsResult['result']['gross_income']);
        $this->assertEquals(5400.0, $threeMonthsResult['result']['max_affordable_rent']);
        $this->assertTrue($threeMonthsResult['result']['meets_threshold']);
        // But the monthly average IS different, proving it's computed
        // independently and just not fed into the decision.
        $this->assertEquals(6000.0, $threeMonthsResult['result']['monthly_average_gross_income']);
    }

    public function test_no_statement_months_means_no_monthly_average(): void
    {
        $agent = $this->agent();
        $app = $this->application();

        $response = $this->actingAs($agent)->post(route('corex.rental-applications.review.assessment', $app), [
            'income_items' => [['description' => 'Salary', 'amount' => '18000']],
        ]);

        $data = $response->json();
        $this->assertNull($data['result']['monthly_average_gross_income']);
        $this->assertNull($data['result']['statement_months']);
    }
}
