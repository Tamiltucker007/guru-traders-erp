@extends('layouts.app')

@section('title', 'Billing & Invoices')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 fw-bold">Billing & Invoices</h1>
            </div>
            <div class="col-sm-6 text-end">
                @can('billing.create')
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newInvoiceModal">
                        <i class="bi bi-plus-lg me-1"></i> New Invoice
                    </button>
                @endcan
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- KPI Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0 border-start border-4 border-primary">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Total Invoiced</div>
                        <div class="fs-4 fw-bold text-dark">₹{{ number_format($totalInvoiced, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 border-start border-4 border-success">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Total Paid</div>
                        <div class="fs-4 fw-bold text-success">₹{{ number_format($totalPaid, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 border-start border-4 border-warning">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">TDS Deducted</div>
                        <div class="fs-4 fw-bold text-warning">₹{{ number_format($totalTds, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 border-start border-4 border-danger">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Balance Due</div>
                        <div class="fs-4 fw-bold text-danger">₹{{ number_format($totalBalance, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Toolbar / Filters --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('finance.billing.index') }}" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search invoice # or buyer..." value="{{ $search }}">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="unpaid" {{ $status === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                            <option value="partial" {{ $status === 'partial' ? 'selected' : '' }}>Partial</option>
                            <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="overdue" {{ $status === 'overdue' ? 'selected' : '' }}>Overdue</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('finance.billing.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="card shadow-sm">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice #</th>
                            <th>Buyer / Client</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">TDS</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $inv)
                            <tr>
                                <td class="fw-bold">{{ $inv->invoice_num }}</td>
                                <td>{{ $inv->buyer->company_name ?? '—' }}</td>
                                <td>{{ $inv->invoice_date?->format('d M Y') }}</td>
                                <td>{{ $inv->due_date?->format('d M Y') }}</td>
                                <td class="text-end">₹{{ number_format($inv->subtotal, 2) }}</td>
                                <td class="text-end text-warning">₹{{ number_format($inv->tds_amount, 2) }} ({{ $inv->tds_percentage }}%)</td>
                                <td class="text-end fw-bold">₹{{ number_format($inv->total_amount, 2) }}</td>
                                <td class="text-end text-success">₹{{ number_format($inv->paid_amount, 2) }}</td>
                                <td class="text-end text-danger fw-bold">₹{{ number_format($inv->balance_amount, 2) }}</td>
                                <td>
                                    @if($inv->status === 'paid')
                                        <span class="badge bg-success">Paid</span>
                                    @elseif($inv->status === 'partial')
                                        <span class="badge bg-info text-dark">Partial</span>
                                    @elseif($inv->status === 'overdue')
                                        <span class="badge bg-danger">Overdue</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Unpaid</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($inv->balance_amount > 0)
                                        <button class="btn btn-sm btn-outline-success me-1" data-bs-toggle="modal" data-bs-target="#payModal{{ $inv->id }}" title="Record Payment">
                                            <i class="bi bi-cash-stack"></i>
                                        </button>
                                    @endif
                                    @can('billing.delete')
                                        <form action="{{ route('finance.billing.destroy', $inv->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this invoice?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>

                            {{-- Payment Modal --}}
                            <div class="modal fade" id="payModal{{ $inv->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('finance.billing.pay', $inv->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Record Payment — Invoice {{ $inv->invoice_num }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="mb-2"><strong>Buyer:</strong> {{ $inv->buyer->company_name ?? '—' }}</p>
                                                <p class="mb-3 text-danger"><strong>Current Balance Due:</strong> ₹{{ number_format($inv->balance_amount, 2) }}</p>

                                                <div class="mb-3">
                                                    <label class="form-label">Payment Amount (₹)</label>
                                                    <input type="number" step="0.01" name="payment_amount" class="form-control" value="{{ $inv->balance_amount }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Payment Mode</label>
                                                    <select name="payment_mode" class="form-select" required>
                                                        <option value="Bank Transfer">Bank Transfer</option>
                                                        <option value="UPI">UPI</option>
                                                        <option value="Cheque">Cheque</option>
                                                        <option value="Cash">Cash</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success">Record Payment</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No billing invoices found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($invoices->hasPages())
                <div class="card-footer clearfix">
                    {{ $invoices.links() }}
                </div>
            @endif
        </div>

    </div>
</div>

{{-- New Invoice Modal --}}
<div class="modal fade" id="newInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('finance.billing.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Create Sales Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Buyer / Client <span class="text-danger">*</span></label>
                            <select name="buyer_id" class="form-select" required>
                                <option value="">Select Buyer</option>
                                @foreach($buyers as $b)
                                    <option value="{{ $b->id }}">{{ $b->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Export Document (Optional)</label>
                            <select name="export_document_id" class="form-select">
                                <option value="">Select Export Document</option>
                                @foreach($exportDocs as $doc)
                                    <option value="{{ $doc->id }}">{{ $doc->doc_num }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                            <input type="date" name="invoice_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">TDS Percentage (%)</label>
                            <input type="number" step="0.01" name="tds_percentage" class="form-control" value="0.00">
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2">Invoice Line Items</h6>
                    <div id="invoiceItemsContainer">
                        <div class="row g-2 mb-2 align-items-center item-row">
                            <div class="col-md-5">
                                <input type="text" name="items[0][description]" class="form-control" placeholder="Item Description" required>
                            </div>
                            <div class="col-md-2">
                                <input type="text" name="items[0][hsn_sac]" class="form-control" placeholder="HSN/SAC">
                            </div>
                            <div class="col-md-2">
                                <input type="number" step="0.01" name="items[0][qty]" class="form-control" placeholder="Qty" value="1" required>
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" name="items[0][rate]" class="form-control" placeholder="Rate (₹)" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes / Instructions</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Payment terms, bank details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
