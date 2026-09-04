<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $showDeleted = $request->boolean('deleted');

        $query = Voucher::query()->with(['user:id,name', 'approver:id,name']);

        if ($showDeleted) {
            $query->onlyTrashed();
        }

        if ($status) {
            $query->where('status', $status);
        }

        // Standard users can view their own vouchers; Admins/SuperAdmins view all
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->hasRole('Admin') && !auth()->user()->hasRole('Accounts')) {
            $query->where('user_id', auth()->id());
        }

        $vouchers = $query->latest('id')->paginate(20)->withQueryString();

        $pendingCount  = Voucher::where('status', 'pending')->count();
        $approvedCount = Voucher::where('status', 'approved')->count();
        $rejectedCount = Voucher::where('status', 'rejected')->count();

        return view('finance.vouchers.index', compact(
            'vouchers', 'status', 'showDeleted', 'pendingCount', 'approvedCount', 'rejectedCount'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'category'     => 'required|string',
            'amount'       => 'required|numeric|min:0.01',
            'voucher_date' => 'required|date',
            'notes'        => 'nullable|string',
            'receipt'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('vouchers', 'public');
        }

        $voucherNum = 'VCH-' . date('Y') . '-' . str_pad((string) (Voucher::withTrashed()->max('id') + 1), 4, '0', STR_PAD_LEFT);

        Voucher::create([
            'voucher_num'  => $voucherNum,
            'user_id'      => auth()->id(),
            'title'        => $request->title,
            'category'     => $request->category,
            'amount'       => $request->amount,
            'voucher_date' => $request->voucher_date,
            'receipt_path' => $receiptPath,
            'status'       => 'pending',
            'notes'        => $request->notes,
        ]);

        return redirect()->route('finance.vouchers.index')->with('success', "Voucher {$voucherNum} submitted for approval.");
    }

    public function approve(Voucher $voucher, FinanceService $financeService): RedirectResponse
    {
        $voucher->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Auto-record expense entry in Finance Tracker
        $financeService->recordApprovedVoucher($voucher, auth()->id());

        return redirect()->route('finance.vouchers.index')->with('success', "Voucher {$voucher->voucher_num} approved.");
    }

    public function reject(Request $request, Voucher $voucher): RedirectResponse
    {
        $request->validate(['rejection_reason' => 'required|string']);

        $voucher->update([
            'status'           => 'rejected',
            'approved_by'       => auth()->id(),
            'approved_at'       => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return redirect()->route('finance.vouchers.index')->with('success', "Voucher {$voucher->voucher_num} rejected.");
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        $voucher->delete();

        return redirect()->route('finance.vouchers.index')->with('success', 'Voucher moved to deleted items.');
    }

    public function restore(int $id): RedirectResponse
    {
        $voucher = Voucher::onlyTrashed()->findOrFail($id);
        $voucher->restore();

        return redirect()->route('finance.vouchers.index')->with('success', 'Voucher restored successfully.');
    }
}
