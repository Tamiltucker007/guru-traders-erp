@php
    $isEdit = isset($inwardEntry);
@endphp

<x-ui.form-section title="Header Details" icon="bi-file-text">
    <div class="row g-3">
        <div class="col-md-6">
            @if($isEdit)
                {{-- Read-only and not submitted, same pattern as PO/OC number displays. --}}
                <div class="row form-line">
                    <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">Inward Number</label>
                    <div class="col-sm-8 col-lg-9">
                        <input type="text" class="form-control font-monospace readonly-bg" value="{{ $inwardEntry->inward_no }}" readonly>
                    </div>
                </div>
                <input type="hidden" name="purchase_order_id" value="{{ $inwardEntry->purchase_order_id }}">
                <div class="row form-line">
                    <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">Purchase Order</label>
                    <div class="col-sm-8 col-lg-9">
                        <input type="text" class="form-control font-monospace readonly-bg" value="{{ $inwardEntry->purchaseOrder?->po_num }} — {{ $inwardEntry->supplier?->company_name }}" readonly>
                    </div>
                </div>
            @else
                <div class="row form-line">
                    <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">Inward Number</label>
                    <div class="col-sm-8 col-lg-9">
                        <input type="text" class="form-control font-monospace readonly-bg" value="Auto-generated (GT/INW/xxx)" readonly>
                    </div>
                </div>

                <x-ui.select name="purchase_order_id" label="Purchase Order" required horizontal id="po_select"
                    placeholder="Select Raised / Partial PO..."
                    :options="$purchaseOrders->mapWithKeys(fn ($po) => [$po->id => $po->po_num.' — '.$po->supplier?->company_name.' ('.$po->statusLabel().')'])"
                    :selected="$selectedPoId ?? null" />
            @endif
        </div>

        <div class="col-md-6">
            <x-ui.field name="inward_date" label="Inward Date" required horizontal type="date"
                :value="$isEdit ? $inwardEntry->inward_date?->format('Y-m-d') : date('Y-m-d')" />

            <x-ui.field name="challan_no" label="Challan / DC No." horizontal placeholder="Delivery Challan / Invoice No."
                :value="$isEdit ? $inwardEntry->challan_no : ''" />

            <x-ui.field name="challan_date" label="Challan Date" horizontal type="date"
                :value="$isEdit ? $inwardEntry->challan_date?->format('Y-m-d') : ''" />
        </div>

        <div class="col-12">
            <x-ui.textarea name="remarks" label="Receipt Remarks" :value="old('remarks', $isEdit ? $inwardEntry->remarks : '')" horizontal rows="2" placeholder="Notes regarding delivery condition, driver, packaging..." />
        </div>
    </div>
</x-ui.form-section>

