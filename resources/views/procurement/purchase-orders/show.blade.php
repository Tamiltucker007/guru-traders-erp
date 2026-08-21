<x-app-layout>
    <x-slot name="header">Purchase Order {{ $purchaseOrder->po_num }}</x-slot>

    <x-ui.card :title="$purchaseOrder->po_num" variant="primary">
        <x-slot name="actions">
            @can('purchase-order.edit')
                <a href="{{ route('procurement.purchase-orders.edit', $purchaseOrder) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
            <a href="{{ route('procurement.purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <x-order-context-modal :order-context="$orderContext ?? ['available' => false]" modal-id="poOrderContextModal" />
        </x-slot>

        <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-arrow-return-right me-1"></i>
            From OC
            <a href="{{ route('sales.order-confirmations.show', $purchaseOrder->order_confirmation_id) }}" class="fw-semibold">
                {{ $purchaseOrder->orderConfirmation?->oc_num }}
            </a>
            — items, sizes &amp; colours carried across, ₹ cost from inquiry costing.
        </div>

        <dl class="row mb-4">
            <dt class="col-sm-3 text-body-secondary fw-normal">Status</dt>
            <dd class="col-sm-9"><span class="badge text-bg-{{ $purchaseOrder->statusColor() }}">{{ $purchaseOrder->statusLabel() }}</span></dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">PO Date</dt>
            <dd class="col-sm-9">{{ $purchaseOrder->po_date?->format('d M Y') }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Buyer</dt>
            <dd class="col-sm-9">{{ $purchaseOrder->orderConfirmation?->buyer?->company_name ?? '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Category</dt>
            <dd class="col-sm-9">{{ $purchaseOrder->orderConfirmation?->category?->name ?? '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Supplier</dt>
            <dd class="col-sm-9">{{ $purchaseOrder->supplier?->company_name }} ({{ $purchaseOrder->supplier?->display_code }})</dd>

            {{-- Change request #6 — jobwork Agent Commission, calculated from
                 the supplier's own negotiated rate (per piece or %). Blank
                 rather than "—" when there is no agent at all — most POs
                 have none, and a row that never applies is noise. --}}
            @if($purchaseOrder->supplier?->agent_id)
                <dt class="col-sm-3 text-body-secondary fw-normal">Agent</dt>
                <dd class="col-sm-9">{{ $purchaseOrder->supplier->agent?->label ?: '—' }}</dd>

                <dt class="col-sm-3 text-body-secondary fw-normal">Agent Commission</dt>
                <dd class="col-sm-9">{{ $purchaseOrder->agentCommissionAmountLabel() ?: '—' }}</dd>
            @endif

            <dt class="col-sm-3 text-body-secondary fw-normal">Dispatch Date</dt>
            <dd class="col-sm-9">{{ $purchaseOrder->dispatch_date?->format('d M Y') ?? '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Remarks</dt>
            <dd class="col-sm-9">{{ $purchaseOrder->remarks ?: '—' }}</dd>
        </dl>

        <h6 class="fw-semibold mb-2">PO Items</h6>
        <p class="text-body-secondary small">₹ price ← Inquiry costing · OC FOB shown for reference · HSN ← Product Master</p>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Design No.</th>
                        <th>Product / HSN</th>
                        <th>Colour / Size</th>
                        <th>Unit</th>
                        <th class="text-end">₹ / Unit</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">OC FOB</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrder->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="fw-semibold">{{ $item->design_no ?: '—' }}</td>
                            <td>
                                {{ $item->product?->name ?? '—' }}
                                @if($item->product?->hsn_code)
                                    <div class="small text-body-secondary">{{ $item->product->hsn_code }}</div>
                                @endif
                            </td>
                            <td>
                                @forelse($item->colours as $colour)
                                    <div class="small mb-1">
                                        @if($colour->colour)<span class="badge text-bg-light border me-1">{{ $colour->colour }}</span>@endif
                                        @forelse($colour->sizes as $size)
                                            <span class="text-body-secondary">{{ $size->size }}:{{ $size->qty }}</span>@if(! $loop->last), @endif
                                        @empty
                                            <span class="text-body-secondary">—</span>
                                        @endforelse
                                    </div>
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td>{{ $item->unit ?? '—' }}</td>
                            <td class="text-end">{{ $item->cost_price !== null ? number_format((float) $item->cost_price, 2) : '—' }}</td>
                            <td class="text-end">{{ $item->qty }}</td>
                            <td class="text-end">{{ number_format((float) $item->amount, 2) }}</td>
                            <td class="text-end text-body-secondary">
                                {{ $item->sourceItem?->price !== null ? number_format((float) $item->sourceItem->price, 2) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="9" icon="bi-box-seam" title="No items" />
                    @endforelse
                </tbody>
                @if($purchaseOrder->items->isNotEmpty())
                    <tfoot>
                        <tr class="fw-semibold table-light">
                            <td colspan="6" class="text-end">Total</td>
                            <td class="text-end">{{ $purchaseOrder->items->sum('qty') }}</td>
                            <td class="text-end">{{ number_format($purchaseOrder->totalAmount(), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <h6 class="fw-semibold mb-2">Delivery Timeline</h6>
        @forelse($purchaseOrder->timelineEntries as $entry)
            <div class="d-flex gap-3 mb-2 small">
                <div class="text-body-secondary" style="min-width:6.5rem">{{ $entry->entry_date?->format('d M Y') }}</div>
                <div class="flex-grow-1">{{ $entry->note }}</div>
                @if($entry->qty !== null)<div class="text-body-secondary">{{ $entry->qty }} pcs</div>@endif
            </div>
        @empty
            <p class="text-body-secondary small">No timeline entries recorded yet — Goods Inward will populate this once that module exists.</p>
        @endforelse

        <div class="row g-4 my-4">
            <div class="col-lg-6">
                <h6 class="fw-semibold mb-2">Delivery details</h6>
                <div class="border rounded p-3 bg-body-tertiary small" style="min-height:5rem">
                    {{ $purchaseOrder->delivery_details ?: 'None recorded.' }}
                </div>
            </div>
            <div class="col-lg-6">
                <h6 class="fw-semibold mb-2">Packing details</h6>
                <div class="border rounded p-3 bg-body-tertiary small" style="min-height:5rem">
                    {{ $purchaseOrder->packing_details ?: 'None recorded.' }}
                </div>
            </div>
        </div>

        <h6 class="fw-semibold mb-2">Module Connections</h6>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <span class="badge text-bg-light border fw-normal">OC Module — contract no. links, items &amp; sizes inherited</span>
            <span class="badge text-bg-light border fw-normal">Inquiry Module — ₹ cost price carried through</span>
            <span class="badge text-bg-light border fw-normal">Product Master — HSN, drawback %, RoSCTL %</span>
            <span class="badge text-bg-light border fw-normal">Goods Inward — not built yet</span>
        </div>

        <dl class="row mb-0 mt-4 pt-3 border-top">
            <dt class="col-sm-3 text-body-secondary fw-normal">Created</dt>
            <dd class="col-sm-9 text-body-secondary small">
                {{ $purchaseOrder->created_at?->format('d M Y, H:i') }}
                @if($purchaseOrder->creator) by {{ $purchaseOrder->creator->name }} @endif
            </dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Last updated</dt>
            <dd class="col-sm-9 text-body-secondary small">
                {{ $purchaseOrder->updated_at?->format('d M Y, H:i') }}
                @if($purchaseOrder->updater) by {{ $purchaseOrder->updater->name }} @endif
            </dd>
        </dl>
    </x-ui.card>
</x-app-layout>
