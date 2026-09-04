<?php

namespace Tests\Feature\Masters;

use App\Models\Agent;
use App\Models\CalculationBasis;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use App\Services\Masters\AgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['status' => true]);
        $user->assignRole($role);
        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * A complete supplier-side agent. Every field the form requires is here,
     * so a test that cares about one rule can override just that key and still
     * reach the rule it is testing rather than failing on a missing sibling.
     *
     * The PAN matches characters 3–12 of the GSTIN — the request cross-checks
     * them, so a mismatched pair would fail every test in the file.
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'agent_type'   => 'supplier',
            'name'         => 'Test Agent Name',
            'display_code' => 'AGT01',
            'categories'   => [$this->category()->id],

            'phone'   => '+91 98200 41122',
            'city'    => 'Mumbai',
            'address' => '12 Kalbadevi Road, Mumbai 400002',

            'gst_number' => '27AAECS1429B1Z6',
            'pan_number' => 'AAECS1429B',

            'bank_name'      => 'HDFC Bank',
            'account_number' => '50200012345678',
            'ifsc_code'      => 'HDFC0000518',

            'calculation_basis_id' => CalculationBasis::where('name', 'Net Value')->value('id'),
            'commissions'          => [
                ['commission_type' => 'percent', 'amount' => '2.5', 'currency_id' => null],
            ],
            'commission_paid_by' => 'us',
            'payment_term'       => 'after_supplier',

            'status' => 'active',
        ], $overrides);
    }

    /**
     * A buyer-side payload: no Indian tax or IFSC, SWIFT instead.
     */
    private function buyerSidePayload(array $overrides = []): array
    {
        return array_merge($this->payload([
            'agent_type'           => 'buyer',
            'display_code'         => 'AGT02',
            'calculation_basis_id' => CalculationBasis::where('name', 'FOB Value')->value('id'),
            'commission_paid_by'   => 'us',
            'payment_term'         => 'after_buyer',
            'swift_code'           => 'HBUKGB4B',
        ]), $overrides) + ['gst_number' => null, 'pan_number' => null, 'ifsc_code' => null];
    }

    /**
     * Agents require at least one category, so every payload needs one.
     *
     * forceCreate, not firstOrCreate: `code` is not fillable — it is assigned
     * by NumberSeriesService so a crafted POST cannot choose one — and
     * firstOrCreate mass-assigns, which drops it and hits a NOT NULL column.
     */
    private function category(): Category
    {
        return Category::firstWhere('name', 'Agent Test Category')
            ?? Category::forceCreate([
                'code'   => 'CAT90',
                'name'   => 'Agent Test Category',
                'status' => 'active',
            ]);
    }

    /**
     * Creating an Agent straight from a payload would try to mass-assign the
     * `categories` and `commissions` keys, which are not columns. The service
     * is what the controller uses, so the tests use it too.
     */
    private function makeAgent(array $overrides = []): Agent
    {
        return app(AgentService::class)->create($this->payload($overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions & Routing
    |--------------------------------------------------------------------------
    */

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('masters.agents.index'))->assertRedirect(route('login'));
    }

    public function test_a_role_without_agent_view_is_denied(): void
    {
        $user = $this->actingAsRole('Jobworker'); // No agent permissions

        $this->actingAs($user)->get(route('masters.agents.index'))->assertStatus(403);
    }

    public function test_a_view_only_role_cannot_create_or_delete(): void
    {
        // Merchandising & Manufacturing has agent.view but not agent.create.
        $viewUser = $this->actingAsRole('Merchandising & Manufacturing');

        $this->actingAs($viewUser)->get(route('masters.agents.create'))->assertStatus(403);
        $this->actingAs($viewUser)->post(route('masters.agents.store'), $this->payload())->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD & Validation
    |--------------------------------------------------------------------------
    */

    public function test_an_agent_can_be_created_with_categories_and_basis(): void
    {
        $user  = $this->actingAsRole('Super Admin');
        $basis = CalculationBasis::where('name', 'Net Value')->first();
        $cat1  = Category::forceCreate(['code' => 'CAT91', 'name' => 'Category 91', 'status' => 'active']);
        $cat2  = Category::forceCreate(['code' => 'CAT92', 'name' => 'Category 92', 'status' => 'active']);

        $response = $this->actingAs($user)->post(route('masters.agents.store'), $this->payload([
            'calculation_basis_id' => $basis->id,
            'categories'           => [$cat1->id, $cat2->id],
        ]));

        $response->assertRedirect(route('masters.agents.index'));

        $this->assertDatabaseHas('agents', [
            'display_code'         => 'AGT01',
            'name'                 => 'Test Agent Name',
            'agent_type'           => 'supplier',
            'calculation_basis_id' => $basis->id,
            'commission_paid_by'   => 'us',
            'payment_term'         => 'after_supplier',
            'city'                 => 'Mumbai',
            'gst_number'           => '27AAECS1429B1Z6',
            'ifsc_code'            => 'HDFC0000518',
        ]);

        $agent = Agent::where('display_code', 'AGT01')->first();
        $this->assertCount(2, $agent->categories);
    }

    /*
    |--------------------------------------------------------------------------
    | Commission entries
    |--------------------------------------------------------------------------
    */

    public function test_commission_entries_are_saved_in_order(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.agents.store'), $this->payload([
            'commissions' => [
                ['commission_type' => 'percent', 'amount' => '2.5', 'currency_id' => null],
                ['commission_type' => 'fixed',   'amount' => '12',  'currency_id' => null],
            ],
        ]))->assertRedirect(route('masters.agents.index'));

        $commissions = Agent::where('display_code', 'AGT01')->first()->commissions;

        $this->assertCount(2, $commissions);
        $this->assertSame('percent', $commissions[0]->commission_type);
        $this->assertSame(0, $commissions[0]->sort_order);
        $this->assertSame('fixed', $commissions[1]->commission_type);
        $this->assertSame(1, $commissions[1]->sort_order);
    }

    public function test_at_least_one_commission_entry_is_required(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->payload(['commissions' => []]))
            ->assertSessionHasErrors('commissions');
    }

    public function test_a_blank_commission_row_is_skipped_rather_than_saved_as_zero(): void
    {
        // The repeater keeps at least one row on screen, so a user who clears
        // the second entry posts an empty row rather than removing it.
        $agent = $this->makeAgent([
            'commissions' => [
                ['commission_type' => 'percent', 'amount' => '2.5', 'currency_id' => null],
                ['commission_type' => 'fixed',   'amount' => '',    'currency_id' => null],
            ],
        ]);

        $this->assertCount(1, $agent->commissions);
    }

    public function test_editing_replaces_the_commission_entries(): void
    {
        $user  = $this->actingAsRole('Super Admin');
        $agent = $this->makeAgent([
            'commissions' => [
                ['commission_type' => 'percent', 'amount' => '2.5', 'currency_id' => null],
                ['commission_type' => 'fixed',   'amount' => '12',  'currency_id' => null],
            ],
        ]);

        $this->actingAs($user)->put(route('masters.agents.update', $agent), $this->payload([
            'commissions' => [
                ['commission_type' => 'percent', 'amount' => '4', 'currency_id' => null],
            ],
        ]))->assertRedirect(route('masters.agents.index'));

        $commissions = $agent->refresh()->commissions;

        $this->assertCount(1, $commissions);
        $this->assertSame('4.0000', $commissions[0]->amount);

        // Scoped to this agent. A global assertDatabaseCount would be asserting
        // the seeder's size — every seeded agent carries an entry of its own.
        $this->assertSame(1, $agent->commissions()->count(), 'The replaced entry must not be left behind.');
    }

    /*
    |--------------------------------------------------------------------------
    | Side-dependent fields
    |--------------------------------------------------------------------------
    */

    public function test_a_supplier_side_agent_requires_gst_pan_and_ifsc(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->payload([
                'gst_number' => null,
                'pan_number' => null,
                'ifsc_code'  => null,
            ]))
            ->assertSessionHasErrors(['gst_number', 'pan_number', 'ifsc_code']);
    }

    public function test_a_buyer_side_agent_requires_swift_and_rejects_indian_tax_fields(): void
    {
        $user = $this->actingAsRole('Super Admin');

        // SWIFT missing.
        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->buyerSidePayload(['swift_code' => null]))
            ->assertSessionHasErrors('swift_code');

        /*
         * A GSTIN posted for a buyer-side agent is nulled by
         * prepareForValidation rather than rejected — the form has already
         * hidden the box, so the value can only be stale or crafted, and in
         * both cases dropping it is the right answer.
         */
        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->buyerSidePayload([
                'gst_number' => '27AAECS1429B1Z6',
                'ifsc_code'  => 'HDFC0000518',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('agents', [
            'display_code' => 'AGT02',
            'gst_number'   => null,
            'ifsc_code'    => null,
            'swift_code'   => 'HBUKGB4B',
        ]);
    }

    public function test_switching_a_supplier_agent_to_buyer_side_clears_its_indian_bank_details(): void
    {
        $user  = $this->actingAsRole('Super Admin');
        $agent = $this->makeAgent();

        $this->assertSame('HDFC0000518', $agent->ifsc_code);

        $this->actingAs($user)->put(
            route('masters.agents.update', $agent),
            $this->buyerSidePayload(['display_code' => $agent->display_code])
        )->assertRedirect(route('masters.agents.index'));

        $agent->refresh();

        $this->assertNull($agent->ifsc_code, 'A buyer-side agent must not keep an IFSC — it would be paid on the wrong rail.');
        $this->assertNull($agent->gst_number);
        $this->assertSame('HBUKGB4B', $agent->swift_code);
    }

    public function test_the_pan_must_match_the_one_inside_the_gst_number(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->payload(['pan_number' => 'ZZZZZ9999Z']))
            ->assertSessionHasErrors('pan_number');
    }

    public function test_a_supplier_side_commission_is_stored_without_a_currency(): void
    {
        $user = $this->actingAsRole('Super Admin');

        /*
         * Supplier-side commissions are INR; the form hides the picker, so a
         * posted currency is either stale or crafted. Dropping it is
         * prepareForValidation's job, so this has to go through the endpoint —
         * calling the service directly would skip the rule under test.
         */
        $this->actingAs($user)->post(route('masters.agents.store'), $this->payload([
            'commissions' => [
                ['commission_type' => 'percent', 'amount' => '2', 'currency_id' => Currency::where('iso_code', 'USD')->value('id')],
            ],
        ]))->assertSessionHasNoErrors();

        $agent = Agent::where('display_code', 'AGT01')->first();

        $this->assertNull($agent->commissions->first()->currency_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Commission payer & payment terms
    |--------------------------------------------------------------------------
    */

    public function test_the_commission_payer_is_required_and_has_no_default(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->payload(['commission_paid_by' => null]))
            ->assertSessionHasErrors('commission_paid_by');
    }

    public function test_a_custom_payment_term_needs_a_description(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->payload(['payment_term' => 'custom']))
            ->assertSessionHasErrors('payment_term_custom');

        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->payload([
                'payment_term'        => 'custom',
                'payment_term_custom' => '50% on shipment, balance on buyer payment',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_term_description_is_dropped_when_the_term_is_not_custom(): void
    {
        $user = $this->actingAsRole('Super Admin');

        // Same as the currency above: prepareForValidation does the clearing,
        // so the request has to run for the rule to be exercised at all.
        $this->actingAs($user)->post(route('masters.agents.store'), $this->payload([
            'payment_term'        => 'monthly',
            'payment_term_custom' => 'left over from an earlier edit',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Agent::where('display_code', 'AGT01')->first()->payment_term_custom);
    }

    public function test_at_least_one_category_is_required(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->payload(['categories' => []]))
            ->assertSessionHasErrors('categories');
    }

    public function test_supplier_pays_does_not_require_payment_terms_or_commission_percent(): void
    {
        $user = $this->actingAsRole('Admin');

        $this->actingAs($user)->post(route('masters.agents.store'), $this->payload([
            'commission_paid_by'   => 'supplier',
            'payment_term'         => null,
            'payment_term_custom'  => null,
            'calculation_basis_id' => null,
            'commissions'          => [
                ['commission_type' => 'percent', 'amount' => '', 'currency_id' => null],
            ],
            'bank_name'            => null,
            'account_number'       => null,
            'ifsc_code'            => null,
        ]))->assertRedirect(route('masters.agents.index'));

        $agent = Agent::where('display_code', 'AGT01')->first();
        $this->assertNotNull($agent);
        $this->assertNull($agent->payment_term);
        $this->assertNull($agent->calculation_basis_id);
        $this->assertNull($agent->bank_name);
        $this->assertNull($agent->account_number);
        $this->assertNull($agent->ifsc_code);
        $this->assertCount(0, $agent->commissions);
    }

    public function test_we_pay_still_requires_commission_and_payment_terms(): void
    {
        $user = $this->actingAsRole('Admin');

        $this->actingAs($user)
            ->from(route('masters.agents.create'))
            ->post(route('masters.agents.store'), $this->payload([
                'commission_paid_by' => 'us',
                'payment_term'       => null,
                'commissions'        => [],
            ]))
            ->assertRedirect(route('masters.agents.create'))
            ->assertSessionHasErrors(['payment_term', 'commissions']);
    }

    public function test_agent_display_code_must_be_alphanumeric_and_max_5(): void
    {
        $user = $this->actingAsRole('Super Admin');

        // Regex fail (has hyphen)
        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->payload(['display_code' => 'AG-01']))
            ->assertSessionHasErrors('display_code');

        // Length fail (6 chars)
        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->payload(['display_code' => 'AGT001']))
            ->assertSessionHasErrors('display_code');
    }

    public function test_duplicate_display_code_is_rejected(): void
    {
        $user = $this->actingAsRole('Super Admin');
        $this->makeAgent();

        $this->actingAs($user)
            ->post(route('masters.agents.store'), $this->payload(['name' => 'Other Name']))
            ->assertSessionHasErrors('display_code');
    }

    public function test_agent_can_be_updated(): void
    {
        $user = $this->actingAsRole('Super Admin');
        $agent = $this->makeAgent();

        $response = $this->actingAs($user)->put(route('masters.agents.update', $agent), $this->payload([
            'name' => 'Updated Agent Name',
        ]));

        $response->assertRedirect(route('masters.agents.index'));
        $this->assertSame('Updated Agent Name', $agent->refresh()->name);
    }

    public function test_agent_can_be_soft_deleted(): void
    {
        $user = $this->actingAsRole('Super Admin');
        $agent = $this->makeAgent();

        $this->actingAs($user)->delete(route('masters.agents.destroy', $agent));

        $this->assertSoftDeleted('agents', ['id' => $agent->id]);
    }

    public function test_code_availability_reports_correctly(): void
    {
        $user = $this->actingAsRole('Super Admin');
        $agent = $this->makeAgent();

        // Active code taken
        $this->actingAs($user)
            ->getJson(route('masters.agents.check-code', ['field' => 'display_code', 'value' => 'AGT01']))
            ->assertOk()->assertJson(['available' => false]);

        // Ignored during edit
        $this->actingAs($user)
            ->getJson(route('masters.agents.check-code', ['field' => 'display_code', 'value' => 'AGT01', 'ignore' => $agent->id]))
            ->assertOk()->assertJson(['available' => true]);

        // Soft deleted code also counts as taken
        $agent->delete();
        $this->actingAs($user)
            ->getJson(route('masters.agents.check-code', ['field' => 'display_code', 'value' => 'AGT01']))
            ->assertOk()->assertJson(['available' => false]);
    }
}
