<?php

namespace App\Services\Export;

use App\Models\ExportDocument;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\InwardEntryItem;
use App\Models\Markup;
use App\Models\OrderConfirmation;
use App\Models\OrderConfirmationItem;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Commercial cockpit for one order — used on the OCR desk and as a popup on
 * Inquiry / OC / Export Document screens: buyer, suppliers, jobbers, payment,
 * Markup auto-calc, min price across parties, and PO/Inward stock proxies
 * (Guru Traders keeps no ready-stock ledger).
 */
class OcrOrderContextBuilder
{
    /** Checklist codes that tell payment / realisation progress. */
    private const PAYMENT_CODES = [
        'payment_received',
        'eefc_upload',
        'firc',
        'bank_certificate',
        'ebrc',
        'goods_received',
    ];

    /**
     * @param  array{
     *     buyer?: ?array{id:int},
     *     supplier?: ?array{id:int},
     *     order_confirmation?: ?array{id:int}
     * }|null  $parties
     * @return array<string, mixed>
     */
    public function build(?ExportDocument $document, ?array $parties = null): array
    {
        if (! $document) {
            return [
                'available' => false,
                'message'   => 'Select an Export Document to see order context.',
            ];
        }

        $document->loadMissing([
            'buyer.paymentTerm',
            'currency',
            'orderConfirmation.buyer',
            'orderConfirmation.currency',
            'orderConfirmation.items.supplier',
            'orderConfirmation.items.product',
            'orderConfirmation.purchaseOrders',
            'items.sourceItem.supplier',
            'checklist.type',
        ]);

        $oc = $document->orderConfirmation;
        if (! $oc && ! empty($parties['order_confirmation']['id'])) {
            $oc = OrderConfirmation::query()
                ->with(['buyer', 'currency', 'items.supplier', 'items.product'])
                ->find($parties['order_confirmation']['id']);
        }

        $buyer = $document->buyer;
        $buyerId = $buyer?->id ?? ($parties['buyer']['id'] ?? null);

        $suppliers = $this->suppliersOnOrder($document, $oc);
        $lines = $this->commercialLines($oc);
        $payments = $this->paymentSnapshot($document, $buyer);
        $lineCalcs = $this->lineCalculations($buyerId, $oc);
        $minPrices = $this->minPricesForProducts($oc);
        $inventory = $this->inventorySnapshot($oc, $lines);
        $jobbers = $this->jobbersForOrder($buyerId, $oc, $lines);
        $pricing = $this->pricingHints($buyerId, $suppliers, $lines, $lineCalcs, $minPrices);

        return [
            'available' => true,
            'module'    => 'export_document',
            'export_document' => [
                'id'           => $document->id,
                'doc_num'      => $document->doc_num,
                'status'       => $document->status,
                'status_label' => $document->statusLabel(),
                'invoice_no'   => $document->invoice_no,
                'invoice_date' => optional($document->invoice_date)?->format('d M Y'),
                'amount'       => round($document->totalAmount(), 2),
                'currency'     => $document->currency?->iso_code ?? $oc?->currency?->iso_code,
            ],
            'order_confirmation' => $oc ? [
                'id'          => $oc->id,
                'oc_num'      => $oc->oc_num,
                'buyer_ref'   => $oc->buyer_ref,
                'status'      => $oc->status,
                'status_label'=> $oc->statusLabel(),
                'oc_date'     => optional($oc->oc_date)?->format('d M Y'),
                'amount'      => round($oc->totalAmount(), 2),
                'currency'    => $oc->currency?->iso_code,
            ] : null,
            'inquiry' => null,
            'buyer' => $buyer ? [
                'id'             => $buyer->id,
                'display_code'   => $buyer->display_code,
                'company_name'   => $buyer->company_name,
                'payment_term'   => $buyer->paymentTerm?->name,
                'payment_term_days' => $buyer->paymentTerm?->days,
                'advance_percent'=> $buyer->advance_percent !== null ? (float) $buyer->advance_percent : null,
                'sight_percent'  => $buyer->sight_percent !== null ? (float) $buyer->sight_percent : null,
            ] : null,
            'suppliers'   => $suppliers,
            'jobbers'     => $jobbers,
            'payment'     => $payments,
            'lines'       => $lines,
            'line_calcs'  => $lineCalcs,
            'min_prices'  => $minPrices,
            'inventory'   => $inventory,
            'pricing'     => $pricing,
            'summary'     => $this->summaryText($document, $oc, $payments, $minPrices, $inventory, $jobbers),
        ];
    }

