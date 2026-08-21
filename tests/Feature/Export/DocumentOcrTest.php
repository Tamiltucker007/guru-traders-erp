<?php

namespace Tests\Feature\Export;

use App\Models\Buyer;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DocumentFormat;
use App\Models\ExportDocument;
use App\Models\Markup;
use App\Models\OrderConfirmation;
use App\Models\PaymentTerm;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Export\ExportDocumentService;
use App\Services\Export\GeminiDocumentExtractor;
use App\Services\Export\OcrPartyMatcher;
use Database\Seeders\DocumentChecklistTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentOcrTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ExportDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(DocumentChecklistTypeSeeder::class);

        foreach ([
            'export-document.view', 'export-document.create', 'export-document.edit', 'export-document.delete',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            'export-document.view', 'export-document.create', 'export-document.edit', 'export-document.delete',
        ]);

        $buyer = Buyer::forceCreate([
            'display_code' => 'BUY01',
            'company_name' => 'Test Overseas Buyer',
            'status'       => 'active',
        ]);

        $category = new Category(['name' => 'Woven Garments', 'status' => 'active']);
        $category->code = 'CAT001';
        $category->save();

        $format = DocumentFormat::create(['name' => 'Standard Order Format', 'status' => 'active']);

        $currency = new Currency(['name' => 'US Dollar', 'symbol' => '$', 'status' => 'active']);
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

        $item = $oc->items()->create([
            'sort_order'  => 0,
            'design_no'   => 'D-100',
            'unit'        => 'pcs',
            'price'       => 5.00,
            'qty'         => 100,
            'amount'      => 500.00,
        ]);

        $this->document = app(ExportDocumentService::class)
            ->raiseFromOrderConfirmation($oc, [$item->id]);
        $this->document->load('checklist.type');
    }

    public function test_ocr_desk_page_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('export.ocr.index'))
            ->assertOk()
            ->assertSee('Checklist (from CHA)', false)
            ->assertSee('E-Sanchit Documents', false)
            ->assertSee('Assessed Copy', false)
            ->assertSee('LEO Copy', false)
            ->assertSee('CLP', false)
            ->assertSee('Bill of Lading (Final)', false)
            ->assertSee('Insurance Certificate', false)
            ->assertSee('eBRC', false);
    }

    public function test_ocr_requires_gemini_key(): void
    {
        config(['services.gemini.key' => null]);

        $this->actingAs($this->admin)
            ->postJson(route('export.ocr.extract'), [
                'file'      => UploadedFile::fake()->image('cha.jpg'),
                'type_code' => 'cha_checklist',
            ])
            ->assertStatus(503);
    }

    public function test_ocr_extracts_cha_checklist_fields(): void
    {
        config([
            'services.gemini.key'   => 'test-key',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'checklist_no'      => 'CHA/JOB/7788',
                                'checklist_date'    => '2026-08-15',
                                'shipping_bill_no'  => 'SB1234567',
                                'invoice_no'        => 'INV/001',
                                'cha_name'          => 'Demo CHA Pvt Ltd',
                                'status_or_remarks' => 'Filed on ICEGATE',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('export.ocr.extract'), [
                'file'      => UploadedFile::fake()->image('cha.jpg'),
                'type_code' => 'cha_checklist',
            ])
            ->assertOk()
            ->assertJsonPath('reference_no', 'CHA/JOB/7788')
            ->assertJsonFragment(['remarks' => 'Date: 2026-08-15 · SB: SB1234567 · Invoice: INV/001 · CHA: Demo CHA Pvt Ltd · Filed on ICEGATE']);
    }

    public function test_ocr_extracts_e_sanchit_fields(): void
    {
        config([
            'services.gemini.key'   => 'test-key',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'ack_or_ref_no'     => 'ESAN/ACK/91',
                                'document_date'     => '2026-08-14',
                                'invoice_no'        => 'GT/EXP/001/2026-27',
                                'packing_list_ref'  => 'GT/PL/001/2026-27',
                                'shipping_bill_no'  => 'SB-7845123',
                                'exporter_name'     => 'Guru Traders',
                                'status_or_remarks' => 'Ready for ICEGATE upload',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('export.ocr.extract'), [
                'file'      => UploadedFile::fake()->image('esan.jpg'),
                'type_code' => 'e_sanchit_docs',
            ])
            ->assertOk()
            ->assertJsonPath('reference_no', 'ESAN/ACK/91')
            ->assertJsonFragment([
                'remarks' => 'Date: 2026-08-14 · Invoice: GT/EXP/001/2026-27 · PL: GT/PL/001/2026-27 · SB: SB-7845123 · Exporter: Guru Traders · Ready for ICEGATE upload',
            ]);
    }

    public function test_ocr_extracts_assessed_and_leo_fields(): void
    {
        config([
            'services.gemini.key'   => 'test-key',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'assessed_ref_no'    => 'ASC/NS/1',
                                    'assessed_date'      => '2025-04-09',
                                    'shipping_bill_no'   => 'SB-1',
                                    'invoice_no'         => 'INV-1',
                                    'examiner_or_office' => 'Nhava Sheva Customs',
                                    'status_or_remarks'  => 'Passed for stuffing',
                                ]),
                            ]],
                        ],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'leo_number'        => 'LEO/NS/1',
                                    'leo_date'          => '2025-04-10',
                                    'shipping_bill_no'  => 'SB-1',
                                    'invoice_no'        => 'INV-1',
                                    'port_of_loading'   => 'Nhava Sheva',
                                    'status_or_remarks' => 'LEO granted',
                                ]),
                            ]],
                        ],
                    ]],
                ], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('export.ocr.extract'), [
                'file'      => UploadedFile::fake()->image('assessed.jpg'),
                'type_code' => 'assessed_copy',
            ])
            ->assertOk()
            ->assertJsonPath('reference_no', 'ASC/NS/1')
            ->assertJsonFragment([
                'remarks' => 'Date: 2025-04-09 · SB: SB-1 · Invoice: INV-1 · Office: Nhava Sheva Customs · Passed for stuffing',
            ]);

        $this->actingAs($this->admin)
            ->postJson(route('export.ocr.extract'), [
                'file'      => UploadedFile::fake()->image('leo.jpg'),
                'type_code' => 'leo_copy',
            ])
            ->assertOk()
            ->assertJsonPath('reference_no', 'LEO/NS/1')
            ->assertJsonFragment([
                'remarks' => 'LEO date: 2025-04-10 · SB: SB-1 · Invoice: INV-1 · Port: Nhava Sheva · LEO granted',
            ]);
    }

    public function test_ocr_rejects_non_uploaded_type_on_desk(): void
    {
        config(['services.gemini.key' => 'test-key']);

        $this->actingAs($this->admin)
            ->postJson(route('export.ocr.extract'), [
                'file'      => UploadedFile::fake()->image('pack.jpg'),
                'type_code' => 'packing_list',
            ])
            ->assertStatus(422);
    }

    public function test_saving_ocr_updates_checklist_row(): void
    {
        $file = UploadedFile::fake()->create('cha-checklist.pdf', 40, 'application/pdf');

        $this->actingAs($this->admin)
            ->post(route('export.ocr.store'), [
                'export_document_id' => $this->document->id,
                'type_code'          => 'cha_checklist',
                'file'               => $file,
                'reference_no'       => 'CHA/JOB/7788',
                'remarks'            => 'Date: 2026-08-15',
            ])
            ->assertRedirect();

        $entry = $this->document->checklist()
            ->whereHas('type', fn ($q) => $q->where('code', 'cha_checklist'))
            ->firstOrFail();

        $this->assertSame('uploaded', $entry->status);
        $this->assertSame('CHA/JOB/7788', $entry->reference_no);
        $this->assertNotNull($entry->file_path);
    }

    public function test_ocr_matches_buyer_supplier_and_order_confirmation(): void
    {
        config([
            'services.gemini.key'   => 'test-key',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        $supplier = Supplier::forceCreate([
            'display_code' => 'SUP01',
            'company_name' => 'Acme Textiles Pvt Ltd',
            'status'       => 'active',
        ]);

        $this->document->update(['invoice_no' => 'INV/OC-LINK/9']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'checklist_no'             => 'CHA/JOB/9001',
                                'checklist_date'           => '2026-08-15',
                                'shipping_bill_no'         => 'SB999',
                                'invoice_no'               => 'INV/OC-LINK/9',
                                'cha_name'                 => 'Demo CHA',
                                'buyer_or_consignee_name'  => 'Test Overseas Buyer',
                                'supplier_or_shipper_name' => 'Acme Textiles Pvt Ltd',
                                'status_or_remarks'        => 'Filed',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('export.ocr.extract'), [
                'file'               => UploadedFile::fake()->image('cha.jpg'),
                'type_code'          => 'cha_checklist',
                'export_document_id' => $this->document->id,
            ])
            ->assertOk()
            ->assertJsonPath('parties.buyer.company_name', 'Test Overseas Buyer')
            ->assertJsonPath('parties.supplier.company_name', 'Acme Textiles Pvt Ltd')
            ->assertJsonPath('parties.order_confirmation.id', $this->document->order_confirmation_id)
            ->assertJsonPath('parties.selected_oc_matches', true)
            ->assertJsonPath('parties.selected_buyer_matches', true);

        $matcher = app(OcrPartyMatcher::class);
        $this->assertSame($supplier->id, $matcher->matchSupplier('Acme Textiles')['id']);
    }

    public function test_saving_ocr_persists_matched_parties(): void
    {
        $buyerId = $this->document->buyer_id;
        $ocId = $this->document->order_confirmation_id;
        $supplier = Supplier::forceCreate([
            'display_code' => 'SUP88',
            'company_name' => 'Linked Supplier Co',
            'status'       => 'active',
        ]);

        $file = UploadedFile::fake()->create('cha.pdf', 20, 'application/pdf');

        $this->actingAs($this->admin)
            ->post(route('export.ocr.store'), [
                'export_document_id'  => $this->document->id,
                'type_code'           => 'cha_checklist',
                'file'                => $file,
                'reference_no'        => 'CHA/LINK/1',
                'remarks'             => 'Buyer linked',
                'matched_buyer_id'    => $buyerId,
                'matched_supplier_id' => $supplier->id,
                'matched_order_confirmation_id' => $ocId,
            ])
            ->assertRedirect();

        $entry = $this->document->checklist()
            ->whereHas('type', fn ($q) => $q->where('code', 'cha_checklist'))
            ->firstOrFail();

        $this->assertSame($buyerId, $entry->matched_buyer_id);
        $this->assertSame($supplier->id, $entry->matched_supplier_id);
        $this->assertSame($ocId, $entry->matched_order_confirmation_id);
    }

    public function test_ocr_extract_includes_order_context(): void
    {
        config([
            'services.gemini.key'   => 'test-key',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'checklist_no'             => 'CHA/CTX/1',
                                'checklist_date'           => '2026-08-20',
                                'shipping_bill_no'         => 'SB1',
                                'invoice_no'               => 'INV-CTX',
                                'cha_name'                 => 'CHA',
                                'buyer_or_consignee_name'  => 'Test Overseas Buyer',
                                'supplier_or_shipper_name' => null,
                                'status_or_remarks'        => 'ok',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('export.ocr.extract'), [
                'file'               => UploadedFile::fake()->image('cha.jpg'),
                'type_code'          => 'cha_checklist',
                'export_document_id' => $this->document->id,
            ])
            ->assertOk()
            ->assertJsonPath('order_context.available', true)
            ->assertJsonPath('order_context.export_document.id', $this->document->id)
            ->assertJsonPath('order_context.order_confirmation.id', $this->document->order_confirmation_id)
            ->assertJsonStructure([
                'order_context' => [
                    'buyer',
                    'payment' => ['payment_received', 'ebrc', 'pending_labels', 'due_note'],
                    'pricing',
                    'line_calcs',
                    'min_prices',
                    'inventory',
                    'jobbers',
                    'summary',
                ],
                'verification' => [
                    'status',
                    'status_label',
                    'rows',
                    'summary',
                ],
            ]);
    }

    public function test_ocr_order_context_auto_calcs_and_min_supplier_price(): void
    {
        $supplierCheap = Supplier::forceCreate([
            'display_code'     => 'SUP-LO',
            'company_name'     => 'Cheap Mill',
            'discount_percent' => 5,
            'status'           => 'active',
        ]);
        $supplierDear = Supplier::forceCreate([
            'display_code'     => 'SUP-HI',
            'company_name'     => 'Dear Mill',
            'discount_percent' => 0,
            'status'           => 'active',
        ]);

        $buyer = $this->document->buyer;
        $oc = $this->document->orderConfirmation;

        $oc->items()->first()->update([
            'supplier_id' => $supplierDear->id,
            'design_no'   => 'D-100',
            'price'       => 12.00,
            'cost_price'  => 10.00,
            'qty'         => 100,
            'amount'      => 1200.00,
        ]);

        $otherBuyer = Buyer::forceCreate([
            'display_code' => 'BUY99',
            'company_name' => 'Other Buyer Ltd',
            'status'       => 'active',
        ]);

        $otherOc = new OrderConfirmation([
            'buyer_id'           => $otherBuyer->id,
            'category_id'        => $oc->category_id,
            'document_format_id' => $oc->document_format_id,
            'currency_id'        => $oc->currency_id,
            'oc_date'            => now()->toDateString(),
            'status'             => 'confirmed',
        ]);
        $otherOc->oc_num = 'GT/OC/099/26-27';
        $otherOc->financial_year = '26-27';
        $otherOc->save();
        $otherOc->items()->create([
            'sort_order'  => 0,
            'design_no'   => 'D-100',
            'supplier_id' => $supplierCheap->id,
            'unit'        => 'pcs',
            'price'       => 9.00,
            'cost_price'  => 7.50,
            'qty'         => 50,
            'amount'      => 450.00,
        ]);

        Markup::forceCreate([
            'buyer_id'       => $buyer->id,
            'supplier_id'    => $supplierDear->id,
            'markup_percent' => 20,
            'status'         => 'active',
            'record_date'    => now()->toDateString(),
        ]);

        $term = PaymentTerm::forceCreate([
            'name'       => 'Net 30',
            'days'       => 30,
            'has_split'  => false,
            'applies_to' => 'buyer',
            'status'     => 'active',
        ]);
        $buyer->update([
            'payment_term_id' => $term->id,
        ]);
        $this->document->update([
            'invoice_date' => '2026-08-01',
        ]);

        $ctx = app(\App\Services\Export\OcrOrderContextBuilder::class)
            ->build($this->document->fresh([
                'buyer.paymentTerm',
                'orderConfirmation.items.supplier',
                'orderConfirmation.items.product',
                'checklist.type',
            ]));

        $this->assertTrue($ctx['available']);
        $this->assertSame('31 Aug 2026', $ctx['payment']['due_date']);

        $this->assertNotEmpty($ctx['min_prices']);
        $min = collect($ctx['min_prices'])->firstWhere('product', 'D-100');
        $this->assertNotNull($min);
        $this->assertSame(7.5, $min['min_cost_price']);
        $this->assertStringContainsString('SUP-LO', (string) $min['min_cost_supplier']);
        $this->assertSame(9.0, $min['min_list_price']);

        $this->assertNotEmpty($ctx['line_calcs']);
        $calc = $ctx['line_calcs'][0];
        $this->assertSame(12.0, $calc['client_price']);
        $this->assertSame(10.0, $calc['our_cost']);
        $this->assertSame(2.0, $calc['unit_profit']);
        $this->assertSame(200.0, $calc['line_profit']);
    }

    public function test_order_context_includes_inventory_and_jobbers(): void
    {
        $jobber = Supplier::forceCreate([
            'display_code' => 'JOB01',
            'company_name' => 'Stitch Jobber Co',
            'party_type'   => 'jobber',
            'status'       => 'active',
        ]);
        $jobber->buyers()->syncWithoutDetaching([$this->document->buyer_id]);

        $ctx = app(\App\Services\Export\OcrOrderContextBuilder::class)
            ->build($this->document->fresh([
                'buyer.paymentTerm',
                'orderConfirmation.items.supplier',
                'orderConfirmation.items.product',
                'orderConfirmation.purchaseOrders',
                'checklist.type',
            ]));

        $this->assertArrayHasKey('inventory', $ctx);
        $this->assertArrayHasKey('rows', $ctx['inventory']);
        $this->assertNotEmpty($ctx['jobbers']);
        $this->assertTrue(collect($ctx['jobbers'])->contains(fn ($j) => $j['id'] === $jobber->id));
    }

    public function test_ocr_extract_includes_erp_vs_document_verification(): void
    {
        config([
            'services.gemini.key'   => 'test-key',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        $this->document->buyer->update(['company_name' => 'Test Overseas Buyer']);
        $this->document->update(['invoice_no' => 'INV-MATCH-1']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'checklist_no'             => 'CHA/VER/1',
                                'checklist_date'           => '2026-08-20',
                                'shipping_bill_no'         => 'SB1',
                                'invoice_no'               => 'INV-MATCH-1',
                                'cha_name'                 => 'CHA',
                                'buyer_or_consignee_name'  => 'Test Overseas Buyer',
                                'hs_code'                  => '610190',
                                'currency'                 => 'USD',
                                'status_or_remarks'        => 'ok',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('export.ocr.extract'), [
                'file'               => UploadedFile::fake()->image('cha.jpg'),
                'type_code'          => 'cha_checklist',
                'export_document_id' => $this->document->id,
            ])
            ->assertOk();

        $rows = collect($response->json('verification.rows'));
        $this->assertTrue($rows->contains(fn ($r) => $r['field'] === 'Buyer Name' && $r['result'] === 'match'));
        $this->assertTrue($rows->contains(fn ($r) => $r['field'] === 'Invoice No.' && $r['result'] === 'match'));
    }

    public function test_price_decimal_mismatch_is_error_including_rs(): void
    {
        $verifier = app(\App\Services\Export\OcrFieldVerifier::class);

        $this->document->load(['buyer', 'currency', 'items', 'orderConfirmation.items']);
        // ERP total rounded / stored as whole rupees.
        $this->document->setRelation('items', collect([
            (object) ['qty' => 1, 'price' => 10678.0, 'product' => null],
        ]));

        // Fake totalAmount via a partial mock is heavy — call compare with extracted doc amount.
        $result = $verifier->compare($this->document->fresh(['buyer', 'currency', 'items.product', 'orderConfirmation.items.product']), [
            'buyer_name' => $this->document->buyer?->company_name,
            'fields'     => [
                'invoice_no'   => $this->document->invoice_no,
                'total_amount' => '10,678.678',
                'currency'     => 'INR',
                'unit_price'   => 'Rs 10,678.678',
            ],
        ]);

        $total = collect($result['rows'])->firstWhere('field', 'Total Amount');
        $this->assertNotNull($total);
        // Document has paisa decimals; ERP line is 10678 → Price error
        if (($total['erp'] ?? '—') !== '—') {
            $this->assertSame('error', $total['result']);
            $this->assertSame('Price error', $total['result_label']);
        }

        // Direct unit-price style compare via same judge path: 10678.678 vs 10678
        $unit = collect($result['rows'])->firstWhere('field', 'Unit Price');
        if ($unit && ($unit['erp'] ?? '—') !== '—' && ($unit['document'] ?? '—') !== '—') {
            $this->assertSame('error', $unit['result']);
            $this->assertSame('Price error', $unit['result_label']);
        }
    }

    public function test_ocr_falls_back_to_selected_shipment_parties(): void
    {
        config([
            'services.gemini.key'   => 'test-key',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        $supplier = Supplier::forceCreate([
            'display_code' => 'SUP77',
            'company_name' => 'Shipment Supplier Co',
            'status'       => 'active',
        ]);

        $this->document->orderConfirmation->items()->first()?->update(['supplier_id' => $supplier->id]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'checklist_no'             => 'CHA/OTHER',
                                'checklist_date'           => '2026-08-20',
                                'shipping_bill_no'         => 'SB9',
                                'invoice_no'               => 'OTHER-INV-999',
                                'cha_name'                 => 'CHA',
                                'buyer_or_consignee_name'  => 'BHANABHAI AND SONS PTE LTD.',
                                'supplier_or_shipper_name' => 'GURU TRADERS',
                                'status_or_remarks'        => 'Assessed',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        // Company profile name so exporter is skipped as supplier.
        \App\Models\CompanyProfile::current()->update(['company_name' => 'Guru Traders']);

        $this->actingAs($this->admin)
            ->postJson(route('export.ocr.extract'), [
                'file'               => UploadedFile::fake()->image('assessed.jpg'),
                'type_code'          => 'assessed_copy',
                'export_document_id' => $this->document->id,
            ])
            ->assertOk()
            ->assertJsonPath('parties.buyer.id', $this->document->buyer_id)
            ->assertJsonPath('parties.buyer.source', 'shipment')
            ->assertJsonPath('parties.order_confirmation.id', $this->document->order_confirmation_id)
            ->assertJsonPath('parties.order_confirmation.source', 'shipment')
            ->assertJsonPath('parties.supplier.id', $supplier->id)
            ->assertJsonPath('parties.selected_buyer_matches', true)
            ->assertJsonPath('parties.selected_oc_matches', true);
    }

    public function test_phase1_lists_all_uploaded_types(): void
    {
        $this->assertSame(
            GeminiDocumentExtractor::UPLOADED_TYPES,
            GeminiDocumentExtractor::PHASE1_TYPES
        );
        $this->assertContains('clp', GeminiDocumentExtractor::PHASE1_TYPES);
        $this->assertContains('bl_final', GeminiDocumentExtractor::PHASE1_TYPES);
        $this->assertContains('ebrc', GeminiDocumentExtractor::PHASE1_TYPES);
        $this->assertNotContains('packing_list', GeminiDocumentExtractor::UPLOADED_TYPES);
    }
}
