<?php

namespace Tests\Feature;

use App\Models\BillingInvoice;
use App\Models\Buyer;
use App\Models\FinanceTransaction;
use App\Models\GstFiling;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionsSeeder::class, RolesSeeder::class]);
        $this->admin = User::factory()->create(['status' => true])->assignRole('Super Admin');
    }

    public function test_admin_can_access_billing_index_and_create_invoice(): void
    {
        $buyer = Buyer::forceCreate([
            'company_name' => 'Acme Global Buyer',
            'display_code' => 'ACME1',
            'status'       => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post(route('finance.billing.store'), [
            'buyer_id'       => $buyer->id,
            'invoice_date'   => '2026-09-01',
            'due_date'       => '2026-09-30',
            'tds_percentage' => 10,
            'items'          => [
                [
                    'description' => 'Woven Shirts Lot 100',
                    'qty'         => 10,
                    'rate'        => 1000,
                ],
            ],
        ]);

        $response->assertRedirect(route('finance.billing.index'));
        $this->assertDatabaseHas('billing_invoices', [
            'buyer_id'       => $buyer->id,
            'subtotal'       => 10000.00,
            'tds_amount'     => 1000.00,
            'total_amount'   => 10000.00,
            'balance_amount' => 9000.00,
        ]);
    }

    public function test_recording_billing_payment_creates_finance_transaction(): void
    {
        $buyer = Buyer::forceCreate([
            'company_name' => 'Test Buyer Ltd',
            'display_code' => 'TBL1',
            'status'       => 'active',
        ]);

        $invoice = BillingInvoice::create([
            'invoice_num'    => 'INV-2026-0001',
            'buyer_id'       => $buyer->id,
            'invoice_date'   => '2026-09-01',
            'due_date'       => '2026-09-30',
            'subtotal'       => 5000,
            'total_amount'   => 5000,
            'balance_amount' => 5000,
            'status'         => 'unpaid',
        ]);

        $response = $this->actingAs($this->admin)->post(route('finance.billing.pay', $invoice->id), [
            'payment_amount' => 5000,
            'payment_mode'   => 'Bank Transfer',
        ]);

        $response->assertRedirect(route('finance.billing.index'));
        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertDatabaseHas('finance_transactions', [
            'type'               => 'income',
            'category'           => 'billing',
            'billing_invoice_id' => $invoice->id,
            'amount'             => 5000.00,
        ]);
    }

    public function test_voucher_submission_and_approval_workflow(): void
    {
        $user = User::factory()->create(['status' => true])->givePermissionTo('voucher.create');

        $response = $this->actingAs($user)->post(route('finance.vouchers.store'), [
            'title'        => 'Travel Expense Reimbursement',
            'category'     => 'staff',
            'amount'       => 1500,
            'voucher_date' => '2026-09-02',
        ]);

        $response->assertRedirect(route('finance.vouchers.index'));
        $voucher = Voucher::where('title', 'Travel Expense Reimbursement')->first();

        $this->assertNotNull($voucher);
        $this->assertSame('pending', $voucher->status);

        // Approve voucher as Super Admin
        $approveResp = $this->actingAs($this->admin)->post(route('finance.vouchers.approve', $voucher->id));
        $approveResp->assertRedirect(route('finance.vouchers.index'));

        $voucher->refresh();
        $this->assertSame('approved', $voucher->status);

        $this->assertDatabaseHas('finance_transactions', [
            'type'       => 'expense',
            'voucher_id' => $voucher->id,
            'amount'     => 1500.00,
        ]);
    }

    public function test_budget_target_setting(): void
    {
        $response = $this->actingAs($this->admin)->post(route('finance.budget.store'), [
            'period_type'          => 'month',
            'period_value'         => '2026-09',
            'category_or_vertical' => 'material',
            'target_amount'        => 50000,
        ]);

        $response->assertRedirect(route('finance.budget.index', ['period_type' => 'month', 'period_value' => '2026-09']));
        $this->assertDatabaseHas('budget_targets', [
            'period_type'          => 'month',
            'period_value'         => '2026-09',
            'category_or_vertical' => 'material',
            'target_amount'        => 50000.00,
        ]);
    }

    public function test_payroll_processing_and_lop_math(): void
    {
        $employee = User::factory()->create([
            'status'          => true,
            'monthly_salary'  => 26000,
            'employment_type' => 'Full-Time',
        ]);

        $response = $this->actingAs($this->admin)->post(route('finance.payroll.process'), [
            'period' => '2026-09',
        ]);

        $response->assertRedirect(route('finance.payroll.index', ['period' => '2026-09']));

        $record = PayrollRecord::where('period', '2026-09')->where('user_id', $employee->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals(26000, $record->monthly_salary);
        $this->assertEquals(26000, $record->net_pay);
    }
}