    /**
     * Same cockpit for OC show / popup — prefers the latest linked Export Document
     * when one exists (so payment checklist is available).
     *
     * @return array<string, mixed>
     */
    public function buildFromOrderConfirmation(OrderConfirmation $oc): array
    {
        $oc->loadMissing([
            'buyer.paymentTerm',
            'currency',
            'items.supplier',
            'items.product',
            'purchaseOrders',
            'exportDocuments.checklist.type',
            'exportDocuments.buyer.paymentTerm',
            'exportDocuments.currency',
            'exportDocuments.items.sourceItem.supplier',
        ]);

        $document = $oc->exportDocuments->sortByDesc('id')->first();
        if ($document) {
            $ctx = $this->build($document);
            $ctx['module'] = 'order_confirmation';

            return $ctx;
        }

        $buyer = $oc->buyer;
        $buyerId = $buyer?->id;
        $suppliers = $this->suppliersOnOrder(null, $oc);
        $lines = $this->commercialLines($oc);
        $payments = $this->emptyPayment('Raise / open an Export Document to track Swift / eBRC.');
        $lineCalcs = $this->lineCalculations($buyerId, $oc);
        $minPrices = $this->minPricesForProducts($oc);
        $inventory = $this->inventorySnapshot($oc, $lines);
        $jobbers = $this->jobbersForOrder($buyerId, $oc, $lines);
        $pricing = $this->pricingHints($buyerId, $suppliers, $lines, $lineCalcs, $minPrices);

        return [
            'available' => true,
            'module'    => 'order_confirmation',
            'export_document' => null,
            'order_confirmation' => [
                'id'          => $oc->id,
                'oc_num'      => $oc->oc_num,
                'buyer_ref'   => $oc->buyer_ref,
                'status'      => $oc->status,
                'status_label'=> $oc->statusLabel(),
                'oc_date'     => optional($oc->oc_date)?->format('d M Y'),
                'amount'      => round($oc->totalAmount(), 2),
                'currency'    => $oc->currency?->iso_code,
            ],
            'inquiry' => null,
            'buyer' => $buyer ? [
                'id'                => $buyer->id,
                'display_code'      => $buyer->display_code,
                'company_name'      => $buyer->company_name,
                'payment_term'      => $buyer->paymentTerm?->name,
                'payment_term_days' => $buyer->paymentTerm?->days,
                'advance_percent'   => $buyer->advance_percent !== null ? (float) $buyer->advance_percent : null,
                'sight_percent'     => $buyer->sight_percent !== null ? (float) $buyer->sight_percent : null,
            ] : null,
            'suppliers'  => $suppliers,
            'jobbers'    => $jobbers,
            'payment'    => $payments,
            'lines'      => $lines,
            'line_calcs' => $lineCalcs,
            'min_prices' => $minPrices,
            'inventory'  => $inventory,
            'pricing'    => $pricing,
            'summary'    => $this->summaryText(null, $oc, $payments, $minPrices, $inventory, $jobbers),
        ];
    }

