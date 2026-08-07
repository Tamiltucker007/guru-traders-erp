<x-app-layout>
    <x-slot name="header">Goods Inward {{ $inwardEntry->inward_no }}</x-slot>

    <x-ui.card :title="$inwardEntry->inward_no" variant="primary">
        <x-slot name="actions">
            @can('inward-entry.edit')
                <a href="{{ route('procurement.inward-entries.edit', $inwardEntry) }}" class="btn btn-sm btn-outline-primary me-1">
                    <i class="bi bi-pencil me-1"></i> Edit Receipt
                </a>
            @endcan
            <a href="{{ route('procurement.inward-entries.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Goods Inward
            </a>
        </x-slot>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="small text-body-secondary">Inward Number</div>
                <div class="fw-semibold font-monospace fs-5">{{ $inwardEntry->inward_no }}</div>
            </div>
            <div class="col-md-3">
                <div class="small text-body-secondary">Inward Date</div>
                <div class="fw-semibold">{{ $inwardEntry->inward_date?->format('d M Y') }}</div>
            </div>
            <div class="col-md-3">
                <div class="small text-body-secondary">Status</div>
                <div><span class="badge text-bg-{{ $inwardEntry->statusColor() }} fs-6">{{ $inwardEntry->statusLabel() }}</span></div>
            </div>
            <div class="col-md-3 text-md-end">
                <div class="small text-body-secondary">Challan / DC No.</div>
                <div class="fw-semibold">{{ $inwardEntry->challan_no ?: '—' }}</div>
                @if($inwardEntry->challan_date)
                    <div class="small text-body-secondary">Date: {{ $inwardEntry->challan_date->format('d M Y') }}</div>
                @endif
            </div>
        </div>

        <div class="row g-3 mb-4 p-3 bg-body-tertiary rounded border">
            <div class="col-md-6">
                <h6 class="fw-semibold mb-1 text-primary">Purchase Order Reference</h6>
                @if($inwardEntry->purchaseOrder)
                    <div class="fs-6 font-monospace fw-bold">
                        <a href="{{ route('procurement.purchase-orders.show', $inwardEntry->purchaseOrder) }}" class="text-decoration-none">
                            {{ $inwardEntry->purchaseOrder->po_num }}
                        </a>
                    </div>
                    <div class="small text-body-secondary">
                        PO Date: {{ $inwardEntry->purchaseOrder->po_date?->format('d M Y') }} · Status:
                        <span class="badge text-bg-{{ $inwardEntry->purchaseOrder->statusColor() }}">
                            {{ $inwardEntry->purchaseOrder->statusLabel() }}
                        </span>
                    </div>
                @else
                    <div class="text-body-secondary">None linked.</div>
                @endif
            </div>

            <div class="col-md-6">
                <h6 class="fw-semibold mb-1 text-primary">Supplier Details</h6>
                @if($inwardEntry->supplier)
                    <div class="fs-6 fw-bold">{{ $inwardEntry->supplier->company_name }}</div>
                    <div class="small text-body-secondary">
                        Code: {{ $inwardEntry->supplier->display_code }} · GST: {{ $inwardEntry->supplier->gst_number ?: 'N/A' }}
                    </div>
                @else
                    <div class="text-body-secondary">None linked.</div>
                @endif
            </div>
        </div>

        @if($inwardEntry->remarks)
            <div class="mb-4">
                <h6 class="fw-semibold mb-1">Receipt Remarks</h6>
                <div class="p-3 bg-light rounded border text-body">{{ $inwardEntry->remarks }}</div>
            </div>
        @endif

        <h6 class="fw-semibold mb-2">Delivered &amp; Inspected Items</h6>
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Product / Description</th>
                        <th style="width:90px">Unit</th>
                        <th class="text-end" style="width:110px">Ordered Qty</th>
                        <th class="text-end" style="width:110px">Received Qty</th>
                        <th class="text-end" style="width:110px">Passed Qty</th>
                        <th class="text-end" style="width:110px">Rejected Qty</th>
                        <th>Line Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inwardEntry->items as $index => $item)
                        <tr>
                            <td class="text-center text-body-secondary">{{ $index + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->product?->name ?? $item->description }}</div>
                                @if($item->product?->code)
                                    <div class="small text-body-secondary font-monospace">{{ $item->product->code }}</div>
                                @endif
                            </td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-end font-monospace">{{ number_format($item->ordered_qty) }}</td>
                            <td class="text-end font-monospace fw-semibold">{{ number_format($item->received_qty) }}</td>
                            <td class="text-end font-monospace text-success fw-bold">{{ number_format($item->passed_qty) }}</td>
                            <td class="text-end font-monospace text-danger fw-bold">{{ number_format($item->rejected_qty) }}</td>
                            <td class="small">
                                {{ $item->remarks ?: '—' }}
                                @if($item->qc_remarks)
                                    <div class="text-danger small mt-1"><strong>QC:</strong> {{ $item->qc_remarks }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary">No items recorded.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="text-end font-monospace">{{ number_format($inwardEntry->items->sum('ordered_qty')) }}</td>
                        <td class="text-end font-monospace">{{ number_format($inwardEntry->totalReceivedQty()) }}</td>
                        <td class="text-end font-monospace text-success">{{ number_format($inwardEntry->totalPassedQty()) }}</td>
                        <td class="text-end font-monospace text-danger">{{ number_format($inwardEntry->totalRejectedQty()) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- ================= Quality Control Inspection Section ================= --}}
        <div class="card border-warning mb-4">
            <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold text-warning-emphasis">
                    <i class="bi bi-patch-check me-2"></i>Quality Control (QC) Inspection Pass
                </h6>
                @if($inwardEntry->status === 'approved')
                    <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>QC Approved</span>
                @elseif($inwardEntry->status === 'rejected')
                    <span class="badge text-bg-danger"><i class="bi bi-x-circle me-1"></i>QC Rejected</span>
                @else
                    <span class="badge text-bg-warning"><i class="bi bi-clock-history me-1"></i>Pending Inspection</span>
                @endif
            </div>
            <div class="card-body">
                @if($inwardEntry->qc_inspected_at)
                    <div class="row g-3 mb-3 pb-3 border-bottom text-muted small">
                        <div class="col-md-6">
                            <strong>Inspected Date:</strong> {{ $inwardEntry->qc_inspected_at->format('d M Y, H:i') }}
                        </div>
                        <div class="col-md-6">
                            <strong>Inspected By:</strong> {{ $inwardEntry->qcInspector?->name ?? 'System User' }}
                        </div>
                    </div>
                @endif

                @can('inward-entry.approve')
                    <form action="{{ route('procurement.inward-entries.approve', $inwardEntry) }}" method="POST" id="qc_approve_form">
                        @csrf
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product / Description</th>
                                        <th class="text-end" style="width:100px">Received</th>
                                        <th class="text-end" style="width:120px">Passed Qty</th>
                                        <th class="text-end" style="width:120px">Rejected Qty</th>
                                        <th>QC Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($inwardEntry->items as $item)
                                        <tr>
                                            <td class="small">{{ $item->product?->name ?? $item->description }}</td>
                                            <td class="text-end font-monospace">{{ number_format($item->received_qty) }}</td>
                                            <td>
                                                <input type="number" name="items[{{ $item->id }}][passed_qty]"
                                                       class="form-control form-control-sm text-end qty-passed text-success" min="0" max="{{ $item->received_qty }}"
                                                       value="{{ old("items.{$item->id}.passed_qty", $item->passed_qty) }}">
                                            </td>
                                            <td>
                                                <input type="number" name="items[{{ $item->id }}][rejected_qty]"
                                                       class="form-control form-control-sm text-end qty-rejected text-danger" min="0" max="{{ $item->received_qty }}"
                                                       data-received="{{ $item->received_qty }}"
                                                       value="{{ old("items.{$item->id}.rejected_qty", $item->rejected_qty) }}">
                                            </td>
                                            <td>
                                                <input type="text" name="items[{{ $item->id }}][qc_remarks]"
                                                       class="form-control form-control-sm" placeholder="Line QC notes"
                                                       value="{{ old("items.{$item->id}.qc_remarks", $item->qc_remarks) }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">QC Decision</label>
                                <select name="status" class="form-select">
                                    <option value="approved" @selected($inwardEntry->status === 'approved')>Approve (QC Pass)</option>
                                    <option value="rejected" @selected($inwardEntry->status === 'rejected')>Reject Shipment</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">QC Inspection Notes / Remarks</label>
                                <input type="text" name="remarks" class="form-control" placeholder="Optional inspector notes..."
                                       value="{{ old('remarks', $inwardEntry->remarks) }}">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-warning w-100 fw-semibold">
                                    <i class="bi bi-shield-check me-1"></i> Submit QC Sign-off
                                </button>
                            </div>
                        </div>
                    </form>

                    @push('scripts')
                    <script>
                    document.getElementById('qc_approve_form')?.addEventListener('input', function (e) {
                        if (!e.target.classList.contains('qty-rejected')) return;

                        const row = e.target.closest('tr');
                        const passedInput = row?.querySelector('.qty-passed');
                        if (!passedInput) return;

                        const received = parseInt(e.target.dataset.received, 10) || 0;
                        const rejected = parseInt(e.target.value, 10) || 0;
                        passedInput.value = Math.max(0, received - rejected);
                    });
                    </script>
                    @endpush
                @else
                    <p class="mb-0 text-body-secondary small">
                        QC inspection requires <code>inward-entry.approve</code> permission (Quality Checker role).
                    </p>
                @endcan
            </div>
        </div>

        <h6 class="fw-semibold mb-2">Module Connections</h6>
        <div class="d-flex flex-wrap gap-2 mb-4">
            <span class="badge text-bg-light border fw-normal">PO Module — status updated to {{ $inwardEntry->purchaseOrder?->statusLabel() }}</span>
            <span class="badge text-bg-light border fw-normal">PO Delivery Timeline — auto-populated entry appended</span>
            <span class="badge text-bg-light border fw-normal">Supplier Master — {{ $inwardEntry->supplier?->company_name }}</span>
        </div>

        <dl class="row mb-0 mt-4 pt-3 border-top">
            <dt class="col-sm-3 text-body-secondary fw-normal">Created</dt>
            <dd class="col-sm-9 text-body-secondary small">
                {{ $inwardEntry->created_at?->format('d M Y, H:i') }}
                @if($inwardEntry->creator) by {{ $inwardEntry->creator->name }} @endif
            </dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Last updated</dt>
            <dd class="col-sm-9 text-body-secondary small">
                {{ $inwardEntry->updated_at?->format('d M Y, H:i') }}
                @if($inwardEntry->updater) by {{ $inwardEntry->updater->name }} @endif
            </dd>
        </dl>
    </x-ui.card>
</x-app-layout>