<x-ui.form-section title="Received Items" icon="bi-box-seam">
    <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0" id="inward_items_table">
            <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Product / Description</th>
                    <th style="width:90px">Unit</th>
                    <th class="text-end" style="width:110px">Ordered Qty</th>
                    @if(!$isEdit)
                        <th class="text-end" style="width:110px">Prev. Received</th>
                        <th class="text-end" style="width:110px">Balance Qty</th>
                    @endif
                    <th class="text-end" style="width:130px">Received Qty</th>
                    @if($isEdit)
                        <th class="text-end" style="width:120px">Passed Qty</th>
                        <th class="text-end" style="width:120px">Rejected Qty</th>
                    @endif
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody id="inward_items_tbody">
                @if($isEdit)
                    @foreach($inwardEntry->items as $index => $item)
                        <tr>
                            <td class="text-center fw-semibold text-body-secondary">{{ $index + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->product?->name ?? $item->description }}</div>
                                <div class="small text-body-secondary">{{ $item->product?->code }}</div>
                                <input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->purchase_order_item_id }}">
                                <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                                <input type="hidden" name="items[{{ $index }}][description]" value="{{ $item->description }}">
                                <input type="hidden" name="items[{{ $index }}][unit]" value="{{ $item->unit }}">
                                <input type="hidden" name="items[{{ $index }}][ordered_qty]" value="{{ $item->ordered_qty }}">
                            </td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-end font-monospace">{{ number_format($item->ordered_qty) }}</td>
                            <td>
                                <input type="number" name="items[{{ $index }}][received_qty]"
                                       class="form-control form-control-sm text-end qty-received" min="0"
                                       value="{{ old("items.{$index}.received_qty", $item->received_qty) }}">
                            </td>
                            <td>
                                <input type="number" name="items[{{ $index }}][passed_qty]"
                                       class="form-control form-control-sm text-end qty-passed text-success" min="0"
                                       value="{{ old("items.{$index}.passed_qty", $item->passed_qty) }}">
                            </td>
                            <td>
                                <input type="number" name="items[{{ $index }}][rejected_qty]"
                                       class="form-control form-control-sm text-end qty-rejected text-danger" min="0"
                                       value="{{ old("items.{$index}.rejected_qty", $item->rejected_qty) }}">
                            </td>
                            <td>
                                <input type="text" name="items[{{ $index }}][remarks]"
                                       class="form-control form-control-sm" placeholder="Line remarks"
                                       value="{{ old("items.{$index}.remarks", $item->remarks) }}">
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="8" class="text-center text-body-secondary py-4">
                            Select a Purchase Order above to load items.
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</x-ui.form-section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const poSelect = document.getElementById('po_select');
    const tbody = document.getElementById('inward_items_tbody');

    if (poSelect) {
        poSelect.addEventListener('change', function() {
            const poId = this.value;
            if (!poId) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-body-secondary py-4">Select a Purchase Order above to load items.</td></tr>`;
                return;
            }

            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>Loading PO items...</td></tr>`;

            fetch(`/procurement/inward-entries/po-details/${poId}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.items || data.items.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-warning py-4">No items found for this Purchase Order.</td></tr>`;
                        return;
                    }

                    let html = '';
                    data.items.forEach((item, index) => {
                        html += `
                            <tr>
                                <td class="text-center fw-semibold text-body-secondary">${index + 1}</td>
                                <td>
                                    <div class="fw-semibold">${item.product_name}</div>
                                    <div class="small text-body-secondary">${item.product_code || ''}</div>
                                    <input type="hidden" name="items[${index}][purchase_order_item_id]" value="${item.purchase_order_item_id}">
                                    <input type="hidden" name="items[${index}][product_id]" value="${item.product_id || ''}">
                                    <input type="hidden" name="items[${index}][description]" value="${item.description || ''}">
                                    <input type="hidden" name="items[${index}][unit]" value="${item.unit || ''}">
                                    <input type="hidden" name="items[${index}][ordered_qty]" value="${item.ordered_qty}">
                                </td>
                                <td>${item.unit}</td>
                                <td class="text-end font-monospace">${item.ordered_qty}</td>
                                <td class="text-end font-monospace text-body-secondary">${item.previously_received_qty}</td>
                                <td class="text-end font-monospace fw-semibold text-primary">${item.balance_qty}</td>
                                <td>
                                    <input type="number" name="items[${index}][received_qty]"
                                           class="form-control form-control-sm text-end qty-received" min="0" max="${item.ordered_qty}"
                                           value="${item.balance_qty}">
                                </td>
                                <td>
                                    <input type="text" name="items[${index}][remarks]"
                                           class="form-control form-control-sm" placeholder="Line remarks">
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                })
                .catch(err => {
                    console.error('Error fetching PO details:', err);
                    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">Failed to load PO items. Please refresh and try again.</td></tr>`;
                });
        });

        // Trigger change if PO is pre-selected
        if (poSelect.value) {
            poSelect.dispatchEvent(new Event('change'));
        }
    }

    // Auto-calculate passed = received - rejected on edit view
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('qty-rejected') || e.target.classList.contains('qty-received')) {
            const row = e.target.closest('tr');
            if (row) {
                const recInput = row.querySelector('.qty-received');
                const rejInput = row.querySelector('.qty-rejected');
                const pasInput = row.querySelector('.qty-passed');

                if (recInput && rejInput && pasInput) {
                    const rec = parseInt(recInput.value) || 0;
                    const rej = parseInt(rejInput.value) || 0;
                    pasInput.value = Math.max(0, rec - rej);
                }
            }
        }
    });
});
</script>
@endpush