    /**
     * Inquiry show / popup — if already converted to OC, reuse OC/ED path.
     *
     * @return array<string, mixed>
     */
    public function buildFromInquiry(Inquiry $inquiry): array
    {
        $inquiry->loadMissing([
            'buyer.paymentTerm',
            'currency',
            'items.supplier',
            'items.product',
        ]);

        $oc = OrderConfirmation::query()
            ->with([
                'buyer.paymentTerm',
                'currency',
                'items.supplier',
                'items.product',
                'exportDocuments.checklist.type',
                'exportDocuments.buyer.paymentTerm',
                'exportDocuments.currency',
                'exportDocuments.items.sourceItem.supplier',
            ])
            ->where('source_inquiry_id', $inquiry->id)
            ->latest('id')
            ->first();

        if ($oc) {
            $ctx = $this->buildFromOrderConfirmation($oc);
            $ctx['module'] = 'inquiry';
            $ctx['inquiry'] = [
                'id'         => $inquiry->id,
                'inquiry_no' => $inquiry->inquiry_no,
                'status'     => $inquiry->status,
                'status_label' => $inquiry->statusLabel(),
            ];

            return $ctx;
        }

        $buyer = $inquiry->buyer;
        $buyerId = $buyer?->id;
        $lines = $inquiry->items->take(12)->map(fn ($item) => [
            'product_id'  => $item->product_id,
            'product'     => $item->product?->name ?? $item->design_no,
            'supplier_id' => $item->supplier_id,
            'supplier'    => $item->supplier?->company_name,
            'unit'        => $item->unit,
            'qty'         => (float) $item->qty,
            'price'       => (float) $item->price,
            'cost_price'  => $item->cost_price !== null ? (float) $item->cost_price : null,
            'amount'      => (float) $item->amount,
        ])->all();

        $supplierIds = collect($lines)->pluck('supplier_id')->filter()->unique();
        $suppliers = Supplier::query()
            ->whereIn('id', $supplierIds)
            ->get()
            ->map(fn (Supplier $s) => [
                'id'               => $s->id,
                'display_code'     => $s->display_code,
                'company_name'     => $s->company_name,
                'discount_percent' => $s->discount_percent !== null ? (float) $s->discount_percent : null,
            ])->all();

        $ocStub = new OrderConfirmation;
        $ocStub->setRelation('items', $inquiry->items->map(function ($item) {
            $row = new OrderConfirmationItem([
                'product_id'  => $item->product_id,
                'design_no'   => $item->design_no,
                'price'       => $item->price,
                'cost_price'  => $item->cost_price,
                'supplier_id' => $item->supplier_id,
                'qty'         => $item->qty,
                'unit'        => $item->unit,
                'amount'      => $item->amount,
            ]);
            $row->setRelation('product', $item->product);
            $row->setRelation('supplier', $item->supplier);

            return $row;
        }));

        $payments = $this->emptyPayment('Convert to OC and raise Export Document to track payment.');
        $minPrices = $this->minPricesForProducts($ocStub);
        $inventory = $this->inventorySnapshot(null, $lines);
        $jobbers = $this->jobbersForOrder($buyerId, null, $lines);
        $lineCalcs = [];
        $pricing = $this->pricingHints($buyerId, $suppliers, $lines, $lineCalcs, $minPrices);

        return [
            'available' => true,
            'module'    => 'inquiry',
            'export_document' => null,
            'order_confirmation' => null,
            'inquiry' => [
                'id'           => $inquiry->id,
                'inquiry_no'   => $inquiry->inquiry_no,
                'status'       => $inquiry->status,
                'status_label' => $inquiry->statusLabel(),
            ],
            'buyer' => $buyer ? [
                'id'                => $buyer->id,
                'display_code'      => $buyer->display_code,
                'company_name'      => $buyer->company_name,
                'payment_term'      => $buyer->paymentTerm?->name,
                'payment_term_days' => $buyer->paymentTerm?->days,
                'advance_percent'   => $buyer->advance_percent !== null ? (float) $buyer->advance_percent : null,
                'sight_percent'     => $buyer->sight_percent !== null ? (float) $buyer->sight_percent : null,
            ] : null,
            'suppliers'  => $suppliers,
            'jobbers'    => $jobbers,
            'payment'    => $payments,
            'lines'      => $lines,
            'line_calcs' => $lineCalcs,
            'min_prices' => $minPrices,
            'inventory'  => $inventory,
            'pricing'    => $pricing,
            'summary'    => collect([
                $inquiry->inquiry_no,
                $buyer?->company_name,
                $jobbers !== [] ? count($jobbers).' jobber(s)' : null,
            ])->filter()->implode(' · '),
        ];
    }

    /**
     * @return list<array{id:int,display_code:?string,company_name:string,discount_percent:?float}>
     */
    private function suppliersOnOrder(?ExportDocument $document, ?OrderConfirmation $oc): array
    {
        $rows = collect();

        if ($oc) {
            foreach ($oc->items as $item) {
                if ($item->supplier) {
                    $rows->put($item->supplier->id, $item->supplier);
                }
            }
        }

        if ($document) {
            foreach ($document->items as $item) {
                $supplier = $item->sourceItem?->supplier;
                if ($supplier) {
                    $rows->put($supplier->id, $supplier);
                }
            }
        }

        return $rows->values()->map(fn (Supplier $s) => [
            'id'               => $s->id,
            'display_code'     => $s->display_code,
            'company_name'     => $s->company_name,
            'discount_percent' => $s->discount_percent !== null ? (float) $s->discount_percent : null,
        ])->all();
    }

    /**
     * @return list<array{product_id:?int,product:?string,supplier_id:?int,supplier:?string,unit:?string,qty:float,price:float,cost_price:?float,amount:float}>
     */
    private function commercialLines(?OrderConfirmation $oc): array
    {
        if (! $oc) {
            return [];
        }

        return $oc->items->take(12)->map(fn ($item) => [
            'product_id'  => $item->product_id,
            'product'     => $item->product?->name ?? $item->design_no,
            'supplier_id' => $item->supplier_id,
            'supplier'    => $item->supplier?->company_name,
            'unit'        => $item->unit,
            'qty'         => (float) $item->qty,
            'price'       => (float) $item->price,
            'cost_price'  => $item->cost_price !== null ? (float) $item->cost_price : null,
            'amount'      => (float) $item->amount,
        ])->all();
    }

