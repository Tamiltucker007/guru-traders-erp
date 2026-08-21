@php
    $dot = function (string $state): string {
        return match ($state) {
            'verified' => 'text-success',
            'errors'   => 'text-danger',
            'review'   => 'text-warning',
            'uploaded' => 'text-info',
            default    => 'text-body-secondary',
        };
    };
@endphp

<x-app-layout>
    <x-slot name="header">Export Documents</x-slot>

    <x-ui.card title="Export Documents" variant="primary">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Doc no., OC no., buyer">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Buyer</label>
                <select name="buyer_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($buyers as $id => $label)
                        <option value="{{ $id }}" @selected((string) ($filters['buyer_id'] ?? '') === (string) $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('export.documents.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Export Order</th>
                        <th>Buyer</th>
                        <th class="text-center">Invoice</th>
                        <th class="text-center">Packing List</th>
                        <th class="text-center">Shipping Bill</th>
                        <th>OCR Status</th>
                        <th class="text-end" style="width:120px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        @php $ocr = $ocrDashboards[$document->id] ?? ['status' => 'pending', 'status_label' => 'Not scanned', 'invoice' => 'pending', 'packing_list' => 'pending', 'shipping_bill' => 'pending', 'action' => 'Scan']; @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold font-monospace">{{ $document->doc_num }}</div>
                                <div class="small text-body-secondary">{{ $document->orderConfirmation?->oc_num ?? '—' }}</div>
                            </td>
                            <td>{{ $document->buyer?->company_name }} <span class="text-body-secondary">({{ $document->buyer?->display_code }})</span></td>
                            <td class="text-center"><i class="bi bi-circle-fill {{ $dot($ocr['invoice']) }}" title="{{ $ocr['invoice'] }}"></i></td>
                            <td class="text-center"><i class="bi bi-circle-fill {{ $dot($ocr['packing_list']) }}" title="{{ $ocr['packing_list'] }}"></i></td>
                            <td class="text-center"><i class="bi bi-circle-fill {{ $dot($ocr['shipping_bill']) }}" title="{{ $ocr['shipping_bill'] }}"></i></td>
                            <td>
                                <span @class([
                                    'badge',
                                    'text-bg-success' => ($ocr['status'] ?? '') === 'verified',
                                    'text-bg-danger' => ($ocr['status'] ?? '') === 'errors',
                                    'text-bg-warning' => ($ocr['status'] ?? '') === 'review',
                                    'text-bg-secondary' => ! in_array(($ocr['status'] ?? ''), ['verified', 'errors', 'review'], true),
                                ])>{{ $ocr['status_label'] ?? 'Not scanned' }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('export.documents.show', $document) }}"
                                   class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('export.ocr.index', ['export_document_id' => $document->id]) }}"
                                   class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="{{ $ocr['action'] ?? 'OCR' }}">
                                    {{ $ocr['action'] ?? 'Scan' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="7" icon="bi-files"
                                          title="No Export Documents yet"
                                          message="Raise one from a confirmed Order Confirmation's item list." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($documents->hasPages())
            <div class="mt-3">{{ $documents->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $documents->firstItem() ?? 0 }}–{{ $documents->lastItem() ?? 0 }} of {{ $documents->total() }}
            · Dots: <span class="text-success">●</span> verified
            <span class="text-warning">●</span> review
            <span class="text-danger">●</span> errors
            <span class="text-body-secondary">●</span> pending
        </div>
    </x-ui.card>
</x-app-layout>
