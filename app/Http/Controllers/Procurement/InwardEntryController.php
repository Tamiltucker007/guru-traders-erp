<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\ApproveInwardEntryRequest;
use App\Http\Requests\Procurement\StoreInwardEntryRequest;
use App\Http\Requests\Procurement\UpdateInwardEntryRequest;
use App\Models\InwardEntry;
use App\Models\InwardEntryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Procurement\InwardEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class InwardEntryController extends Controller implements HasMiddleware
{
    public function __construct(private readonly InwardEntryService $inwardEntries)
    {
    }

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inward-entry.view', only: ['index', 'show', 'poDetails']),
            new Middleware('permission:inward-entry.create', only: ['create', 'store']),
            new Middleware('permission:inward-entry.edit', only: ['edit', 'update']),
            new Middleware('permission:inward-entry.delete', only: ['destroy']),
            new Middleware('permission:inward-entry.approve', only: ['approve']),
        ];
    }

    public function index(Request $request): View
    {
        $inwardEntries = InwardEntry::query()
            ->with(['supplier:id,company_name,display_code', 'purchaseOrder:id,po_num,status', 'items'])
            ->when(
                $request->filled('supplier_id'),
                fn ($q) => $q->where('supplier_id', $request->integer('supplier_id'))
            )
            ->when(
                $request->filled('purchase_order_id'),
                fn ($q) => $q->where('purchase_order_id', $request->integer('purchase_order_id'))
            )
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString())
            ->sort($request->string('sort')->toString(), $request->string('direction')->toString())
            ->paginate(15)
            ->withQueryString();

        return view('procurement.inward-entries.index', [
            'inwardEntries' => $inwardEntries,
            'suppliers'     => Supplier::active()->orderBy('company_name')->get()->pluck('label', 'id'),
            'purchaseOrders'=> PurchaseOrder::query()->orderByDesc('id')->get()->pluck('po_num', 'id'),
            'statuses'      => InwardEntry::STATUSES,
            'filters'       => $request->only('search', 'status', 'supplier_id', 'purchase_order_id', 'sort', 'direction'),
        ]);
    }

    public function create(Request $request): View
    {
        $selectedPoId = $request->integer('purchase_order_id') ?: null;

        return view('procurement.inward-entries.create', $this->formData() + [
            'selectedPoId' => $selectedPoId,
        ]);
    }

    public function store(StoreInwardEntryRequest $request): RedirectResponse
    {
        $inward = $this->inwardEntries->create($request->validated());

        return redirect()
            ->route('procurement.inward-entries.index')
            ->with('success', "Goods Inward receipt \"{$inward->inward_no}\" created successfully.");
    }

    public function show(InwardEntry $inwardEntry): View
    {
        return view('procurement.inward-entries.show', [
            'inwardEntry' => $inwardEntry->load([
                'purchaseOrder.supplier',
                'supplier',
                'items.product',
                'items.purchaseOrderItem',
                'qcInspector',
                'creator',
                'updater',
            ]),
        ]);
    }

    public function edit(InwardEntry $inwardEntry): View
    {
        $inwardEntry->load(['items.product', 'items.purchaseOrderItem', 'purchaseOrder.supplier']);

        return view('procurement.inward-entries.edit', $this->formData() + [
            'inwardEntry' => $inwardEntry,
        ]);
    }

    public function update(UpdateInwardEntryRequest $request, InwardEntry $inwardEntry): RedirectResponse
    {
        $this->inwardEntries->update($inwardEntry, $request->validated());

        return redirect()
            ->route('procurement.inward-entries.index')
            ->with('success', "Goods Inward receipt \"{$inwardEntry->inward_no}\" updated successfully.");
    }

    public function approve(ApproveInwardEntryRequest $request, InwardEntry $inwardEntry): RedirectResponse
    {
        $this->inwardEntries->approve($inwardEntry, $request->validated(), $request->user()->id);

        $statusText = ucfirst($inwardEntry->status);

        return redirect()
            ->route('procurement.inward-entries.show', $inwardEntry)
            ->with('success', "Goods Inward receipt \"{$inwardEntry->inward_no}\" inspection completed: {$statusText}.");
    }

    public function destroy(InwardEntry $inwardEntry): RedirectResponse
    {
        $this->inwardEntries->delete($inwardEntry);

        return redirect()
            ->route('procurement.inward-entries.index')
            ->with('success', "Goods Inward receipt \"{$inwardEntry->inward_no}\" deleted successfully.");
    }

    /**
     * AJAX endpoint returning Purchase Order details & item quantities for dynamic form populating.
     */
    public function poDetails(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['supplier:id,company_name,display_code', 'items.product:id,name,code']);

        $items = $purchaseOrder->items->map(function ($poItem) use ($purchaseOrder) {
            $prevReceived = (int) InwardEntryItem::query()
                ->where('purchase_order_item_id', $poItem->id)
                ->sum('received_qty');

            $orderedQty = (int) $poItem->qty;
            $balanceQty = max(0, $orderedQty - $prevReceived);

            return [
                'purchase_order_item_id' => $poItem->id,
                'product_id'             => $poItem->product_id,
                'product_name'           => $poItem->product?->name ?? 'Custom Item',
                'product_code'           => $poItem->product?->code ?? $poItem->design_no,
                'description'            => $poItem->description,
                'unit'                   => $poItem->unit ?? 'pcs',
                'ordered_qty'            => $orderedQty,
                'previously_received_qty'=> $prevReceived,
                'balance_qty'            => $balanceQty,
            ];
        });

        return response()->json([
            'purchase_order_id' => $purchaseOrder->id,
            'po_num'            => $purchaseOrder->po_num,
            'supplier_id'       => $purchaseOrder->supplier_id,
            'supplier_name'     => $purchaseOrder->supplier?->company_name,
            'po_date'           => $purchaseOrder->po_date?->format('Y-m-d'),
            'items'             => $items,
        ]);
    }

    private function formData(): array
    {
        return [
            'purchaseOrders' => PurchaseOrder::query()
                ->whereIn('status', ['raised', 'partial'])
                ->with('supplier:id,company_name,display_code')
                ->orderByDesc('id')
                ->get(),

            'suppliers' => Supplier::active()->orderBy('company_name')->get()->pluck('label', 'id'),
            'statuses'  => InwardEntry::STATUSES,
        ];
    }
}