    /**
     * Auto-calc client price / our cost / profit per OC line via Markup master.
     *
     * @return list<array<string, mixed>>
     */
    private function lineCalculations(?int $buyerId, ?OrderConfirmation $oc): array
    {
        if (! $buyerId || ! $oc || $oc->items->isEmpty()) {
            return [];
        }

        $markups = Markup::query()
            ->with('supplier:id,discount_percent')
            ->where('buyer_id', $buyerId)
            ->where('status', 'active')
            ->get()
            ->keyBy('supplier_id');

        return $oc->items->take(12)->map(function ($item) use ($markups) {
            $listPrice = (float) $item->price;
            $costPrice = $item->cost_price !== null ? (float) $item->cost_price : $listPrice;
            $markup = $item->supplier_id ? ($markups->get($item->supplier_id) ?? null) : null;

            $row = [
                'product'         => $item->product?->name ?? $item->design_no,
                'supplier'        => $item->supplier?->company_name,
                'qty'             => (float) $item->qty,
                'unit'            => $item->unit,
                'list_price'      => $listPrice,
                'cost_price'      => $costPrice,
                'markup_percent'  => null,
                'client_price'    => null,
                'our_cost'        => null,
                'unit_profit'     => null,
                'line_profit'     => null,
                'note'            => null,
            ];

            if (! $markup || $costPrice <= 0) {
                $row['note'] = $markup
                    ? 'Markup found but cost/list price is zero.'
                    : ($item->supplier_id ? 'No active Markup for this buyer–supplier.' : 'No supplier on line.');

                return $row;
            }

            $client = $markup->clientPrice($costPrice);
            $ourCost = $markup->ourCost($costPrice);
            $profit = $markup->profit($costPrice);

            $row['markup_percent'] = (float) $markup->markup_percent;
            $row['client_price'] = $client;
            $row['our_cost'] = $ourCost;
            $row['unit_profit'] = $profit;
            $row['line_profit'] = round($profit * (float) $item->qty, 2);
            $row['note'] = 'Auto from Markup master';

            return $row;
        })->all();
    }

    /**
     * For each product on this OC, collect list/cost quotes from inquiries and
     * other OCs (any buyer/supplier) and surface the minimum available price.
     *
     * @return list<array<string, mixed>>
     */
    private function minPricesForProducts(?OrderConfirmation $oc): array
    {
        if (! $oc) {
            return [];
        }

        $productIds = $oc->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            // Fall back to design_no grouping when masters have no product_id.
            return $this->minPricesByDesignNo($oc);
        }

        $inquiryOffers = InquiryItem::query()
            ->with(['supplier:id,display_code,company_name', 'product:id,name', 'inquiry.buyer:id,display_code,company_name'])
            ->whereIn('product_id', $productIds)
            ->where(function ($q) {
                $q->whereNotNull('price')->orWhereNotNull('cost_price');
            })
            ->limit(200)
            ->get();

        $ocOffers = OrderConfirmationItem::query()
            ->with([
                'supplier:id,display_code,company_name',
                'product:id,name',
                'orderConfirmation.buyer:id,display_code,company_name',
            ])
            ->whereIn('product_id', $productIds)
            ->where(function ($q) {
                $q->whereNotNull('price')->orWhereNotNull('cost_price');
            })
            ->limit(200)
            ->get();

        $byProduct = [];

        foreach ($productIds as $productId) {
            $name = $oc->items->firstWhere('product_id', $productId)?->product?->name
                ?? $oc->items->firstWhere('product_id', $productId)?->design_no
                ?? 'Product #'.$productId;

            $offers = collect();

            foreach ($inquiryOffers->where('product_id', $productId) as $row) {
                $offers->push($this->offerRow(
                    'inquiry',
                    $row->supplier,
                    $row->inquiry?->buyer,
                    $row->price !== null ? (float) $row->price : null,
                    $row->cost_price !== null ? (float) $row->cost_price : null,
                ));
            }

            foreach ($ocOffers->where('product_id', $productId) as $row) {
                $offers->push($this->offerRow(
                    'oc',
                    $row->supplier,
                    $row->orderConfirmation?->buyer,
                    $row->price !== null ? (float) $row->price : null,
                    $row->cost_price !== null ? (float) $row->cost_price : null,
                ));
            }

            $byProduct[] = $this->summariseOffers((int) $productId, $name, $offers);
        }

