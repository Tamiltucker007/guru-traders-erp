@extends('layouts.app')

@section('title', 'Finance Tracker')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 fw-bold">Finance Tracker</h1>
            </div>
            <div class="col-sm-6 text-end">
                @can('finance-tracker.create')
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTxModal">
                        <i class="bi bi-plus-lg me-1"></i> Add Transaction
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

        {{-- Nav Tabs --}}
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'overview' ? 'active fw-bold' : '' }}" href="{{ route('finance.tracker.index', ['tab' => 'overview']) }}">
                    <i class="bi bi-list-columns-reverse me-1"></i> Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'charts' ? 'active fw-bold' : '' }}" href="{{ route('finance.tracker.index', ['tab' => 'charts']) }}">
                    <i class="bi bi-pie-chart me-1"></i> Charts & Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'invoices' ? 'active fw-bold' : '' }}" href="{{ route('finance.tracker.index', ['tab' => 'invoices']) }}">
                    <i class="bi bi-journal-text me-1"></i> Invoices
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'gst' ? 'active fw-bold' : '' }}" href="{{ route('finance.tracker.index', ['tab' => 'gst']) }}">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> GST Filings
                </a>
            </li>
        </ul>

        @if($activeTab === 'overview')
            {{-- KPI Summary --}}
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 border-start border-4 border-success">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase font-monospace fw-bold">Total Income</div>
                            <div class="fs-3 fw-bold text-success">₹{{ number_format($totalIncome, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 border-start border-4 border-danger">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase font-monospace fw-bold">Total Expense</div>
                            <div class="fs-3 fw-bold text-danger">₹{{ number_format($totalExpense, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 border-start border-4 {{ $netPat >= 0 ? 'border-primary' : 'border-warning' }}">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase font-monospace fw-bold">Net Profit / Loss (PAT)</div>
                            <div class="fs-3 fw-bold {{ $netPat >= 0 ? 'text-primary' : 'text-danger' }}">₹{{ number_format($netPat, 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Transactions Table --}}
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <form method="GET" action="{{ route('finance.tracker.index') }}" class="row g-2 align-items-center mb-0">
                        <input type="hidden" name="tab" value="overview">
                        <div class="col-md-3">
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <option value="income" {{ $type === 'income' ? 'selected' : '' }}>Income</option>
                                <option value="expense" {{ $type === 'expense' ? 'selected' : '' }}>Expense</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="category" class="form-select" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <option value="billing" {{ $category === 'billing' ? 'selected' : '' }}>Billing</option>
                                <option value="staff" {{ $category === 'staff' ? 'selected' : '' }}>Staff & HR</option>
                                <option value="material" {{ $category === 'material' ? 'selected' : '' }}>Material</option>
                                <option value="rent" {{ $category === 'rent' ? 'selected' : '' }}>Rent & Utilities</option>
                                <option value="voucher" {{ $category === 'voucher' ? 'selected' : '' }}>Vouchers</option>
                                <option value="other" {{ $category === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search description, invoice, voucher..." value="{{ $search }}">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-search"></i> Search</button>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Voucher / Inv #</th>
                                <th>Party</th>
                                <th>Description</th>
                                <th>Mode</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $tx)
                                <tr>
                                    <td>{{ $tx->transaction_date?->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge {{ $tx->type === 'income' ? 'bg-success' : 'bg-danger' }}">
                                            {{ ucfirst($tx->type) }}
                                        </span>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ ucfirst($tx->category) }}</span></td>
                                    <td>{{ $tx->invoice_num ?? $tx->voucher_num ?? '—' }}</td>
                                    <td>{{ $tx->buyer->company_name ?? $tx->supplier->company_name ?? '—' }}</td>
                                    <td>{{ Str::limit($tx->description, 35) }}</td>
                                    <td>{{ $tx->payment_mode }}</td>
                                    <td class="text-end fw-bold {{ $tx->type === 'income' ? 'text-success' : 'text-danger' }}">
                                        {{ $tx->type === 'income' ? '+' : '-' }}₹{{ number_format($tx->amount, 2) }}
                                    </td>
                                    <td class="text-center">
                                        @can('finance-tracker.delete')
                                            <form action="{{ route('finance.tracker.destroy', $tx->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete transaction?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No transactions recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @elseif($activeTab === 'charts')
            {{-- Charts View --}}
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white fw-bold">Revenue vs Expense Summary</div>
                        <div class="card-body">
                            <p class="text-muted small">Total Income: <strong class="text-success">₹{{ number_format($totalIncome, 2) }}</strong></p>
                            <p class="text-muted small">Total Expense: <strong class="text-danger">₹{{ number_format($totalExpense, 2) }}</strong></p>
                            <div class="progress style-bar mb-3" style="height: 24px;">
                                @php
                                    $tot = max(1, $totalIncome + $totalExpense);
                                    $incPct = round(($totalIncome / $tot) * 100);
                                    $expPct = 100 - $incPct;
                                @endphp
                                <div class="progress-bar bg-success" style="width: {{ $incPct }}%">Income {{ $incPct }}%</div>
                                <div class="progress-bar bg-danger" style="width: {{ $expPct }}%">Expense {{ $expPct }}%</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white fw-bold">Net Operating Surplus (PAT)</div>
                        <div class="card-body text-center py-4">
                            <h2 class="{{ $netPat >= 0 ? 'text-success' : 'text-danger' }} fw-bold">
                                ₹{{ number_format($netPat, 2) }}
                            </h2>
                            <p class="text-muted small mb-0">Calculated as Total Income minus Operating Expenses</p>
                        </div>
                    </div>
                </div>
            </div>

        @elseif($activeTab === 'invoices')
            {{-- Invoices Sync View --}}
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">Synced Sales Invoices</div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Buyer</th>
                                <th>Invoice Date</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Outstanding</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $inv)
                                <tr>
                                    <td class="fw-bold">{{ $inv->invoice_num }}</td>
                                    <td>{{ $inv->buyer->company_name ?? '—' }}</td>
                                    <td>{{ $inv->invoice_date?->format('d M Y') }}</td>
                                    <td class="text-end">₹{{ number_format($inv->total_amount, 2) }}</td>
                                    <td class="text-end text-success">₹{{ number_format($inv->paid_amount, 2) }}</td>
                                    <td class="text-end text-danger fw-bold">₹{{ number_format($inv->balance_amount, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $inv->status === 'paid' ? 'bg-success' : ($inv->status === 'partial' ? 'bg-info' : 'bg-warning') }}">
                                            {{ ucfirst($inv->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No invoices synced.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @elseif($activeTab === 'gst')
            {{-- GST Filings View --}}
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h6 class="m-0 fw-bold">GST Filings Status (GSTR-1 & GSTR-3B)</h6>
                    <span class="text-muted small">GSTR-1 due 11th · GSTR-3B due 20th of following month</span>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Period</th>
                                <th>Financial Year</th>
                                <th>GSTR-1 Status</th>
                                <th>GSTR-1 Filed Date</th>
                                <th>GSTR-3B Status</th>
                                <th>GSTR-3B Filed Date</th>
                                <th class="text-end">Sales Tax</th>
                                <th class="text-end">Purchase Tax</th>
                                <th class="text-end">Net Payable</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($gstFilings as $gst)
                                <tr>
                                    <td class="fw-bold">{{ $gst->period }}</td>
                                    <td>{{ $gst->financial_year }}</td>
                                    <td><span class="badge {{ $gst->gstr1_status === 'filed' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($gst->gstr1_status) }}</span></td>
                                    <td>{{ $gst->gstr1_filed_at?->format('d M Y') ?? '—' }}</td>
                                    <td><span class="badge {{ $gst->gstr3b_status === 'filed' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($gst->gstr3b_status) }}</span></td>
                                    <td>{{ $gst->gstr3b_filed_at?->format('d M Y') ?? '—' }}</td>
                                    <td class="text-end">₹{{ number_format($gst->total_sales_tax, 2) }}</td>
                                    <td class="text-end">₹{{ number_format($gst->total_purchase_tax, 2) }}</td>
                                    <td class="text-end fw-bold">₹{{ number_format($gst->net_gst_payable, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted py-4">No GST filing records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</div>

{{-- New Transaction Modal --}}
<div class="modal fade" id="newTxModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('finance.tracker.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Finance Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="billing">Billing</option>
                            <option value="staff">Staff & HR</option>
                            <option value="material">Material & Fabric</option>
                            <option value="rent">Rent & Utilities</option>
                            <option value="marketing">Marketing</option>
                            <option value="logistics">Logistics & IT</option>
                            <option value="voucher">Voucher Expense</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
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
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
