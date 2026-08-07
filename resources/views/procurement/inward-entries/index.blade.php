<x-app-layout>
    <x-slot name="header">Goods Inward</x-slot>

    <x-ui.card title="Goods Inward Receipts" variant="primary">
        <x-slot name="actions">
            @can('inward-entry.create')
                <a href="{{ route('procurement.inward-entries.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New Goods Inward
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Inward no., challan no., supplier">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Supplier</label>
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $id => $label)
                        <option value="{{ $id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-body-secondary mb-1">PO Reference</label>
                <select name="purchase_order_id" class="form-select form-select-sm">
                    <option value="">All POs</option>
                    @foreach($purchaseOrders as $id => $poNum)
                        <option value="{{ $id }}" @selected((string) ($filters['purchase_order_id'] ?? '') === (string) $id)>{{ $poNum }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-body-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('procurement.inward-entries.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Inward No.</th>
                        <th>Date</th>
                        <th>PO Reference</th>
                        <th>Supplier</th>
                        <th>Challan No.</th>
                        <th class="text-end">Received Qty</th>
                        <th class="text-end">Passed Qty</th>
                        <th class="text-end">Rejected Qty</th>
                        <th style="width:140px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inwardEntries as $inward)
                        <tr>
                            <td class="fw-semibold font-monospace">{{ $inward->inward_no }}</td>
                            <td>{{ $inward->inward_date?->format('d M Y') }}</td>
                            <td>
                                @if($inward->purchaseOrder)
                                    <a href="{{ route('procurement.purchase-orders.show', $inward->purchaseOrder) }}" class="text-decoration-none font-monospace">
                                        {{ $inward->purchaseOrder->po_num }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $inward->supplier?->company_name }} <span class="text-body-secondary">({{ $inward->supplier?->display_code }})</span></td>
                            <td>{{ $inward->challan_no ?: '—' }}</td>
                            <td class="text-end fw-semibold">{{ number_format($inward->totalReceivedQty()) }}</td>
                            <td class="text-end text-success">{{ number_format($inward->totalPassedQty()) }}</td>
                            <td class="text-end text-danger">{{ number_format($inward->totalRejectedQty()) }}</td>
                            <td><span class="badge text-bg-{{ $inward->statusColor() }}">{{ $inward->statusLabel() }}</span></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('inward-entry.view')
                                        <a href="{{ route('procurement.inward-entries.show', $inward) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View & QC">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('inward-entry.edit')
                                        <a href="{{ route('procurement.inward-entries.edit', $inward) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('inward-entry.delete')
                                        <x-ui.delete-form
                                            :action="route('procurement.inward-entries.destroy', $inward)"
                                            :confirm="'Delete Goods Inward &quot;'.$inward->inward_no.'&quot;? This will recalculate the Purchase Order status.'" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="10" icon="bi-box-arrow-in-down"
                                          title="No Goods Inward receipts recorded"
                                          message="Record incoming goods delivered by suppliers against a Purchase Order." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inwardEntries->hasPages())
            <div class="mt-3">{{ $inwardEntries->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $inwardEntries->firstItem() ?? 0 }}–{{ $inwardEntries->lastItem() ?? 0 }} of {{ $inwardEntries->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