        return $byProduct;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function minPricesByDesignNo(OrderConfirmation $oc): array
    {
        $designNos = $oc->items
            ->pluck('design_no')
            ->filter()
            ->unique()
            ->values();

        if ($designNos->isEmpty()) {
            return [];
        }

        $inquiryOffers = InquiryItem::query()
            ->with(['supplier:id,display_code,company_name', 'inquiry.buyer:id,display_code,company_name'])
            ->whereIn('design_no', $designNos)
            ->where(function ($q) {
                $q->whereNotNull('price')->orWhereNotNull('cost_price');
            })
            ->limit(200)
            ->get();

        $ocOffers = OrderConfirmationItem::query()
            ->with([
                'supplier:id,display_code,company_name',
                'orderConfirmation.buyer:id,display_code,company_name',
            ])
            ->whereIn('design_no', $designNos)
            ->where(function ($q) {
                $q->whereNotNull('price')->orWhereNotNull('cost_price');
            })
            ->limit(200)
            ->get();

        $out = [];
        foreach ($designNos as $designNo) {
            $offers = collect();
            foreach ($inquiryOffers->where('design_no', $designNo) as $row) {
                $offers->push($this->offerRow(
                    'inquiry',
                    $row->supplier,
                    $row->inquiry?->buyer,
                    $row->price !== null ? (float) $row->price : null,
                    $row->cost_price !== null ? (float) $row->cost_price : null,
                ));
            }
            foreach ($ocOffers->where('design_no', $designNo) as $row) {
                $offers->push($this->offerRow(
                    'oc',
                    $row->supplier,
                    $row->orderConfirmation?->buyer,
                    $row->price !== null ? (float) $row->price : null,
                    $row->cost_price !== null ? (float) $row->cost_price : null,
                ));
            }
            $out[] = $this->summariseOffers(null, (string) $designNo, $offers);
        }

        return $out;
    }

