@extends('layouts.app')

@section('title', 'Payroll & Salary')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 fw-bold">Payroll & Salary</h1>
            </div>
            <div class="col-sm-6 text-end">
                @can('payroll.process')
                    <form action="{{ route('finance.payroll.process') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="period" value="{{ $period }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-play-circle me-1"></i> Calculate & Process Period {{ $period }}
                        </button>
                    </form>
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

        {{-- Period Selector Toolbar --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('finance.payroll.index') }}" class="row g-2 align-items-center mb-0">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Payroll Period (YYYY-MM)</label>
                        <input type="month" name="period" class="form-control" value="{{ $period }}" onchange="this.form.submit()">
                    </div>
                </form>
            </div>
        </div>

        {{-- Payroll Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0 border-start border-4 border-primary">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Gross Monthly Salary</div>
                        <div class="fs-4 fw-bold text-dark">₹{{ number_format($totalGross, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 border-start border-4 border-danger">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Total LOP Deductions</div>
                        <div class="fs-4 fw-bold text-danger">₹{{ number_format($totalDeduct, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 border-start border-4 border-info">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Total Overtime Pay</div>
                        <div class="fs-4 fw-bold text-info">₹{{ number_format($totalOvertime, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 border-start border-4 border-success">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Net Payable Payroll</div>
                        <div class="fs-4 fw-bold text-success">₹{{ number_format($totalNetPay, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold">Employee Payroll Register — {{ $period }}</h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th class="text-end">Base Salary</th>
                            <th class="text-center">Days Present</th>
                            <th class="text-center">Normal LOP (1x)</th>
                            <th class="text-center">2x LOP</th>
                            <th class="text-end text-danger">Deductions</th>
                            <th class="text-end">Overtime (Hrs)</th>
                            <th class="text-end text-success">Net Pay</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $rec)
                            <tr>
                                <td class="fw-bold">{{ $rec->user->name ?? '—' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $rec->employment_type }}</span></td>
                                <td class="text-end">₹{{ number_format($rec->monthly_salary, 2) }}</td>
                                <td class="text-center">{{ $rec->days_present }} / {{ $rec->working_days }}</td>
                                <td class="text-center">{{ $rec->normal_lop_days }}</td>
                                <td class="text-center text-danger fw-bold">{{ $rec->double_lop_days }}</td>
                                <td class="text-end text-danger">₹{{ number_format($rec->total_deductions, 2) }}</td>
                                <td class="text-end">{{ $rec->overtime_hours }} hrs (₹{{ number_format($rec->overtime_amount, 2) }})</td>
                                <td class="text-end fw-bold text-success">₹{{ number_format($rec->net_pay, 2) }}</td>
                                <td>
                                    <span class="badge {{ $rec->status === 'paid' ? 'bg-success' : 'bg-primary' }}">
                                        {{ ucfirst($rec->status) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editPayrollModal{{ $rec->id }}" title="Edit / Adjust LOP">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @can('payroll.delete')
                                        <form action="{{ route('finance.payroll.destroy', $rec->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete payroll entry?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>

                            {{-- Edit Payroll Modal --}}
                            <div class="modal fade" id="editPayrollModal{{ $rec->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form action="{{ route('finance.payroll.update', $rec->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">Adjust Payroll — {{ $rec->user->name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Working Days in Month</label>
                                                    <input type="number" name="working_days" class="form-control" value="{{ $rec->working_days }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Days Present</label>
                                                    <input type="number" name="days_present" class="form-control" value="{{ $rec->days_present }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Normal LOP Days (1x Deduction)</label>
                                                    <input type="number" name="normal_lop_days" class="form-control" value="{{ $rec->normal_lop_days }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Unannounced LOP Days (2x Deduction)</label>
                                                    <input type="number" name="double_lop_days" class="form-control" value="{{ $rec->double_lop_days }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Overtime Hours</label>
                                                    <input type="number" step="0.5" name="overtime_hours" class="form-control" value="{{ $rec->overtime_hours }}" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Adjustments</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No payroll records for {{ $period }}. Click "Calculate & Process" above to generate.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- LOP Explanation Box --}}
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body small text-muted">
                <strong class="text-dark"><i class="bi bi-info-circle me-1"></i> Payroll & LOP Calculation Rules:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    <li><strong>Normal LOP (1x):</strong> Deducts 1 day's salary per absent day with prior leave approval.</li>
                    <li><strong>Unannounced LOP (2x):</strong> Deducts 2 days' salary per absent day without prior notice.</li>
                    <li><strong>Overtime Pay:</strong> Hourly rate = (Monthly Base Salary ÷ Working Days ÷ 8 hrs). Overtime = Hours × Hourly Rate.</li>
                    <li><strong>Net Payable:</strong> Base Salary - Total LOP Deductions + Overtime Amount.</li>
                </ul>
            </div>
        </div>

    </div>
</div>
@endsection
