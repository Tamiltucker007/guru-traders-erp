@extends('layouts.app')

@section('title', 'Vouchers')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 fw-bold">Expense Vouchers</h1>
            </div>
            <div class="col-sm-6 text-end">
                @can('voucher.create')
                    <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#newVoucherModal">
                        <i class="bi bi-plus-lg me-1"></i> Submit Voucher
                    </button>
                @endcan
                <a href="{{ route('finance.vouchers.index', ['deleted' => !$showDeleted]) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-trash me-1"></i> {{ $showDeleted ? 'Active Vouchers' : 'Deleted Vouchers' }}
                </a>
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

        {{-- Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 border-start border-4 border-warning">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Pending Vouchers</div>
                        <div class="fs-3 fw-bold text-warning">{{ $pendingCount }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 border-start border-4 border-success">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Approved Vouchers</div>
                        <div class="fs-3 fw-bold text-success">{{ $approvedCount }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 border-start border-4 border-danger">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Rejected Vouchers</div>
                        <div class="fs-3 fw-bold text-danger">{{ $rejectedCount }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <form method="GET" action="{{ route('finance.vouchers.index') }}" class="row g-2 align-items-center mb-0">
                    <div class="col-md-4">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('finance.vouchers.index') }}" class="btn btn-outline-secondary w-100">Reset Filter</a>
                    </div>
                </form>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Voucher #</th>
                            <th>Member / Employee</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th class="text-end">Amount</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vouchers as $vch)
                            <tr>
                                <td class="fw-bold">{{ $vch->voucher_num }}</td>
                                <td>{{ $vch->user->name ?? '—' }}</td>
                                <td>{{ $vch->title }}</td>
                                <td><span class="badge bg-light text-dark border">{{ ucfirst($vch->category) }}</span></td>
                                <td>{{ $vch->voucher_date?->format('d M Y') }}</td>
                                <td class="text-end fw-bold text-dark">₹{{ number_format($vch->amount, 2) }}</td>
                                <td>
                                    @if($vch->receipt_path)
                                        <a href="{{ asset('storage/' . $vch->receipt_path) }}" target="_blank" class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-file-earmark-pdf"></i> View
                                        </a>
                                    @else
                                        <span class="text-muted small">None</span>
                                    @endif
                                </td>
                                <td>
                                    @if($vch->status === 'approved')
                                        <span class="badge bg-success">Approved</span>
                                    @elseif($vch->status === 'rejected')
                                        <span class="badge bg-danger">Rejected</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($showDeleted)
                                        <form action="{{ route('finance.vouchers.restore', $vch->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
                                        </form>
                                    @else
                                        @if($vch->status === 'pending')
                                            @can('voucher.approve')
                                                <form action="{{ route('finance.vouchers.approve', $vch->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-sm btn-success me-1" title="Approve"><i class="bi bi-check-lg"></i></button>
                                                </form>
                                                <button class="btn btn-sm btn-danger me-1" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $vch->id }}" title="Reject">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            @endcan
                                        @endif
                                        @can('voucher.delete')
                                            <form action="{{ route('finance.vouchers.destroy', $vch->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this voucher?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>

                            {{-- Reject Modal --}}
                            <div class="modal fade" id="rejectModal{{ $vch->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('finance.vouchers.reject', $vch->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Voucher {{ $vch->voucher_num }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                                                    <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Specify why this voucher is rejected..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject Voucher</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No vouchers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

{{-- Submit Voucher Modal --}}
<div class="modal fade" id="newVoucherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('finance.vouchers.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Submit Expense Voucher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Voucher Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Travel & Transport to Client Site" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="material">Material & Fabric</option>
                            <option value="staff">Staff & HR</option>
                            <option value="rent">Rent & Utilities</option>
                            <option value="marketing">Marketing & Sales</option>
                            <option value="logistics">Logistics & IT</option>
                            <option value="admin">Admin Overhead</option>
                            <option value="consulting">Consulting & Legal</option>
                            <option value="voucher" selected>General Expense</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="voucher_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Receipt / File (PDF, JPG, PNG)</label>
                        <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes / Description</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Additional details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
