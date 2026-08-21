<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreOrderConfirmationRequest;
use App\Http\Requests\Sales\UpdateOrderConfirmationRequest;
use App\Models\Agent;
use App\Models\Buyer;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DocumentFormat;
use App\Models\FobValue;
use App\Models\Inquiry;
use App\Models\OrderConfirmation;
use App\Services\Export\OcrOrderContextBuilder;
use App\Services\Sales\OrderConfirmationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use RuntimeException;

class OrderConfirmationController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly OrderConfirmationService $confirmations,
        private readonly OcrOrderContextBuilder $orderContext,
    ) {
    }

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:order-confirmation.view', only: ['index', 'show']),
            new Middleware('permission:order-confirmation.create', only: ['create', 'store', 'convertFromInquiry']),
            new Middleware('permission:order-confirmation.edit', only: ['edit', 'update']),
            new Middleware('permission:order-confirmation.delete', only: ['destroy']),
            new Middleware('permission:order-confirmation.approve', only: ['raisePurchaseOrders']),
        ];
    }

    public function index(Request $request): View
    {
        $confirmations = OrderConfirmation::query()
            ->with(['buyer:id,company_name,display_code', 'category:id,name', 'items'])
            ->when(
                $request->filled('buyer_id'),
                fn ($q) => $q->where('buyer_id', $request->integer('buyer_id'))
            )
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString())
            ->sort($request->string('sort')->toString(), $request->string('direction')->toString())
            ->paginate(15)
            ->withQueryString();

        return view('sales.order-confirmations.index', [
            'confirmations' => $confirmations,
            'buyers'        => Buyer::active()->orderBy('company_name')->get()->pluck('label', 'id'),
            'statuses'      => OrderConfirmation::STATUSES,
            'filters'       => $request->only('search', 'status', 'buyer_id', 'sort', 'direction'),
        ]);
    }

    public function create(): View
    {
        return view('sales.order-confirmations.create', $this->formData());
    }

    public function store(StoreOrderConfirmationRequest $request): RedirectResponse
    {
        $oc = $this->confirmations->create($request->validated());

        return redirect()
            ->route('sales.order-confirmations.index')
            ->with('success', "Order Confirmation \"{$oc->oc_num}\" created successfully.");
    }

    public function show(OrderConfirmation $orderConfirmation): View
    {
        $orderConfirmation->load([
            'buyer', 'category', 'format', 'agent', 'currency', 'sourceInquiry',
            'items' => fn ($q) => $q->with(['product', 'supplier', 'fobValue', 'colours.sizes', 'purchaseOrder', 'exportDocument']),
            'purchaseOrders',
            'exportDocuments',
            'creator', 'updater',
        ]);

        return view('sales.order-confirmations.show', [
            'orderConfirmation' => $orderConfirmation,
            'orderContext' => $this->orderContext->buildFromOrderConfirmation($orderConfirmation),
        ]);
    }

    public function edit(OrderConfirmation $orderConfirmation): View
    {
        $orderConfirmation->load(['items' => fn ($q) => $q->whereNull('purchase_order_id')->with('colours.sizes')]);

        return view('sales.order-confirmations.edit', $this->formData() + ['orderConfirmation' => $orderConfirmation]);
    }

    public function update(UpdateOrderConfirmationRequest $request, OrderConfirmation $orderConfirmation): RedirectResponse
    {
        $this->confirmations->update($orderConfirmation, $request->validated());

        return redirect()
            ->route('sales.order-confirmations.index')
            ->with('success', "Order Confirmation \"{$orderConfirmation->oc_num}\" updated successfully.");
    }

    public function destroy(OrderConfirmation $orderConfirmation): RedirectResponse
    {
        $orderConfirmation->delete();

        return redirect()
            ->route('sales.order-confirmations.index')
            ->with('success', "Order Confirmation \"{$orderConfirmation->oc_num}\" deleted successfully.");
    }

    /**
     * Reached from Inquiry's own "Convert to OC" button — same route name
     * (`sales.inquiries.convert-to-oc`) the Inquiry screen already posts to,
     * now pointed at this controller instead of just flipping item status.
     */
    public function convertFromInquiry(Inquiry $inquiry): RedirectResponse
    {
        try {
            $oc = $this->confirmations->convertFromInquiry($inquiry);
        } catch (RuntimeException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()
            ->route('sales.order-confirmations.show', $oc)
            ->with('success', "Order Confirmation \"{$oc->oc_num}\" created from inquiry \"{$inquiry->inquiry_no}\".");
    }

    /**
     * "Raise PO for Selected" — groups the checked items by their own
     * supplier and creates one PO per group.
     */
    public function raisePurchaseOrders(Request $request, OrderConfirmation $orderConfirmation): RedirectResponse
    {
        if ($orderConfirmation->status !== 'confirmed') {
            return back()->with('warning', 'The buyer has not confirmed this OC yet — mark it Confirmed before raising a PO.');
        }

        $itemIds = array_map('intval', (array) $request->input('item_ids', []));

        if (empty($itemIds)) {
            return back()->with('warning', 'Select at least one item to raise a PO.');
        }

        $purchaseOrders = $this->confirmations->raisePurchaseOrders($orderConfirmation, $itemIds);

        if ($purchaseOrders->isEmpty()) {
            return back()->with('warning', 'Nothing to raise — the selected items have no supplier set, or are already on a PO.');
        }

        $names = $purchaseOrders->pluck('po_num')->implode(', ');

        return redirect()
            ->route('sales.order-confirmations.show', $orderConfirmation)
            ->with('success', $purchaseOrders->count() > 1
                ? "{$purchaseOrders->count()} Purchase Orders raised: {$names}."
                : "Purchase Order \"{$names}\" raised.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'buyers' => Buyer::active()->orderBy('company_name')->get([
                'id', 'company_name', 'display_code', 'agent_id',
                'agent_commission_type', 'agent_commission_value', 'currency_id',
            ])->load('categories:id'),

            'categories' => Category::active()->orderBy('name')->pluck('name', 'id'),
            'agents'     => Agent::active()->ofType('buyer')->orderBy('name')->get()->pluck('label', 'id'),

            'formats' => DocumentFormat::active()->with(['units', 'columns', 'categories:id'])
                ->orderBy('name')->get(),

            'fobValues'  => FobValue::active()->orderBy('name')->pluck('name', 'id'),
            'currencies' => Currency::active()->orderBy('iso_code')->get()->pluck('label', 'id'),
            'statuses'   => OrderConfirmation::STATUSES,
            'modes'      => OrderConfirmation::MODES,
        ];
    }
}
