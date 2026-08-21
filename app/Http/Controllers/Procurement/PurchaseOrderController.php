<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Requests\Procurement\UpdatePurchaseOrderRequest;
use App\Models\OrderConfirmation;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Export\OcrOrderContextBuilder;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrders,
        private readonly OcrOrderContextBuilder $orderContext,
    ) {
    }

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:purchase-order.view', only: ['index', 'show']),
            new Middleware('permission:purchase-order.create', only: ['create', 'store']),
            new Middleware('permission:purchase-order.edit', only: ['edit', 'update']),
            new Middleware('permission:purchase-order.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier:id,company_name,display_code', 'orderConfirmation:id,oc_num,buyer_id', 'orderConfirmation.buyer:id,company_name', 'items'])
            ->when(
                $request->filled('supplier_id'),
                fn ($q) => $q->where('supplier_id', $request->integer('supplier_id'))
            )
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString())
            ->sort($request->string('sort')->toString(), $request->string('direction')->toString())
            ->paginate(15)
            ->withQueryString();

        return view('procurement.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'suppliers'      => Supplier::active()->ofParty('supplier')->orderBy('company_name')->get()->pluck('label', 'id'),
            'statuses'       => PurchaseOrder::STATUSES,
            'filters'        => $request->only('search', 'status', 'supplier_id', 'sort', 'direction'),
        ]);
    }

    public function create(): View
    {
        return view('procurement.purchase-orders.create', $this->formData());
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $po = $this->purchaseOrders->create($request->validated());

        return redirect()
            ->route('procurement.purchase-orders.index')
            ->with('success', "Purchase Order \"{$po->po_num}\" created successfully.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'orderConfirmation.buyer', 'orderConfirmation.category', 'supplier.agent',
            'items' => fn ($q) => $q->with(['product', 'colours.sizes', 'sourceItem']),
            'timelineEntries',
            'creator', 'updater',
        ]);

        $orderContext = ['available' => false];
        if ($purchaseOrder->orderConfirmation) {
            $orderContext = $this->orderContext->buildFromOrderConfirmation($purchaseOrder->orderConfirmation);
            $orderContext['module'] = 'purchase_order';
        }

        return view('procurement.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrder,
            'orderContext'  => $orderContext,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['items.product', 'items.colours.sizes', 'timelineEntries']);

        return view('procurement.purchase-orders.edit', $this->formData() + ['purchaseOrder' => $purchaseOrder]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchaseOrders->update($purchaseOrder, $request->validated());

        return redirect()
            ->route('procurement.purchase-orders.index')
            ->with('success', "Purchase Order \"{$purchaseOrder->po_num}\" updated successfully.");
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder->delete();

        return redirect()
            ->route('procurement.purchase-orders.index')
            ->with('success', "Purchase Order \"{$purchaseOrder->po_num}\" deleted successfully.");
    }

    /**
     * Dropdown sources for the create/edit forms.
     *
     * A standalone "+ New PO" is a manual document — Contract and Supplier
     * fix the buyer/category shown for context and seed the Delivery/
     * Packing defaults, but items are typed in the same dynamic grid as
     * Inquiry/OC rather than auto-pulled. Pulling specific priced,
     * sized items across is what "Raise PO for Selected" on the OC screen
     * itself already does more precisely — see
     * OrderConfirmationService::raisePurchaseOrders().
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'orderConfirmations' => OrderConfirmation::query()
                ->where('status', 'confirmed')
                ->with(['buyer:id,company_name,display_code', 'format.units', 'format.columns'])
                ->orderByDesc('id')
                ->get(['id', 'oc_num', 'buyer_id', 'category_id', 'document_format_id', 'delivery_details', 'packing_details']),

            'suppliers' => Supplier::active()->ofParty('supplier')->orderBy('company_name')->get()->pluck('label', 'id'),
            'statuses'  => ['draft' => 'Draft', 'raised' => 'Raised'],
        ];
    }
}
