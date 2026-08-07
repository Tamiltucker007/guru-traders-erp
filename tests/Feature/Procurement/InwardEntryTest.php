<?php

namespace Tests\Feature\Procurement;

use App\Models\Buyer;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DocumentFormat;
use App\Models\InwardEntry;
use App\Models\OrderConfirmation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InwardEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;
    private PurchaseOrder $po;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permission:sync --roles');

        $this->admin = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->admin->assignRole($superAdminRole);

        $this->user = User::factory()->create();

        $this->supplier = Supplier::create([
            'display_code' => 'SUP001',
            'company_name' => 'Test Supplier Fabrics',
            'party_type'   => 'supplier',
            'status'       => 'active',
        ]);

        $buyer = Buyer::create([
            'display_code' => 'BUY001',
            'company_name' => 'Test Overseas Buyer',
            'status'       => 'active',
        ]);

        $category = new Category([
            'name'   => 'Woven Garments',
            'status' => 'active',
        ]);
        $category->code = 'CAT001';
        $category->save();

        $format = DocumentFormat::create([
            'name'   => 'Standard Order Format',
            'status' => 'active',
        ]);

        $currency = new Currency([
            'name'   => 'US Dollar',
            'symbol' => '$',
            'status' => 'active',
        ]);
        $currency->iso_code = 'USD';
        $currency->save();

        $oc = new OrderConfirmation([
            'buyer_id'           => $buyer->id,
            'category_id'        => $category->id,
            'document_format_id' => $format->id,
            'currency_id'        => $currency->id,
            'oc_date'            => now()->toDateString(),
            'status'             => 'confirmed',
        ]);
        $oc->oc_num = 'GT/OC/001/26-27';
        $oc->financial_year = '26-27';
        $oc->save();

        $this->po = new PurchaseOrder([
            'order_confirmation_id' => $oc->id,
            'supplier_id'          => $this->supplier->id,
            'po_date'               => now()->toDateString(),
            'status'                => 'raised',
        ]);
        $this->po->po_num = 'GT/PO/001/26-27';
        $this->po->financial_year = '26-27';
        $this->po->save();

        $this->po->items()->create([
            'sort_order'  => 0,
            'description' => 'Cotton T-Shirt Blue L',
            'unit'        => 'pcs',
            'cost_price'  => 150.00,
            'qty'         => 100,
            'amount'      => 15000.00,
        ]);
    }

    public function test_guest_cannot_access_inward_entries(): void
    {
        $this->get(route('procurement.inward-entries.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_with_permission_can_view_inward_entries_index(): void
    {
        $this->user->givePermissionTo('inward-entry.view');

        $this->actingAs($this->user)
            ->get(route('procurement.inward-entries.index'))
            ->assertOk()
            ->assertSee('Goods Inward Receipts');
    }

    public function test_can_create_inward_entry_and_updates_po_status_and_timeline(): void
    {
        $this->admin->givePermissionTo(['inward-entry.view', 'inward-entry.create']);

        $poItem = $this->po->items->first();

        $response = $this->actingAs($this->admin)
            ->post(route('procurement.inward-entries.store'), [
                'purchase_order_id' => $this->po->id,
                'inward_date'       => now()->toDateString(),
                'challan_no'        => 'DC-9901',
                'remarks'           => 'First batch delivery',
                'items'             => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'received_qty'          => 40,
                    ],
                ],
            ]);

        $response->assertRedirect(route('procurement.inward-entries.index'));
        $this->assertDatabaseHas('inward_entries', [
            'purchase_order_id' => $this->po->id,
            'challan_no'        => 'DC-9901',
            'status'            => 'pending',
        ]);

        $inward = InwardEntry::where('purchase_order_id', $this->po->id)->first();
        $this->assertNotNull($inward);
        $this->assertEquals(40, $inward->totalReceivedQty());

        // Verify PO status auto-updated to partial
        $this->assertEquals('partial', $this->po->fresh()->status);

        // Verify PO delivery timeline entry was created
        $this->assertDatabaseHas('purchase_order_timeline_entries', [
            'purchase_order_id' => $this->po->id,
            'qty'               => 40,
        ]);
    }

    public function test_full_inward_updates_po_status_to_received(): void
    {
        $poItem = $this->po->items->first();

        $this->actingAs($this->admin)
            ->post(route('procurement.inward-entries.store'), [
                'purchase_order_id' => $this->po->id,
                'inward_date'       => now()->toDateString(),
                'challan_no'        => 'DC-9902',
                'items'             => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'received_qty'          => 100,
                    ],
                ],
            ]);

        $this->assertEquals('received', $this->po->fresh()->status);
    }

    public function test_quality_checker_can_approve_inward_entry(): void
    {
        $poItem = $this->po->items->first();

        $inward = new InwardEntry([
            'financial_year'    => '26-27',
            'inward_date'       => now()->toDateString(),
            'purchase_order_id' => $this->po->id,
            'supplier_id'       => $this->supplier->id,
            'status'            => 'pending',
        ]);
        $inward->inward_no = 'GT/INW/001/26-27';
        $inward->save();

        $inwardItem = $inward->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'ordered_qty'            => 100,
            'received_qty'           => 50,
            'passed_qty'             => 50,
            'rejected_qty'           => 0,
        ]);

        $this->admin->givePermissionTo('inward-entry.approve');

        $response = $this->actingAs($this->admin)
            ->post(route('procurement.inward-entries.approve', $inward), [
                'status'  => 'approved',
                'remarks' => 'Fabric quality verified ok',
                'items'   => [
                    $inwardItem->id => [
                        'passed_qty'   => 48,
                        'rejected_qty' => 2,
                        'qc_remarks'   => '2 pcs stain issue',
                    ],
                ],
            ]);

        $response->assertRedirect(route('procurement.inward-entries.show', $inward));
        $this->assertEquals('approved', $inward->fresh()->status);

        $this->assertDatabaseHas('inward_entry_items', [
            'id'           => $inwardItem->id,
            'passed_qty'   => 48,
            'rejected_qty' => 2,
        ]);
    }

    public function test_po_details_json_endpoint(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('procurement.inward-entries.po-details', $this->po))
            ->assertOk()
            ->assertJsonPath('po_num', 'GT/PO/001/26-27')
            ->assertJsonPath('items.0.ordered_qty', 100)
            ->assertJsonPath('items.0.balance_qty', 100);
    }

    public function test_create_form_renders_purchase_order_options(): void
    {
        $this->admin->givePermissionTo('inward-entry.create');

        $this->actingAs($this->admin)
            ->get(route('procurement.inward-entries.create'))
            ->assertOk()
            ->assertSee('value="'.$this->po->id.'"', false)
            ->assertSee($this->po->po_num);
    }

    public function test_edit_form_renders_existing_inward_entry(): void
    {
        $poItem = $this->po->items->first();

        $inward = new InwardEntry([
            'financial_year'    => '26-27',
            'inward_date'       => now()->toDateString(),
            'purchase_order_id' => $this->po->id,
            'supplier_id'       => $this->supplier->id,
            'status'            => 'pending',
        ]);
        $inward->inward_no = 'GT/INW/002/26-27';
        $inward->save();

        $inward->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'ordered_qty'            => 100,
            'received_qty'           => 30,
            'passed_qty'             => 30,
            'rejected_qty'           => 0,
        ]);

        $this->admin->givePermissionTo('inward-entry.edit');

        $this->actingAs($this->admin)
            ->get(route('procurement.inward-entries.edit', $inward))
            ->assertOk()
            ->assertSee($inward->inward_no)
            ->assertSee('value="30"', false);
    }

    public function test_can_update_inward_entry_and_recalculates_po_status(): void
    {
        $poItem = $this->po->items->first();

        $inward = new InwardEntry([
            'financial_year'    => '26-27',
            'inward_date'       => now()->toDateString(),
            'purchase_order_id' => $this->po->id,
            'supplier_id'       => $this->supplier->id,
            'status'            => 'pending',
        ]);
        $inward->inward_no = 'GT/INW/003/26-27';
        $inward->save();

        $inward->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'ordered_qty'            => 100,
            'received_qty'           => 40,
            'passed_qty'             => 40,
            'rejected_qty'           => 0,
        ]);
        $this->po->update(['status' => 'partial']);

        $this->admin->givePermissionTo('inward-entry.edit');

        $response = $this->actingAs($this->admin)
            ->put(route('procurement.inward-entries.update', $inward), [
                'inward_date' => now()->toDateString(),
                'items'       => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'received_qty'           => 100,
                        'passed_qty'             => 100,
                        'rejected_qty'           => 0,
                    ],
                ],
            ]);

        $response->assertRedirect(route('procurement.inward-entries.index'));
        $this->assertEquals('received', $this->po->fresh()->status);
    }

    public function test_deleting_inward_entry_removes_timeline_entry_and_recalculates_po_status(): void
    {
        $poItem = $this->po->items->first();

        $this->admin->givePermissionTo(['inward-entry.create', 'inward-entry.delete']);

        $this->actingAs($this->admin)->post(route('procurement.inward-entries.store'), [
            'purchase_order_id' => $this->po->id,
            'inward_date'       => now()->toDateString(),
            'items'             => [
                ['purchase_order_item_id' => $poItem->id, 'received_qty' => 40],
            ],
        ]);

        $inward = InwardEntry::where('purchase_order_id', $this->po->id)->firstOrFail();
        $this->assertEquals('partial', $this->po->fresh()->status);

        $this->actingAs($this->admin)
            ->delete(route('procurement.inward-entries.destroy', $inward))
            ->assertRedirect(route('procurement.inward-entries.index'));

        $this->assertDatabaseMissing('purchase_order_timeline_entries', [
            'purchase_order_id' => $this->po->id,
        ]);
        $this->assertEquals('raised', $this->po->fresh()->status);
    }

    public function test_user_without_permission_cannot_create_inward_entry(): void
    {
        $poItem = $this->po->items->first();

        $this->actingAs($this->user)
            ->get(route('procurement.inward-entries.create'))
            ->assertForbidden();

        $this->actingAs($this->user)
            ->post(route('procurement.inward-entries.store'), [
                'purchase_order_id' => $this->po->id,
                'inward_date'       => now()->toDateString(),
                'items'             => [
                    ['purchase_order_item_id' => $poItem->id, 'received_qty' => 10],
                ],
            ])
            ->assertForbidden();
    }

    public function test_user_without_permission_cannot_delete_or_approve_inward_entry(): void
    {
        $poItem = $this->po->items->first();

        $inward = new InwardEntry([
            'financial_year'    => '26-27',
            'inward_date'       => now()->toDateString(),
            'purchase_order_id' => $this->po->id,
            'supplier_id'       => $this->supplier->id,
            'status'            => 'pending',
        ]);
        $inward->inward_no = 'GT/INW/004/26-27';
        $inward->save();
        $inward->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'ordered_qty'            => 100,
            'received_qty'           => 10,
        ]);

        $this->actingAs($this->user)
            ->delete(route('procurement.inward-entries.destroy', $inward))
            ->assertForbidden();

        $this->actingAs($this->user)
            ->post(route('procurement.inward-entries.approve', $inward), ['status' => 'approved'])
            ->assertForbidden();
    }

    public function test_cannot_receive_more_than_the_purchase_order_balance(): void
    {
        $this->admin->givePermissionTo('inward-entry.create');
        $poItem = $this->po->items->first();

        $response = $this->actingAs($this->admin)
            ->post(route('procurement.inward-entries.store'), [
                'purchase_order_id' => $this->po->id,
                'inward_date'       => now()->toDateString(),
                'items'             => [
                    ['purchase_order_item_id' => $poItem->id, 'received_qty' => 150],
                ],
            ]);

        $response->assertSessionHasErrors('items.0.received_qty');
        $this->assertDatabaseMissing('inward_entries', ['purchase_order_id' => $this->po->id]);
    }

    public function test_passed_and_rejected_quantity_cannot_exceed_received_quantity(): void
    {
        $poItem = $this->po->items->first();

        $inward = new InwardEntry([
            'financial_year'    => '26-27',
            'inward_date'       => now()->toDateString(),
            'purchase_order_id' => $this->po->id,
            'supplier_id'       => $this->supplier->id,
            'status'            => 'pending',
        ]);
        $inward->inward_no = 'GT/INW/005/26-27';
        $inward->save();
        $inward->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'ordered_qty'            => 100,
            'received_qty'           => 50,
        ]);

        $this->admin->givePermissionTo('inward-entry.edit');

        $response = $this->actingAs($this->admin)
            ->put(route('procurement.inward-entries.update', $inward), [
                'inward_date' => now()->toDateString(),
                'items'       => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'received_qty'           => 50,
                        'passed_qty'             => 40,
                        'rejected_qty'           => 20,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('items.0.rejected_qty');
    }
}
