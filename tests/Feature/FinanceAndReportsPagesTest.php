<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DocumentChecklistTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinanceAndReportsPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DocumentChecklistTypeSeeder::class);

        foreach ([
            'packing.view',
            'purchase-bill.view',
            'debit-note.view',
            'payment.view',
            'foreign-payment.view',
            'agent-commission.view',
            'outstanding.view',
            'report.view',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            'packing.view',
            'purchase-bill.view',
            'debit-note.view',
            'payment.view',
            'foreign-payment.view',
            'agent-commission.view',
            'outstanding.view',
            'report.view',
        ]);
    }

    public function test_guest_is_redirected_from_new_module_pages(): void
    {
        $this->get(route('export.packing.index'))->assertRedirect();
        $this->get(route('finance.purchase-bills.index'))->assertRedirect();
        $this->get(route('reports.index'))->assertRedirect();
    }

    public function test_admin_can_open_packing_finance_and_report_pages(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('export.packing.index'))->assertOk();
        $this->get(route('finance.purchase-bills.index'))->assertOk();
        $this->get(route('finance.debit-notes.index'))->assertOk();
        $this->get(route('finance.supplier-payments.index'))->assertOk();
        $this->get(route('finance.buyer-receipts.index'))->assertOk();
        $this->get(route('finance.agent-commission.index'))
            ->assertOk()
            ->assertSee('>Agent Commission</p>', false)
            ->assertDontSee('href="'.route('dashboard', absolute: false).'" class="nav-link">Dashboard</a>', false);
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('reports.outstanding.index'))->assertOk();
    }
}