    /**
     * @return array{source:string,supplier:?string,supplier_code:?string,buyer:?string,buyer_code:?string,list_price:?float,cost_price:?float}
     */
    private function offerRow(
        string $source,
        ?Supplier $supplier,
        mixed $buyer,
        ?float $listPrice,
        ?float $costPrice,
    ): array {
        return [
            'source'        => $source,
            'supplier'      => $supplier?->company_name,
            'supplier_code' => $supplier?->display_code,
            'buyer'         => is_object($buyer) ? ($buyer->company_name ?? null) : null,
            'buyer_code'    => is_object($buyer) ? ($buyer->display_code ?? null) : null,
            'list_price'    => $listPrice !== null && $listPrice > 0 ? $listPrice : null,
            'cost_price'    => $costPrice !== null && $costPrice > 0 ? $costPrice : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $offers
     * @return array<string, mixed>
     */
    private function summariseOffers(?int $productId, string $name, Collection $offers): array
    {
        // Deduplicate identical supplier+prices so the table stays readable.
        $unique = $offers
            ->filter(fn ($o) => ($o['list_price'] ?? null) !== null || ($o['cost_price'] ?? null) !== null)
            ->unique(fn ($o) => implode('|', [
                $o['supplier_code'] ?? '',
                $o['buyer_code'] ?? '',
                (string) ($o['list_price'] ?? ''),
                (string) ($o['cost_price'] ?? ''),
                $o['source'],
            ]))
            ->values();

        $minCost = $unique
            ->filter(fn ($o) => ($o['cost_price'] ?? null) !== null)
            ->sortBy('cost_price')
            ->first();

        $minList = $unique
            ->filter(fn ($o) => ($o['list_price'] ?? null) !== null)
            ->sortBy('list_price')
            ->first();

        return [
            'product_id'         => $productId,
            'product'            => $name,
            'offer_count'        => $unique->count(),
            'offers'             => $unique->take(12)->all(),
            'min_cost_price'     => $minCost['cost_price'] ?? null,
            'min_cost_supplier'  => $minCost
                ? trim(($minCost['supplier_code'] ? $minCost['supplier_code'].' — ' : '').($minCost['supplier'] ?? ''))
                : null,
            'min_list_price'     => $minList['list_price'] ?? null,
            'min_list_party'     => $minList
                ? trim(collect([
                    $minList['supplier_code'] ? 'Sup '.$minList['supplier_code'] : ($minList['supplier'] ?? null),
                    $minList['buyer_code'] ? 'Buy '.$minList['buyer_code'] : ($minList['buyer'] ?? null),
                ])->filter()->implode(' / '))
                : null,
        ];
    }

    /**
     * @return array{
     *     payment_received: array{status:string,label:string,reference:?string},
     *     eefc_upload: array{status:string,label:string,reference:?string},
     *     ebrc: array{status:string,label:string,reference:?string},
     *     goods_received: array{status:string,label:string,reference:?string},
     *     realised: bool,
     *     pending_labels: list<string>,
     *     due_date:?string,
     *     due_note:?string
     * }
     */
    private function paymentSnapshot(ExportDocument $document, mixed $buyer): array
    {
        $byCode = [];
        foreach ($document->checklist as $row) {
            $code = $row->type?->code;
            if (! $code || ! in_array($code, self::PAYMENT_CODES, true)) {
                continue;
            }
            $byCode[$code] = [
                'status'    => $row->status,
                'label'     => $row->statusLabel(),
                'reference' => $row->reference_no,
                'name'      => $row->type?->name,
            ];
        }

        $pick = function (string $code) use ($byCode): array {
            return $byCode[$code] ?? [
                'status'    => 'pending',
                'label'     => 'Pending',
                'reference' => null,
                'name'      => $code,
            ];
        };

        $pending = [];
        foreach (['payment_received' => 'Swift / Payment', 'eefc_upload' => 'EEFC proof', 'ebrc' => 'eBRC'] as $code => $label) {
            $row = $pick($code);
            if (in_array($row['status'], ['pending', 'cancelled'], true)) {
                $pending[] = $label;
            }
        }

        $paymentDone = ! in_array($pick('payment_received')['status'], ['pending', 'cancelled'], true);
        $ebrcDone = ! in_array($pick('ebrc')['status'], ['pending', 'cancelled'], true);

        $dueDate = null;
        $dueNote = null;
        $days = $buyer?->paymentTerm?->days;
        $anchor = $document->invoice_date
            ?? $document->orderConfirmation?->oc_date
            ?? null;

        if ($days !== null && $anchor) {
            $due = Carbon::parse($anchor)->addDays((int) $days);
            $dueDate = $due->format('d M Y');
            $dueNote = $paymentDone
                ? 'Payment already marked received.'
                : ('Due '.$dueDate.' ('.$buyer->paymentTerm->name.', '.$days.' days from invoice/OC date).');
        } elseif (! $paymentDone) {
            $dueNote = $buyer?->paymentTerm?->name
                ? 'Payment term: '.$buyer->paymentTerm->name.' — set invoice date to compute due date.'
                : 'No payment term / invoice date to compute due date.';
        }

        return [
            'payment_received' => $pick('payment_received'),
            'eefc_upload'      => $pick('eefc_upload'),
            'ebrc'             => $pick('ebrc'),
            'goods_received'   => $pick('goods_received'),
            'realised'         => $paymentDone && $ebrcDone,
            'pending_labels'   => $pending,
            'due_date'         => $dueDate,
            'due_note'         => $dueNote,
        ];
    }

    /**
     * @param  list<array{id:int,discount_percent:?float}>  $suppliers
     * @param  list<array{price:float,amount:float,supplier:?string}>  $lines
     * @param  list<array<string, mixed>>  $lineCalcs
     * @param  list<array<string, mixed>>  $minPrices
     * @return array{markup_percent:?float,sample_client_price:?float,sample_our_cost:?float,sample_profit:?float,total_line_profit:?float,note:?string}
     */
    private function pricingHints(?int $buyerId, array $suppliers, array $lines, array $lineCalcs, array $minPrices): array
    {
        $totalProfit = collect($lineCalcs)
            ->pluck('line_profit')
            ->filter(fn ($v) => $v !== null)
            ->sum();

        $firstCalc = collect($lineCalcs)->first(fn ($r) => ($r['unit_profit'] ?? null) !== null);

        $minBits = collect($minPrices)
            ->filter(fn ($p) => ($p['min_cost_price'] ?? null) !== null)
            ->map(fn ($p) => ($p['product'] ?? 'Item').' min cost '.number_format((float) $p['min_cost_price'], 2)
                .($p['min_cost_supplier'] ? ' ('.$p['min_cost_supplier'].')' : ''))
            ->take(3)
            ->implode('; ');

        if ($firstCalc) {
            $note = 'Auto-calc from Markup on OC lines.';
            if ($minBits !== '') {
                $note .= ' Cheapest available: '.$minBits.'.';
            }

            return [
                'markup_percent'      => $firstCalc['markup_percent'],
                'sample_client_price' => $firstCalc['client_price'],
                'sample_our_cost'     => $firstCalc['our_cost'],
                'sample_profit'       => $firstCalc['unit_profit'],
                'total_line_profit'   => $totalProfit > 0 ? round((float) $totalProfit, 2) : null,
                'note'                => $note,
            ];
        }

        if (! $buyerId || $suppliers === [] || $lines === []) {
            return [
                'markup_percent'      => null,
                'sample_client_price' => null,
                'sample_our_cost'     => null,
                'sample_profit'       => null,
                'total_line_profit'   => null,
                'note'                => $minBits !== '' ? 'Cheapest available: '.$minBits.'.' : null,
            ];
        }

        $supplierId = $suppliers[0]['id'];
        $markup = Markup::query()
            ->with('supplier:id,discount_percent')
            ->where('buyer_id', $buyerId)
            ->where('supplier_id', $supplierId)
            ->where('status', 'active')
            ->first();

        $cp = (float) ($lines[0]['cost_price'] ?? $lines[0]['price'] ?? 0);
        if (! $markup || $cp <= 0) {
            return [
                'markup_percent'      => $markup ? (float) $markup->markup_percent : null,
                'sample_client_price' => null,
                'sample_our_cost'     => null,
                'sample_profit'       => null,
                'total_line_profit'   => null,
                'note'                => $markup
                    ? 'Markup rule found — open Markup master for full arithmetic.'
                    : ($minBits !== ''
                        ? 'No Markup yet. Cheapest available: '.$minBits.'.'
                        : 'No active Markup rule for this buyer–supplier pair.'),
            ];
        }

        $client = $markup->clientPrice($cp);
        $cost = $markup->ourCost($cp);
        $profit = $markup->profit($cp);

        return [
            'markup_percent'      => (float) $markup->markup_percent,
            'sample_client_price' => round($client, 2),
            'sample_our_cost'     => round($cost, 2),
            'sample_profit'       => round($profit, 2),
            'total_line_profit'   => null,
            'note'                => 'Sample from first OC line + Markup master (buyer–supplier).'
                .($minBits !== '' ? ' Cheapest available: '.$minBits.'.' : ''),
        ];
    }

    /**
     * PO / Inward proxies — Guru Traders does not keep a ready-stock ledger.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{note:string,rows:list<array<string, mixed>>}
     */
    private function inventorySnapshot(?OrderConfirmation $oc, array $lines): array
    {
        $note = 'No ready-stock warehouse — qty below is from Purchase Orders / Goods Inward for this order’s products.';

        if ($lines === []) {
            return ['note' => $note, 'rows' => []];
        }

        $productIds = collect($lines)->pluck('product_id')->filter()->unique()->values();
        $rows = [];

        if ($productIds->isNotEmpty()) {
            $orderQty = collect($lines)
                ->groupBy('product_id')
                ->map(fn ($group) => (float) $group->sum('qty'));

            $poQuery = PurchaseOrderItem::query()->whereIn('product_id', $productIds);
            if ($oc) {
                $poQuery->whereHas('purchaseOrder', fn ($q) => $q->where('order_confirmation_id', $oc->id));
            }
            $poOrdered = $poQuery
                ->selectRaw('product_id, SUM(qty) as total_qty')
                ->groupBy('product_id')
                ->pluck('total_qty', 'product_id');

            $poItemIds = PurchaseOrderItem::query()
                ->whereIn('product_id', $productIds)
                ->when($oc, fn ($q) => $q->whereHas('purchaseOrder', fn ($p) => $p->where('order_confirmation_id', $oc->id)))
                ->pluck('id');

            $received = InwardEntryItem::query()
                ->when(
                    $poItemIds->isNotEmpty(),
                    fn ($q) => $q->whereIn('purchase_order_item_id', $poItemIds),
                    fn ($q) => $q->whereIn('product_id', $productIds)
                )
                ->selectRaw('product_id, SUM(received_qty) as received_qty, SUM(passed_qty) as passed_qty')
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            // Also “what we have” globally (QC passed) for the same products.
            $globalPassed = InwardEntryItem::query()
                ->whereIn('product_id', $productIds)
                ->selectRaw('product_id, SUM(passed_qty) as passed_qty')
                ->groupBy('product_id')
                ->pluck('passed_qty', 'product_id');

            foreach ($productIds as $productId) {
                $name = collect($lines)->firstWhere('product_id', $productId)['product'] ?? ('Product #'.$productId);
                $ordered = (float) ($poOrdered[$productId] ?? 0);
                $recv = (float) ($received->get($productId)?->received_qty ?? 0);
                $passed = (float) ($received->get($productId)?->passed_qty ?? 0);
                $rows[] = [
                    'product_id'        => (int) $productId,
                    'product'           => $name,
                    'order_qty'         => (float) ($orderQty[$productId] ?? 0),
                    'po_ordered_qty'    => $ordered,
                    'received_qty'      => $recv,
                    'passed_qty'        => $passed,
                    'balance_to_receive'=> max(0, $ordered - $recv),
                    'global_passed_qty' => (float) ($globalPassed[$productId] ?? 0),
                ];
            }

            return ['note' => $note, 'rows' => $rows];
        }

        // Design-no fallback when lines have no product master.
        foreach (collect($lines)->groupBy(fn ($l) => $l['product'] ?? 'Item') as $name => $group) {
            $design = (string) $name;
            $poOrdered = (float) PurchaseOrderItem::query()
                ->where('design_no', $design)
                ->when($oc, fn ($q) => $q->whereHas('purchaseOrder', fn ($p) => $p->where('order_confirmation_id', $oc->id)))
                ->sum('qty');
            $rows[] = [
                'product_id'         => null,
                'product'            => $design,
                'order_qty'          => (float) $group->sum('qty'),
                'po_ordered_qty'     => $poOrdered,
                'received_qty'       => 0.0,
                'passed_qty'         => 0.0,
                'balance_to_receive' => $poOrdered,
                'global_passed_qty'  => 0.0,
            ];
        }

        return ['note' => $note, 'rows' => $rows];
    }

    /**
     * Jobbers linked to this buyer, products on the order, or used as line parties.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function jobbersForOrder(?int $buyerId, ?OrderConfirmation $oc, array $lines): array
    {
        $productIds = collect($lines)->pluck('product_id')->filter()->unique()->values()->all();
        $lineSupplierIds = collect($lines)->pluck('supplier_id')->filter()->unique()->values()->all();
        $byId = collect();

        $remember = function (Supplier $jobber, string $via) use ($byId): void {
            $existing = $byId->get($jobber->id);
            $vias = $existing['via'] ?? [];
            if (! in_array($via, $vias, true)) {
                $vias[] = $via;
            }
            $byId->put($jobber->id, [
                'id'           => $jobber->id,
                'display_code' => $jobber->display_code,
                'company_name' => $jobber->company_name,
                'party_type'   => $jobber->party_type,
                'via'          => $vias,
            ]);
        };

        if ($lineSupplierIds !== []) {
            Supplier::query()->ofParty('jobber')->whereIn('id', $lineSupplierIds)
                ->get()
                ->each(fn (Supplier $j) => $remember($j, 'order line'));
        }

        if ($buyerId) {
            Supplier::query()->ofParty('jobber')
                ->whereHas('buyers', fn ($q) => $q->whereKey($buyerId))
                ->get()
                ->each(fn (Supplier $j) => $remember($j, 'buyer link'));
        }

        if ($productIds !== []) {
            Supplier::query()->ofParty('jobber')
                ->whereHas('products', fn ($q) => $q->whereIn('products.id', $productIds))
                ->get()
                ->each(fn (Supplier $j) => $remember($j, 'product link'));
        }

        // Also any jobber that is the supplier on POs for this OC.
        if ($oc) {
            $poSupplierIds = $oc->purchaseOrders()->pluck('supplier_id')->filter()->unique()->all();
            if ($poSupplierIds !== []) {
                Supplier::query()->ofParty('jobber')->whereIn('id', $poSupplierIds)
                    ->get()
                    ->each(fn (Supplier $j) => $remember($j, 'purchase order'));
            }
        }

        return $byId->values()->map(function (array $row) {
            $row['via_label'] = implode(', ', $row['via']);
            unset($row['via']);

            return $row;
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayment(string $note): array
    {
        $pending = ['Swift / Payment', 'EEFC proof', 'eBRC'];

        return [
            'payment_received' => ['status' => 'pending', 'label' => 'Pending', 'reference' => null, 'name' => 'payment_received'],
            'eefc_upload'      => ['status' => 'pending', 'label' => 'Pending', 'reference' => null, 'name' => 'eefc_upload'],
            'ebrc'             => ['status' => 'pending', 'label' => 'Pending', 'reference' => null, 'name' => 'ebrc'],
            'goods_received'   => ['status' => 'pending', 'label' => 'Pending', 'reference' => null, 'name' => 'goods_received'],
            'realised'         => false,
            'pending_labels'   => $pending,
            'due_date'         => null,
            'due_note'         => $note,
        ];
    }

    private function summaryText(
        ?ExportDocument $document,
        ?OrderConfirmation $oc,
        array $payments,
        array $minPrices,
        array $inventory = [],
        array $jobbers = [],
    ): string {
        $parts = [
            $document?->doc_num,
            $oc?->oc_num ? 'OC '.$oc->oc_num : null,
            $document?->buyer?->company_name ?? $oc?->buyer?->company_name,
        ];

        if ($payments['realised']) {
            $parts[] = 'Payment realised (Swift + eBRC done)';
        } elseif ($payments['pending_labels'] !== []) {
            $parts[] = 'Payment pending: '.implode(', ', $payments['pending_labels']);
        }

        if (! empty($payments['due_date']) && empty($payments['realised'])) {
            $parts[] = 'Due '.$payments['due_date'];
        }

        $cheapest = collect($minPrices)
            ->filter(fn ($p) => ($p['min_cost_price'] ?? null) !== null)
            ->map(fn ($p) => ($p['product'] ?? 'Item').' ≥'.number_format((float) $p['min_cost_price'], 2))
            ->take(2)
            ->implode(', ');

        if ($cheapest !== '') {
            $parts[] = 'Min cost '.$cheapest;
        }

        $invRows = $inventory['rows'] ?? [];
        if ($invRows !== []) {
            $balance = collect($invRows)->sum('balance_to_receive');
            $parts[] = 'Inward balance '.$balance;
        }

        if ($jobbers !== []) {
            $parts[] = count($jobbers).' jobber(s)';
        }

        return collect($parts)->filter()->implode(' · ');
    }
}
