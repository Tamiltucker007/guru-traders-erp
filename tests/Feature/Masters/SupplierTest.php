<?php

namespace Tests\Feature\Masters;

use App\Models\Agent;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Designation;
use App\Models\State;
use App\Models\Supplier;
use App\Models\SupplierType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['status' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Category::$code is outside $fillable — it is assigned by
     * NumberSeriesService — so fixtures use forceCreate.
     */
    private function category(string $name, string $code): Category
    {
        return Category::forceCreate(['code' => $code, 'name' => $name, 'status' => 'active']);
    }

    private function typeId(string $code): int
    {
        return SupplierType::where('code', $code)->value('id');
    }

    private function designationId(string $name): int
    {
        return Designation::where('name', $name)->value('id');
    }

    private function countryId(string $iso): int
    {
        return Country::where('iso_code', $iso)->value('id');
    }

    private function stateId(string $iso, string $state): int
    {
        return State::where('country_id', $this->countryId($iso))->where('name', $state)->value('id');
    }

    private function cityId(string $iso, string $state, string $city): int
    {
        return City::where('state_id', $this->stateId($iso, $state))->where('name', $city)->value('id');
    }

    /**
     * A complete, valid payload built on the client's own example row —
     * SUP001 / Sri Garments / Ramesh / 12 SIPCOT, Tiruppur, Tamil Nadu / 45 Days.
     *
     * Individual tests override the one field they are about, so a rule change
     * never silently passes because the field under test was the only one
     * present.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'display_code'             => 'SUP01',
            'party_type'               => 'supplier',
            'company_name'             => 'Sri Garments',
            'name_on_bill'             => 'Sri Garments & Co',
            'supplier_type_id'         => $this->typeId(SupplierType::REGISTERED_REGULAR),
            'gst_number'               => '33ABCDE1234F1Z5',
            'pan_number'               => 'ABCDE1234F',
            'is_msme'                  => false,
            'msme_registration_no'     => null,
            'contact_name'             => 'Ramesh',
            'contact_designation_id'   => $this->designationId('Merchandiser'),
            'contact_email'            => 'ramesh@srigarments.com',
            'contact_mobile'           => '9876543210',
            'contacts'                 => [],
            'address'                  => '12 SIPCOT',
            'country_id'               => $this->countryId('IN'),
            'state_id'                 => $this->stateId('IN', 'Tamil Nadu'),
            'city_id'                  => $this->cityId('IN', 'Tamil Nadu', 'Tirupur'),
            'pincode'                  => '641601',
            'category_ids'             => [],
            'discount_percent'         => '5.00',
            'credit_days'              => 45,
            'bank_name'                => 'HDFC Bank',
            'account_number'           => '50100123456789',
            'ifsc_code'                => 'HDFC0001234',
            'agent_id'                 => null,
            'agent_commission_type'    => null,
            'agent_commission_value'   => null,
            'we_supply_material'       => false,
            'requires_sample_approval' => false,
            'default_delivery_mode'    => 'to_office',
            'status'                   => 'active',
            'remarks'                  => null,
        ], $overrides);
    }

    /**
     * Supplier::create() takes columns only — the contact and category keys are
     * sub-forms the service unpacks. Fixtures that do not go through the
     * controller strip them here.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function make(array $overrides = []): Supplier
    {
        return Supplier::create(array_diff_key($this->payload($overrides), array_flip([
            'category_ids', 'contacts', 'contact_name', 'contact_designation_id',
            'contact_email', 'contact_mobile',
        ])));
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD — every column on the sheet
    |--------------------------------------------------------------------------
    */

    public function test_a_supplier_can_be_created_with_every_sheet_field(): void
    {
        $shirts  = $this->category("Men's Shirts", 'CAT901');
        $bottoms = $this->category("Men's Bottoms", 'CAT902');
        $agent   = Agent::ofType('supplier')->first();
        $user    = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.suppliers.store'), $this->payload([
            'category_ids'           => [$shirts->id, $bottoms->id],
            'agent_id'               => $agent->id,
            'agent_commission_type'  => 'percent',
            'agent_commission_value' => '2.5',
            'remarks'                => 'Reliable on delivery',
        ]))->assertRedirect(route('masters.suppliers.index'));

        $supplier = Supplier::first();

        $this->assertSame('SUP01', $supplier->display_code);
        $this->assertSame('supplier', $supplier->party_type);
        $this->assertSame('Sri Garments', $supplier->company_name);
        $this->assertSame('Sri Garments & Co', $supplier->name_on_bill);
        $this->assertSame('33ABCDE1234F1Z5', $supplier->gst_number);
        $this->assertSame('ABCDE1234F', $supplier->pan_number);
        $this->assertSame('Tirupur', $supplier->city->name);
        $this->assertSame('Tamil Nadu', $supplier->state->name);
        $this->assertSame('India', $supplier->country->name);
        $this->assertSame('641601', $supplier->pincode);
        $this->assertSame('5.00', $supplier->discount_percent);
        $this->assertSame(45, $supplier->credit_days);
        $this->assertSame('HDFC0001234', $supplier->ifsc_code);
        $this->assertSame($agent->id, $supplier->agent_id);
        $this->assertSame('percent', $supplier->agent_commission_type);
        $this->assertSame('active', $supplier->status);

        // Col R — the multi-select
        $this->assertEqualsCanonicalizing(
            [$shirts->id, $bottoms->id],
            $supplier->categories->pluck('id')->all()
        );

        // Cols H–K land as the primary contact row.
        $this->assertSame('Ramesh', $supplier->primaryContact->name);
        $this->assertSame('Merchandiser', $supplier->primaryContact->designation->name);
        $this->assertSame('9876543210', $supplier->primaryContact->mobile);

        // HasAuditColumns fills these without the service being asked to.
        $this->assertSame($user->id, $supplier->created_by);
        $this->assertSame($user->id, $supplier->updated_by);
    }

    public function test_a_supplier_can_be_updated(): void
    {
        $a    = $this->category('A', 'CAT901');
        $b    = $this->category('B', 'CAT902');
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.suppliers.store'), $this->payload([
            'category_ids' => [$a->id, $b->id],
        ]));

        $supplier = Supplier::first();

        $this->actingAs($user)->put(route('masters.suppliers.update', $supplier), $this->payload([
            'company_name' => 'Sri Garments Pvt Ltd',
            'category_ids' => [$b->id],
            'credit_days'  => 30,
            'city_id'      => $this->cityId('IN', 'Tamil Nadu', 'Coimbatore'),
        ]))->assertRedirect();

        $supplier->refresh();

        $this->assertSame('Sri Garments Pvt Ltd', $supplier->company_name);
        $this->assertSame(30, $supplier->credit_days);
        $this->assertSame('Coimbatore', $supplier->city->name);
        $this->assertSame([$b->id], $supplier->categories->pluck('id')->all());
    }

    public function test_status_toggles(): void
    {
        $user     = $this->actingAsRole('Super Admin');
        $supplier = $this->make();

        $this->actingAs($user)->patch(route('masters.suppliers.toggle-status', $supplier));
        $this->assertSame('inactive', $supplier->refresh()->status);

        $this->actingAs($user)->patch(route('masters.suppliers.toggle-status', $supplier));
        $this->assertSame('active', $supplier->refresh()->status);
    }

    public function test_a_supplier_is_soft_deleted(): void
    {
        $supplier = $this->make();

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->delete(route('masters.suppliers.destroy', $supplier))
            ->assertRedirect();

        $this->assertSoftDeleted($supplier);
    }

    /*
    |--------------------------------------------------------------------------
    | Col A — "it should tell me if a certain code is used already"
    |--------------------------------------------------------------------------
    */

    public function test_duplicate_display_codes_are_rejected(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.suppliers.store'), $this->payload());

        $this->actingAs($user)
            ->post(route('masters.suppliers.store'), $this->payload(['company_name' => 'Another Mills']))
            ->assertSessionHasErrors('display_code');

        $this->assertSame(1, Supplier::count());
    }

    public function test_a_display_code_is_upper_cased_so_case_cannot_duplicate_it(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.suppliers.store'), $this->payload(['display_code' => 'sup01']));
        $this->assertSame('SUP01', Supplier::first()->display_code);

        $this->actingAs($user)
            ->post(route('masters.suppliers.store'), $this->payload(['display_code' => 'SuP01']))
            ->assertSessionHasErrors('display_code');
    }

    public function test_a_display_code_longer_than_five_characters_is_rejected(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload(['display_code' => 'TOOLONG']))
            ->assertSessionHasErrors('display_code');

        $this->assertSame(0, Supplier::count());
    }

    public function test_the_check_code_endpoint_reports_availability(): void
    {
        $user = $this->actingAsRole('Super Admin');
        $this->actingAs($user)->post(route('masters.suppliers.store'), $this->payload());
        $supplier = Supplier::first();

        $this->actingAs($user)->getJson(route('masters.suppliers.check-code', ['value' => 'SUP01']))
            ->assertJson(['available' => false]);

        $this->actingAs($user)->getJson(route('masters.suppliers.check-code', ['value' => 'ZZZZZ']))
            ->assertJson(['available' => true]);

        // Case-folded, matching what the request upper-cases before saving.
        $this->actingAs($user)->getJson(route('masters.suppliers.check-code', ['value' => 'sup01']))
            ->assertJson(['available' => false]);

        // Its own code is not a clash with itself on the edit form.
        $this->actingAs($user)->getJson(route('masters.suppliers.check-code', ['value' => 'SUP01', 'ignore' => $supplier->id]))
            ->assertJson(['available' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | Cols D, E — "GST number, only if the registered option is chosen"
    |--------------------------------------------------------------------------
    */

    public function test_a_registered_supplier_type_requires_a_gst_number(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'supplier_type_id' => $this->typeId(SupplierType::REGISTERED_COMPOSITION),
                'gst_number'       => null,
            ]))
            ->assertSessionHasErrors('gst_number');

        $this->assertSame(0, Supplier::count());
    }

    public function test_an_unregistered_supplier_type_drops_the_gst_number_rather_than_erroring(): void
    {
        // Consistent with the buyer's orphaned city: a field the form had
        // already hidden is discarded, not turned into an error message.
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'supplier_type_id' => $this->typeId(SupplierType::UNREGISTERED),
                'gst_number'       => '33ABCDE1234F1Z5',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Supplier::first()->gst_number);
    }

    public function test_no_supplier_type_does_not_demand_a_gst_number(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'supplier_type_id' => null,
                'gst_number'       => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Supplier::count());
    }

    public function test_a_malformed_gst_number_is_rejected(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload(['gst_number' => '33ABCDE1234F1X5']))
            ->assertSessionHasErrors('gst_number');
    }

    public function test_a_pan_that_disagrees_with_the_gst_number_is_rejected(): void
    {
        // The GSTIN carries the holder's PAN at characters 3-12. Two fields
        // that must agree is a typo waiting to happen, and it stays invisible
        // until a return is filed.
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'gst_number' => '33ABCDE1234F1Z5',
                'pan_number' => 'ZZZZZ9999Z',
            ]))
            ->assertSessionHasErrors('pan_number');
    }

    public function test_a_malformed_pan_or_ifsc_is_rejected(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)
            ->post(route('masters.suppliers.store'), $this->payload([
                'gst_number' => null,
                'supplier_type_id' => $this->typeId(SupplierType::UNREGISTERED),
                'pan_number' => 'ABCD1234FG',
            ]))
            ->assertSessionHasErrors('pan_number');

        $this->actingAs($user)
            ->post(route('masters.suppliers.store'), $this->payload(['ifsc_code' => 'HDFC1001234']))
            ->assertSessionHasErrors('ifsc_code');
    }

    public function test_the_conditional_blocks_are_hidden_by_the_server_not_by_the_script(): void
    {
        // Rendering them open and closing them on load makes every edit of a
        // trading supplier flash a GST field that does not apply. These assert
        // the starting state, which is the part no JavaScript is involved in.
        $user = $this->actingAsRole('Super Admin');

        $registered = $this->make();
        $plain      = $this->make([
            'display_code'     => 'SUP02',
            'supplier_type_id' => $this->typeId(SupplierType::UNREGISTERED),
            'gst_number'       => null,
        ]);

        $this->assertFalse(
            $this->isHidden($this->actingAs($user)->get(route('masters.suppliers.edit', $registered))->getContent(), 'gst-row'),
            'The GST field should be open for a registered supplier type.'
        );

        $this->assertTrue(
            $this->isHidden($this->actingAs($user)->get(route('masters.suppliers.edit', $plain))->getContent(), 'gst-row'),
            'The GST field should be closed for an unregistered supplier type.'
        );

        // A trading supplier is not doing jobwork, so that section starts closed.
        $this->assertTrue(
            $this->isHidden($this->actingAs($user)->get(route('masters.suppliers.create'))->getContent(), 'jobwork-section')
        );

        $jobber = $this->make(['display_code' => 'JOB01', 'party_type' => 'jobber']);

        $this->assertFalse(
            $this->isHidden($this->actingAs($user)->get(route('masters.suppliers.edit', $jobber))->getContent(), 'jobwork-section')
        );
    }

    /**
     * Whether the element carrying $id was rendered with Bootstrap's d-none.
     *
     * Matched on the whole opening tag rather than a fixed attribute order:
     * the plain div writes class before id, and the form-section component
     * merges its class in afterwards, so the two are not written the same way.
     */
    private function isHidden(string $html, string $id): bool
    {
        $found = preg_match('/<\w+[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>/', $html, $matches);

        $this->assertSame(1, $found, "No element with id \"{$id}\" was rendered.");

        return str_contains($matches[0], 'd-none');
    }

    /*
    |--------------------------------------------------------------------------
    | Col G — MSME registration
    |--------------------------------------------------------------------------
    */

    public function test_ticking_msme_requires_the_registration_number(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'is_msme'              => true,
                'msme_registration_no' => null,
            ]))
            ->assertSessionHasErrors('msme_registration_no');
    }

    public function test_unticking_msme_clears_a_registration_number_left_behind(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'is_msme'              => false,
                'msme_registration_no' => 'UDYAM-TN-33-0001234',
            ]))
            ->assertSessionHasNoErrors();

        $supplier = Supplier::first();

        $this->assertFalse($supplier->is_msme);
        $this->assertNull($supplier->msme_registration_no);
    }

    /*
    |--------------------------------------------------------------------------
    | Cols H–K and L — contacts
    |--------------------------------------------------------------------------
    */

    public function test_the_primary_and_extra_contacts_are_saved_as_one_list(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'contacts' => [
                    ['name' => 'Kumar', 'designation_id' => $this->designationId('Accountant'), 'mobile' => '9000000001', 'email' => 'kumar@srigarments.com'],
                    ['name' => 'Latha', 'designation_id' => null, 'mobile' => '9000000002', 'email' => null],
                    // Wholly blank — a row the user left alone, dropped.
                    ['name' => '', 'designation_id' => null, 'mobile' => '', 'email' => ''],
                ],
            ]))->assertRedirect();

        $contacts = Supplier::first()->contacts;

        $this->assertCount(3, $contacts);
        $this->assertSame(['Ramesh', 'Kumar', 'Latha'], $contacts->pluck('name')->all());

        // Primary first, and exactly one of them.
        $this->assertTrue($contacts->first()->is_primary);
        $this->assertSame(1, $contacts->where('is_primary', true)->count());
    }

    public function test_an_extra_contact_with_a_number_but_no_name_is_rejected(): void
    {
        // The one combination worth reporting: it saves a phone number nobody
        // can attribute.
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'contacts' => [['name' => '', 'designation_id' => null, 'mobile' => '9000000001', 'email' => null]],
            ]))
            ->assertSessionHasErrors('contacts.0.name');
    }

    public function test_the_first_extra_contact_becomes_primary_when_the_main_form_is_blank(): void
    {
        // A supplier with contacts but no primary would make every downstream
        // "email the supplier" a special case.
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'contact_name'           => null,
                'contact_designation_id' => null,
                'contact_email'          => null,
                'contact_mobile'         => null,
                'contacts'               => [
                    ['name' => 'Kumar', 'designation_id' => null, 'mobile' => '9000000001', 'email' => null],
                    ['name' => 'Latha', 'designation_id' => null, 'mobile' => '9000000002', 'email' => null],
                ],
            ]))->assertRedirect();

        $contacts = Supplier::first()->contacts;

        $this->assertSame('Kumar', $contacts->first()->name);
        $this->assertTrue($contacts->first()->is_primary);
        $this->assertFalse($contacts->last()->is_primary);
    }

    public function test_contacts_are_rewritten_on_update_not_appended(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.suppliers.store'), $this->payload([
            'contacts' => [
                ['name' => 'Kumar', 'designation_id' => null, 'mobile' => '9000000001', 'email' => null],
                ['name' => 'Latha', 'designation_id' => null, 'mobile' => '9000000002', 'email' => null],
            ],
        ]));

        $supplier = Supplier::first();

        $this->actingAs($user)->put(route('masters.suppliers.update', $supplier), $this->payload([
            'contact_name' => 'Ramesh Kumar',
            'contacts'     => [
                ['name' => 'Latha', 'designation_id' => null, 'mobile' => '9000000099', 'email' => null],
            ],
        ]));

        $contacts = $supplier->refresh()->contacts;

        $this->assertCount(2, $contacts);
        $this->assertSame(['Ramesh Kumar', 'Latha'], $contacts->pluck('name')->all());
        $this->assertSame('9000000099', $contacts->last()->mobile);
    }

    public function test_contacts_are_removed_with_the_supplier(): void
    {
        $supplier = $this->make();
        $supplier->contacts()->create(['name' => 'Ramesh', 'is_primary' => true]);

        $supplier->forceDelete();

        $this->assertDatabaseCount('supplier_contacts', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Cols P, O, N — cascading Country -> State -> City
    |--------------------------------------------------------------------------
    */

    public function test_a_state_from_a_different_country_is_rejected(): void
    {
        // The browser narrows the list; this is what actually enforces it.
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'country_id' => $this->countryId('GB'),
                'state_id'   => $this->stateId('IN', 'Tamil Nadu'),
                'city_id'    => null,
            ]))
            ->assertSessionHasErrors('state_id');

        $this->assertSame(0, Supplier::count());
    }

    public function test_a_city_from_a_different_state_is_rejected(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'state_id' => $this->stateId('IN', 'Tamil Nadu'),
                'city_id'  => $this->cityId('IN', 'Maharashtra', 'Mumbai'),
            ]))
            ->assertSessionHasErrors('city_id');
    }

    public function test_clearing_the_country_clears_the_state_and_city_rather_than_erroring(): void
    {
        $user     = $this->actingAsRole('Super Admin');
        $supplier = $this->make();

        $this->assertNotNull($supplier->city_id);

        $this->actingAs($user)->put(route('masters.suppliers.update', $supplier), $this->payload([
            'country_id' => null,
        ]))->assertSessionHasNoErrors();

        $supplier->refresh();

        $this->assertNull($supplier->country_id);
        $this->assertNull($supplier->state_id);
        $this->assertNull($supplier->city_id);
    }

    public function test_the_edit_form_renders_the_saved_state_and_city_without_javascript(): void
    {
        // The options are server-rendered, so the saved value is visible before
        // the cascade runs — and readable to a user without JavaScript at all.
        $supplier = $this->make();

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.suppliers.edit', $supplier))
            ->assertOk()
            ->assertSee('Tamil Nadu')
            ->assertSee('Tirupur')
            // A different country's states must not be in the initial markup.
            ->assertDontSee('Northern Ireland');
    }

    public function test_the_form_carries_the_cascade_wiring(): void
    {
        // The cascade is declared in markup and read by app.js. If these
        // attributes stop being emitted the dropdowns silently go static, which
        // no other assertion here would catch.
        $html = $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.suppliers.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-cascade-parent="#country_id"', $html);
        $this->assertStringContainsString('data-cascade-parent="#state_id"', $html);
        $this->assertStringContainsString('data-cascade-parent="#party_type"', $html);
        $this->assertStringContainsString('data-cascade-child="#agent_id"', $html);
        $this->assertStringContainsString(route('masters.geo.states'), $html);
        $this->assertStringContainsString(route('masters.geo.cities'), $html);
        $this->assertStringContainsString(route('masters.suppliers.agents'), $html);
    }

    /*
    |--------------------------------------------------------------------------
    | Col X — "of supplier side selected in agent master"
    |--------------------------------------------------------------------------
    */

    public function test_the_agent_dropdown_lists_only_supplier_side_agents(): void
    {
        Agent::create(['display_code' => 'AGS2', 'name' => 'Supplier Side Agent', 'agent_type' => 'supplier']);
        Agent::create(['display_code' => 'AGB2', 'name' => 'Buyer Side Agent', 'agent_type' => 'buyer']);

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.suppliers.create'))
            ->assertOk()
            ->assertSee('Supplier Side Agent')
            ->assertDontSee('Buyer Side Agent');
    }

    public function test_a_buyer_side_agent_is_rejected_even_if_posted_directly(): void
    {
        // The filtered select is a UI convenience; the rule is what enforces it.
        $buyerSide = Agent::create(['display_code' => 'AGB2', 'name' => 'Buyer Side Agent', 'agent_type' => 'buyer']);

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload(['agent_id' => $buyerSide->id]))
            ->assertSessionHasErrors('agent_id');

        $this->assertSame(0, Supplier::count());
    }

    public function test_a_jobber_takes_a_jobber_side_agent_not_a_supplier_side_one(): void
    {
        $supplierSide = Agent::ofType('supplier')->first();
        $jobberSide   = Agent::ofType('jobber')->first();
        $user         = $this->actingAsRole('Super Admin');

        $this->actingAs($user)
            ->post(route('masters.suppliers.store'), $this->payload([
                'party_type' => 'jobber',
                'agent_id'   => $supplierSide->id,
            ]))
            ->assertSessionHasErrors('agent_id');

        $this->actingAs($user)
            ->post(route('masters.suppliers.store'), $this->payload([
                'party_type' => 'jobber',
                'agent_id'   => $jobberSide->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($jobberSide->id, Supplier::first()->agent_id);
    }

    public function test_a_party_marked_both_may_use_either_side(): void
    {
        $user = $this->actingAsRole('Super Admin');

        foreach (['supplier', 'jobber'] as $index => $side) {
            $this->actingAs($user)
                ->post(route('masters.suppliers.store'), $this->payload([
                    'display_code' => 'SUP0'.($index + 2),
                    'party_type'   => 'both',
                    'agent_id'     => Agent::ofType($side)->first()->id,
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Supplier::count());
    }

    public function test_the_agents_endpoint_follows_the_party_type(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $supplierSide = collect(
            $this->actingAs($user)->getJson(route('masters.suppliers.agents', ['party_type' => 'supplier']))
                ->assertOk()->json()
        )->pluck('name')->implode(' ');

        $jobberSide = collect(
            $this->actingAs($user)->getJson(route('masters.suppliers.agents', ['party_type' => 'jobber']))
                ->assertOk()->json()
        )->pluck('name')->implode(' ');

        // AgentSeeder: AG03 Suresh is supplier side, AG04 Ravi is jobber side.
        $this->assertStringContainsString('Suresh', $supplierSide);
        $this->assertStringNotContainsString('Ravi', $supplierSide);
        $this->assertStringContainsString('Ravi', $jobberSide);
        $this->assertStringNotContainsString('Suresh', $jobberSide);

        // "Both" reaches either side; an unknown value must not reach every agent.
        $both = collect(
            $this->actingAs($user)->getJson(route('masters.suppliers.agents', ['party_type' => 'both']))->json()
        )->pluck('name')->implode(' ');

        $this->assertStringContainsString('Suresh', $both);
        $this->assertStringContainsString('Ravi', $both);
        $this->assertStringNotContainsString('David', $both);   // buyer side
    }

    public function test_the_agents_endpoint_includes_commission_from_agent_master(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $rows = $this->actingAs($user)
            ->getJson(route('masters.suppliers.agents', ['party_type' => 'supplier']))
            ->assertOk()
            ->json();

        $suresh = collect($rows)->first(fn ($row) => str_contains($row['name'], 'Suresh'));

        $this->assertNotNull($suresh);
        $this->assertArrayHasKey('commissions', $suresh);
        $this->assertNotEmpty($suresh['commissions']);
        $this->assertSame('percent', $suresh['commissions'][0]['type']);
        $this->assertEqualsWithDelta(2.0, $suresh['commissions'][0]['value'], 0.0001);
        $this->assertSame('2%', $suresh['commissions'][0]['label']);
    }

    public function test_supplier_create_form_offers_commission_rate_dropdown(): void
    {
        $html = $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.suppliers.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="agent_commission_pick"', $html);
        $this->assertStringContainsString('name="agent_commission_value"', $html);
        $this->assertStringContainsString('Rates come from Agent Master', $html);
    }

    public function test_an_inactive_agent_is_not_offered(): void
    {
        Agent::create(['display_code' => 'AGX', 'name' => 'Retired Agent', 'status' => 'inactive', 'agent_type' => 'supplier']);

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.suppliers.create'))
            ->assertOk()
            ->assertDontSee('Retired Agent');
    }

    /*
    |--------------------------------------------------------------------------
    | Col Y — agent commission
    |--------------------------------------------------------------------------
    */

    public function test_a_commission_value_without_a_type_is_rejected(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'agent_commission_value' => '2.5',
                'agent_commission_type'  => null,
            ]))
            ->assertSessionHasErrors('agent_commission_type');
    }

    public function test_a_commission_type_without_a_value_is_blanked_rather_than_rejected(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'agent_commission_value' => null,
                'agent_commission_type'  => 'percent',
            ]))
            ->assertSessionHasNoErrors();

        // Leaving a stray type behind would make the label render a bare "%".
        $this->assertNull(Supplier::first()->agent_commission_type);
    }

    public function test_the_commission_label_renders_from_type_and_value(): void
    {
        $percent = $this->make([
            'agent_commission_type'  => 'percent',
            'agent_commission_value' => 2.5,
        ]);

        $this->assertSame('2.5%', $percent->agent_commission_label);

        $amount = $this->make([
            'display_code'           => 'SUP02',
            'agent_commission_type'  => 'amount',
            'agent_commission_value' => 1.5,
        ]);

        $this->assertSame('1.5 INR', $amount->agent_commission_label);
    }

    /*
    |--------------------------------------------------------------------------
    | Cols S, T — discount and credit terms
    |--------------------------------------------------------------------------
    */

    public function test_the_sheets_character_limits_are_enforced(): void
    {
        $user = $this->actingAsRole('Super Admin');

        // Col S — "maximum 2 characters allowed"
        $this->actingAs($user)
            ->post(route('masters.suppliers.store'), $this->payload(['discount_percent' => 120]))
            ->assertSessionHasErrors('discount_percent');

        // Col T — "maximum 3 characters allowed"
        $this->actingAs($user)
            ->post(route('masters.suppliers.store'), $this->payload(['credit_days' => 1200]))
            ->assertSessionHasErrors('credit_days');
    }

    public function test_zero_credit_days_reads_as_on_dispatch(): void
    {
        // Schema §5: jobbers "all take payment on dispatch". 0 is a real answer,
        // not a missing one, which is why it is stored as a number.
        $this->assertSame('On dispatch', $this->make(['credit_days' => 0])->credit_terms_label);
        $this->assertSame('45 Days', $this->make(['display_code' => 'SUP02'])->credit_terms_label);
        $this->assertNull($this->make(['display_code' => 'SUP03', 'credit_days' => null])->credit_terms_label);
    }

    /*
    |--------------------------------------------------------------------------
    | Party type — DATABASE_SCHEMA.md §5
    |--------------------------------------------------------------------------
    */

    public function test_the_jobwork_flags_are_cleared_for_a_trading_supplier(): void
    {
        // A trading supplier arranges its own material. Leaving the flag set
        // would switch on a Material Issue that has no business existing.
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'party_type'               => 'supplier',
                'we_supply_material'       => true,
                'requires_sample_approval' => true,
            ]))
            ->assertSessionHasNoErrors();

        $supplier = Supplier::first();

        $this->assertFalse($supplier->we_supply_material);
        $this->assertFalse($supplier->requires_sample_approval);
        $this->assertFalse($supplier->does_jobwork);
    }

    public function test_the_jobwork_flags_are_kept_for_a_jobber(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload([
                'party_type'               => 'jobber',
                'agent_id'                 => null,
                'we_supply_material'       => true,
                'requires_sample_approval' => true,
                'default_delivery_mode'    => 'direct_to_port',
            ]))
            ->assertSessionHasNoErrors();

        $supplier = Supplier::first();

        $this->assertTrue($supplier->we_supply_material);
        $this->assertTrue($supplier->requires_sample_approval);
        $this->assertTrue($supplier->does_jobwork);
        $this->assertSame('direct_to_port', $supplier->default_delivery_mode);
    }

    public function test_the_list_can_be_filtered_by_party_type_and_both_appears_under_each(): void
    {
        $user = $this->actingAsRole('Super Admin');

        $this->make(['display_code' => 'SUP02', 'company_name' => 'Trading Only', 'party_type' => 'supplier']);
        $this->make(['display_code' => 'JOB01', 'company_name' => 'Jobwork Only', 'party_type' => 'jobber']);
        $this->make(['display_code' => 'MIX01', 'company_name' => 'Does Everything', 'party_type' => 'both']);

        $this->actingAs($user)->get(route('masters.suppliers.index', ['party_type' => 'supplier']))
            ->assertSee('Trading Only')
            ->assertSee('Does Everything')      // "both" belongs to this screen too
            ->assertDontSee('Jobwork Only');

        $this->actingAs($user)->get(route('masters.suppliers.index', ['party_type' => 'jobber']))
            ->assertSee('Jobwork Only')
            ->assertSee('Does Everything')
            ->assertDontSee('Trading Only');
    }

    /*
    |--------------------------------------------------------------------------
    | Col R — category link
    |--------------------------------------------------------------------------
    */

    public function test_a_category_in_use_by_a_supplier_cannot_be_deleted(): void
    {
        $category = $this->category('In use by a supplier', 'CAT901');
        $user     = $this->actingAsRole('Super Admin');

        $this->actingAs($user)->post(route('masters.suppliers.store'), $this->payload([
            'category_ids' => [$category->id],
        ]));

        $this->actingAs($user)
            ->delete(route('masters.categories.destroy', $category))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($category);
    }

    public function test_an_unknown_category_id_is_rejected(): void
    {
        $this->actingAs($this->actingAsRole('Super Admin'))
            ->post(route('masters.suppliers.store'), $this->payload(['category_ids' => [99999]]))
            ->assertSessionHasErrors('category_ids.0');
    }

    /*
    |--------------------------------------------------------------------------
    | Screens and filters
    |--------------------------------------------------------------------------
    */

    public function test_every_screen_renders(): void
    {
        $user     = $this->actingAsRole('Super Admin');
        $supplier = $this->make();
        $supplier->contacts()->create(['name' => 'Ramesh', 'mobile' => '9876543210', 'is_primary' => true]);

        $this->actingAs($user)->get(route('masters.suppliers.index'))->assertOk()->assertSee('Sri Garments');
        $this->actingAs($user)->get(route('masters.suppliers.create'))->assertOk();
        $this->actingAs($user)->get(route('masters.suppliers.show', $supplier))->assertOk()->assertSee('Ramesh');
        $this->actingAs($user)->get(route('masters.suppliers.edit', $supplier))->assertOk()->assertSee('SUP01');
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $shirts = $this->category('Shirts', 'CAT901');
        $user   = $this->actingAsRole('Super Admin');

        $one = $this->make(['company_name' => 'Cotton Mills Ltd']);
        $one->categories()->attach($shirts);
        $one->contacts()->create(['name' => 'Ramesh', 'is_primary' => true]);

        $this->make([
            'display_code' => 'SUP02',
            'company_name' => 'Denim Works Inc',
            'status'       => 'inactive',
            'city_id'      => $this->cityId('IN', 'Maharashtra', 'Mumbai'),
            'state_id'     => $this->stateId('IN', 'Maharashtra'),
        ]);

        $this->actingAs($user)->get(route('masters.suppliers.index', ['search' => 'Cotton']))
            ->assertSee('Cotton Mills Ltd')->assertDontSee('Denim Works Inc');

        $this->actingAs($user)->get(route('masters.suppliers.index', ['status' => 'inactive']))
            ->assertSee('Denim Works Inc')->assertDontSee('Cotton Mills Ltd');

        $this->actingAs($user)->get(route('masters.suppliers.index', ['category_id' => $shirts->id]))
            ->assertSee('Cotton Mills Ltd')->assertDontSee('Denim Works Inc');

        // City and the contact person live on other tables; the same search box
        // must still find them.
        $this->actingAs($user)->get(route('masters.suppliers.index', ['search' => 'Mumbai']))
            ->assertSee('Denim Works Inc')->assertDontSee('Cotton Mills Ltd');

        $this->actingAs($user)->get(route('masters.suppliers.index', ['search' => 'Ramesh']))
            ->assertSee('Cotton Mills Ltd')->assertDontSee('Denim Works Inc');
    }

    public function test_sorting_ignores_a_column_that_is_not_whitelisted(): void
    {
        $this->make();

        $this->actingAs($this->actingAsRole('Super Admin'))
            ->get(route('masters.suppliers.index', ['sort' => 'created_by); drop table suppliers; --']))
            ->assertOk();

        $this->assertSame(1, Supplier::count());
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    public function test_a_role_without_supplier_view_is_denied(): void
    {
        // Packing has product.view and buyer.view but no supplier.*.
        $this->actingAs($this->actingAsRole('Packing'))
            ->get(route('masters.suppliers.index'))
            ->assertForbidden();
    }

    public function test_a_view_only_role_cannot_create_or_delete(): void
    {
        // Quality Checker has supplier.view only.
        $user     = $this->actingAsRole('Quality Checker');
        $supplier = $this->make();

        $this->actingAs($user)->get(route('masters.suppliers.index'))->assertOk();
        $this->actingAs($user)->get(route('masters.suppliers.create'))->assertForbidden();
        $this->actingAs($user)->post(route('masters.suppliers.store'), $this->payload())->assertForbidden();
        $this->actingAs($user)->get(route('masters.suppliers.edit', $supplier))->assertForbidden();
        $this->actingAs($user)->delete(route('masters.suppliers.destroy', $supplier))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('masters.suppliers.index'))->assertRedirect(route('login'));
    }
}
