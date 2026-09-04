@extends('layouts.app')

@section('title', 'Budget Planner')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 fw-bold">Budget Planner</h1>
            </div>
            <div class="col-sm-6 text-end">
                @can('budget.create')
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#setTargetModal">
                        <i class="bi bi-bullseye me-1"></i> Set Budget Target
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

        @if($untaggedCount > 0)
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Notice:</strong> {{ $untaggedCount }} expense transaction(s) require vertical/category tagging for budget tracking.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Period Selector Toolbar --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('finance.budget.index') }}" class="row g-2 align-items-center mb-0">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Period Type</label>
                        <select name="period_type" class="form-select" onchange="this.form.submit()">
                            <option value="month" {{ $periodType === 'month' ? 'selected' : '' }}>Month</option>
                            <option value="quarter" {{ $periodType === 'quarter' ? 'selected' : '' }}>Quarter</option>
                            <option value="fy" {{ $periodType === 'fy' ? 'selected' : '' }}>Financial Year</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Period Value</label>
                        <input type="text" name="period_value" class="form-control" value="{{ $periodVal }}" placeholder="e.g. 2026-09 or 2026-Q3">
                    </div>
                    <div class="col-md-2 mt-auto">
                        <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-arrow-repeat"></i> Load</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Totals Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 border-start border-4 border-primary">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Total Budget Target</div>
                        <div class="fs-3 fw-bold text-primary">₹{{ number_format($totalBudgetTarget, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 border-start border-4 border-danger">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Total Actual Spend</div>
                        <div class="fs-3 fw-bold text-danger">₹{{ number_format($totalActualSpend, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 border-start border-4 {{ ($totalBudgetTarget - $totalActualSpend) >= 0 ? 'border-success' : 'border-warning' }}">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase font-monospace fw-bold">Remaining Budget Balance</div>
                        <div class="fs-3 fw-bold {{ ($totalBudgetTarget - $totalActualSpend) >= 0 ? 'text-success' : 'text-danger' }}">
                            ₹{{ number_format($totalBudgetTarget - $totalActualSpend, 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Department Verticals Breakdown Grid --}}
        <div class="row g-3 mb-4">
            @foreach($categories as $catKey => $catName)
                @php
                    $targetRow = $targets->firstWhere('category_or_vertical', $catKey);
                    $targetAmt = $targetRow ? (float) $targetRow->target_amount : 0;
                    $actualAmt = (float) ($actualSpends[$catKey] ?? 0);
                    $pct = $targetAmt > 0 ? min(100, round(($actualAmt / $targetAmt) * 100)) : 0;
                @endphp
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 text-dark">{{ $catName }}</h6>
                                <span class="badge {{ $actualAmt > $targetAmt && $targetAmt > 0 ? 'bg-danger' : 'bg-secondary' }}">
                                    {{ $pct }}% Used
                                </span>
                            </div>

                            <div class="d-flex justify-content-between text-muted small mb-1">
                                <span>Target: ₹{{ number_format($targetAmt, 2) }}</span>
                                <span class="fw-bold text-dark">Spent: ₹{{ number_format($actualAmt, 2) }}</span>
                            </div>

                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar {{ $actualAmt > $targetAmt && $targetAmt > 0 ? 'bg-danger' : 'bg-primary' }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</div>

{{-- Set Target Modal --}}
<div class="modal fade" id="setTargetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('finance.budget.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Set Vertical Budget Target</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Period Type <span class="text-danger">*</span></label>
                        <select name="period_type" class="form-select" required>
                            <option value="month" {{ $periodType === 'month' ? 'selected' : '' }}>Month</option>
                            <option value="quarter" {{ $periodType === 'quarter' ? 'selected' : '' }}>Quarter</option>
                            <option value="fy" {{ $periodType === 'fy' ? 'selected' : '' }}>Financial Year</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Period Value <span class="text-danger">*</span></label>
                        <input type="text" name="period_value" class="form-control" value="{{ $periodVal }}" required placeholder="e.g. 2026-09">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category / Vertical <span class="text-danger">*</span></label>
                        <select name="category_or_vertical" class="form-select" required>
                            @foreach($categories as $catKey => $catName)
                                <option value="{{ $catKey }}">{{ $catName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="target_amount" class="form-control" placeholder="0.00" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Budget Target</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
