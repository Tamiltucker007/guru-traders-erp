@extends('layouts.app')

@section('title', 'GST Filings')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 fw-bold">GST Filing Tracker</h1>
            </div>
            <div class="col-sm-6 text-end">
                <span class="text-muted small">GSTR-1 due 11th · GSTR-3B due 20th of following month</span>
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

        {{-- Table --}}
        <div class="card shadow-sm">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Period</th>
                            <th>Financial Year</th>
                            <th>GSTR-1 Status</th>
                            <th>GSTR-1 Filed At</th>
                            <th>GSTR-3B Status</th>
                            <th>GSTR-3B Filed At</th>
                            <th class="text-end">Sales Tax</th>
                            <th class="text-end">Purchase Tax</th>
                            <th class="text-end">Net Payable</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($filings as $gst)
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
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editGstModal{{ $gst->id }}">
                                        <i class="bi bi-pencil"></i> Update
                                    </button>
                                </td>
                            </tr>

                            {{-- Edit GST Modal --}}
                            <div class="modal fade" id="editGstModal{{ $gst->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('finance.gst.update', $gst->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">Update GST Status — {{ $gst->period }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">GSTR-1 Status</label>
                                                    <select name="gstr1_status" class="form-select">
                                                        <option value="pending" {{ $gst->gstr1_status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                        <option value="filed" {{ $gst->gstr1_status === 'filed' ? 'selected' : '' }}>Filed</option>
                                                        <option value="overdue" {{ $gst->gstr1_status === 'overdue' ? 'selected' : '' }}>Overdue</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">GSTR-1 Filed Date</label>
                                                    <input type="date" name="gstr1_filed_at" class="form-control" value="{{ $gst->gstr1_filed_at?->format('Y-m-d') }}">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">GSTR-3B Status</label>
                                                    <select name="gstr3b_status" class="form-select">
                                                        <option value="pending" {{ $gst->gstr3b_status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                        <option value="filed" {{ $gst->gstr3b_status === 'filed' ? 'selected' : '' }}>Filed</option>
                                                        <option value="overdue" {{ $gst->gstr3b_status === 'overdue' ? 'selected' : '' }}>Overdue</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">GSTR-3B Filed Date</label>
                                                    <input type="date" name="gstr3b_filed_at" class="form-control" value="{{ $gst->gstr3b_filed_at?->format('Y-m-d') }}">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Total Sales Output Tax (₹)</label>
                                                    <input type="number" step="0.01" name="total_sales_tax" class="form-control" value="{{ $gst->total_sales_tax }}">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Total Purchase Input Tax Credit (₹)</label>
                                                    <input type="number" step="0.01" name="total_purchase_tax" class="form-control" value="{{ $gst->total_purchase_tax }}">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Update GST Record</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No GST filing records found for {{ $fy }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
