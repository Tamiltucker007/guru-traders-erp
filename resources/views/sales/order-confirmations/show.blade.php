<x-app-layout>
    <x-slot name="header">Order Confirmation {{ $orderConfirmation->oc_num }}</x-slot>

    <x-ui.card :title="$orderConfirmation->oc_num" variant="primary">
        <x-slot name="actions">
            @can('order-confirmation.edit')
                <a href="{{ route('sales.order-confirmations.edit', $orderConfirmation) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
            <a href="{{ route('sales.order-confirmations.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <x-order-context-modal :order-context="$orderContext ?? ['available' => false]" modal-id="ocOrderContextModal" />
        </x-slot>

        @if($orderConfirmation->source_inquiry_id)
            <div class="alert alert-info py-2 small mb-3">
                <i class="bi bi-arrow-return-right me-1"></i>
                Converted from Inquiry
                <a href="{{ route('sales.inquiries.show', $orderConfirmation->source_inquiry_id) }}" class="fw-semibold">
                    {{ $orderConfirmation->sourceInquiry?->inquiry_no }}
                </a>
                — item data, sizes, colours &amp; costing pre-filled.
            </div>
        @elseif($orderConfirmation->isDirect())
            <div class="alert alert-secondary py-2 small mb-3">
                <i class="bi bi-file-earmark-text me-1"></i>
                Direct Buyer Contract — no OC document sent, this contract number is the anchor for POs raised against it.
            </div>
        @endif

        <dl class="row mb-4">
            <dt class="col-sm-3 text-body-secondary fw-normal">Status</dt>
            <dd class="col-sm-9"><span class="badge text-bg-{{ $orderConfirmation->statusColor() }}">{{ $orderConfirmation->statusLabel() }}</span></dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">OC Date</dt>
            <dd class="col-sm-9">{{ $orderConfirmation->oc_date?->format('d M Y') }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Buyer's Ref</dt>
            <dd class="col-sm-9">{{ $orderConfirmation->buyer_ref ?: '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Buyer</dt>
            <dd class="col-sm-9">{{ $orderConfirmation->buyer?->company_name }} ({{ $orderConfirmation->buyer?->display_code }})</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Category / Order Format</dt>
            <dd class="col-sm-9">{{ $orderConfirmation->category?->name ?? '—' }} / {{ $orderConfirmation->format?->name ?? '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Agent</dt>
            <dd class="col-sm-9">{{ $orderConfirmation->agent?->name ?? '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Currency / Incoterm</dt>
            <dd class="col-sm-9">{{ $orderConfirmation->currency?->iso_code ?? '—' }} / {{ $orderConfirmation->incoterm ?? '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Shipment</dt>
            <dd class="col-sm-9">
                {{ $orderConfirmation->ship_method ?? '—' }}
                @if($orderConfirmation->shipment_date) &middot; {{ $orderConfirmation->shipment_date }} @endif
                @if($orderConfirmation->pol) &middot; POL: {{ $orderConfirmation->pol }} @endif
                @if($orderConfirmation->pod) &middot; POD: {{ $orderConfirmation->pod }} @endif
            </dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Payment Terms</dt>
            <dd class="col-sm-9">{{ $orderConfirmation->payment_terms ?? '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Remarks</dt>
            <dd class="col-sm-9">{{ $orderConfirmation->remarks ?: '—' }}</dd>
        </dl>

        @php
            // Raising a PO or an Export Document only makes sense once the
            // buyer has actually confirmed the order — enforced again
            // server-side in OrderConfirmationController::raisePurchaseOrders()
            // and ExportDocumentController::raiseFromOrderConfirmation().
            $canRaise = auth()->user()->can('order-confirmation.approve') && $orderConfirmation->status === 'confirmed';
            $canShip = auth()->user()->can('export-document.create') && $orderConfirmation->status === 'confirmed';
        @endphp

        @if($orderConfirmation->items->isNotEmpty())
            <h6 class="fw-semibold mb-2">Items</h6>

            {{-- Empty, sibling forms — every checkbox/submit below points at
                 one of these via the form="" attribute rather than being
                 nested inside it, since the two selections (Raise PO,
                 Raise Export Document) share the same item table and a
                 <form> cannot nest inside another. --}}
            @if($canRaise)
                <form action="{{ route('sales.order-confirmations.raise-purchase-orders', $orderConfirmation) }}" method="POST" id="raise-po-form">@csrf</form>
            @endif
            @if($canShip)
                <form action="{{ route('sales.order-confirmations.raise-export-document', $orderConfirmation) }}" method="POST" id="raise-export-form">@csrf</form>
            @endif

            <div class="table-responsive mb-2">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            @if($canRaise)
                                <th style="width:32px" title="Raise PO"><i class="bi bi-cart-check"></i></th>
                            @endif
                            @if($canShip)
                                <th style="width:32px" title="Raise Export Document"><i class="bi bi-box-seam"></i></th>
                            @endif
                            <th>#</th>
                            <th>Design No.</th>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Colour / Size</th>
                            <th>Unit</th>
                            <th class="text-end">FOB</th>
                            <th class="text-end text-body-secondary">Cost Price</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Amount</th>
                            <th>PO</th>
                            <th>Export Doc</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orderConfirmation->items as $item)
                            <tr>
                                @if($canRaise)
                                    <td>
                                        @if($item->isRaised())
                                            <i class="bi bi-check-circle-fill text-success" title="Already raised"></i>
                                        @elseif($item->supplier_id)
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="form-check-input" form="raise-po-form">
                                        @else
                                            <span class="text-body-secondary" data-bs-toggle="tooltip" title="No supplier set">—</span>
                                        @endif
                                    </td>
                                @endif
                                @if($canShip)
                                    <td>
                                        @if($item->isShipped())
                                            <i class="bi bi-check-circle-fill text-success" title="Already on an Export Document"></i>
                                        @else
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="form-check-input" form="raise-export-form">
                                        @endif
                                    </td>
                                @endif
                                <td>{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $item->design_no ?: '—' }}</td>
                                <td>{{ $item->product?->name ?? '—' }}</td>
                                <td>{{ $item->supplier?->company_name ?? '—' }}</td>
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
                                <td class="text-end">{{ $item->price !== null ? number_format((float) $item->price, 2) : '—' }}</td>
                                <td class="text-end text-body-secondary">{{ $item->cost_price !== null ? number_format((float) $item->cost_price, 2) : '—' }}</td>
                                <td class="text-end">{{ $item->qty }}</td>
                                <td class="text-end">{{ number_format((float) $item->amount, 2) }}</td>
                                <td>
                                    @if($item->purchaseOrder)
                                        <a href="{{ route('procurement.purchase-orders.show', $item->purchaseOrder) }}" class="small">
                                            {{ $item->purchaseOrder->po_num }}
                                        </a>
                                    @else
                                        <span class="text-body-secondary small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->exportDocument)
                                        <a href="{{ route('export.documents.show', $item->exportDocument) }}" class="small">
                                            {{ $item->exportDocument->doc_num }}
                                        </a>
                                    @else
                                        <span class="text-body-secondary small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold table-light">
                            <td colspan="{{ ($canRaise ? 1 : 0) + ($canShip ? 1 : 0) + 8 }}" class="text-end">Total</td>
                            <td class="text-end">{{ number_format($orderConfirmation->totalAmount(), 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
                @if($canRaise)
                    <div>
                        <button type="submit" form="raise-po-form" class="btn btn-sm btn-success">
                            <i class="bi bi-arrow-right-circle me-1"></i> Raise PO for Selected
                        </button>
                        <div class="form-text">Grouped by supplier — one PO per supplier.</div>
                    </div>
                @endif
                @if($canShip)
                    <div>
                        <button type="submit" form="raise-export-form" class="btn btn-sm btn-info">
                            <i class="bi bi-box-seam me-1"></i> Raise Export Document for Selected
                        </button>
                        <div class="form-text">Creates one Export Document with the full document checklist.</div>
                    </div>
                @endif
            </div>
            @if(! $canRaise && ! $canShip && auth()->user()->can('order-confirmation.approve') && $orderConfirmation->status !== 'confirmed')
                <p class="text-body-secondary small mb-4">Mark the OC Confirmed before raising a PO or Export Document.</p>
            @endif
        @else
            <p class="text-body-secondary small">No items — {{ $orderConfirmation->isDirect() ? 'items are entered at PO stage for a direct contract.' : 'add items in Edit.' }}</p>
        @endif

        @if($orderConfirmation->purchaseOrders->isNotEmpty())
            <h6 class="fw-semibold mb-2">Purchase Orders Raised</h6>
            <div class="d-flex flex-wrap gap-2 mb-4">
                @foreach($orderConfirmation->purchaseOrders as $po)
                    <a href="{{ route('procurement.purchase-orders.show', $po) }}" class="badge text-bg-{{ $po->statusColor() }} text-decoration-none">
                        {{ $po->po_num }} — {{ $po->supplier?->company_name }} ({{ $po->statusLabel() }})
                    </a>
                @endforeach
            </div>
        @endif

        @if($orderConfirmation->exportDocuments->isNotEmpty())
            <h6 class="fw-semibold mb-2">Export Documents Raised</h6>
            <div class="d-flex flex-wrap gap-2 mb-4">
                @foreach($orderConfirmation->exportDocuments as $doc)
                    <a href="{{ route('export.documents.show', $doc) }}" class="badge text-bg-{{ $doc->statusColor() }} text-decoration-none">
                        {{ $doc->doc_num }} ({{ $doc->statusLabel() }}, {{ $doc->checklistProgress() }})
                    </a>
                @endforeach
            </div>
        @endif

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <h6 class="fw-semibold mb-2">Delivery details</h6>
                <div class="border rounded p-3 bg-body-tertiary small" style="min-height:5rem">
                    {{ $orderConfirmation->delivery_details ?: 'None recorded.' }}
                </div>
            </div>
            <div class="col-lg-6">
                <h6 class="fw-semibold mb-2">Packing details</h6>
                <div class="border rounded p-3 bg-body-tertiary small" style="min-height:5rem">
                    {{ $orderConfirmation->packing_details ?: 'None recorded.' }}
                </div>
            </div>
        </div>

        <dl class="row mb-0 mt-4 pt-3 border-top">
            <dt class="col-sm-3 text-body-secondary fw-normal">Created</dt>
            <dd class="col-sm-9 text-body-secondary small">
                {{ $orderConfirmation->created_at?->format('d M Y, H:i') }}
                @if($orderConfirmation->creator) by {{ $orderConfirmation->creator->name }} @endif
            </dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Last updated</dt>
            <dd class="col-sm-9 text-body-secondary small">
                {{ $orderConfirmation->updated_at?->format('d M Y, H:i') }}
                @if($orderConfirmation->updater) by {{ $orderConfirmation->updater->name }} @endif
            </dd>
        </dl>
    </x-ui.card>
</x-app-layout>
