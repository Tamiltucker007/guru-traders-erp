<?php

namespace Tests\Feature\Procurement;

use App\Models\Buyer;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DocumentFormat;
use App\Models\OrderConfirmation;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;
    private OrderConfirmation $oc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permission:sync --roles');

        $this->admin = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->admin->assignRole($superAdminRole);
        $this->admin->givePermissionTo('purchase-order.create');

        $this->supplier = Supplier::create([
            'display_code' => 'SUP01',
            'company_name' => 'Test Supplier Fabrics',
            'party_type'   => 'supplier',
            'status'       => 'active',
        ]);

        $buyer = Buyer::forceCreate([
            'display_code' => 'BUY01',
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

        $this->oc = new OrderConfirmation([
            'buyer_id'           => $buyer->id,
            'category_id'        => $category->id,
            'document_format_id' => $format->id,
            'currency_id'        => $currency->id,
            'oc_date'            => now()->toDateString(),
            'status'             => 'confirmed',
        ]);
        $this->oc->oc_num = 'GT/OC/001/26-27';
        $this->oc->financial_year = '26-27';
        $this->oc->save();
    }

    public function test_can_create_purchase_order_with_gt_po_number_and_financial_year(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('procurement.purchase-orders.store'), [
                'order_confirmation_id' => $this->oc->id,
                'supplier_id'           => $this->supplier->id,
                'po_date'               => now()->toDateString(),
                'delivery_details'      => 'Deliver to warehouse A',
                'packing_details'       => 'Export carton packing',
                'status'                => 'raised',
                'items'                 => [
                    [
                        'product_id'  => null,
                        'description' => 'Cotton T-Shirt',
                        'unit'        => 'pcs',
                        'cost_price'  => 150,
                        'colours'     => [
                            [
                                'colour' => 'Blue',
                                'sizes'  => [
                                    ['size' => 'L', 'qty' => 50],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertRedirect(route('procurement.purchase-orders.index'));

        $po = PurchaseOrder::query()->first();
        $this->assertNotNull($po);
        $this->assertMatchesRegularExpression('#^GT/PO/\d+/[\d-]+$#', $po->po_num);
        $this->assertNotEmpty($po->financial_year);
        $this->assertStringStartsWith('GT/PO/', $po->po_num);
    }
}
