<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Buyer;
use App\Models\FinanceTransaction;
use App\Models\GstFiling;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceTrackerController extends Controller
{
    public function index(Request $request): View
    {
        $activeTab = $request->query('tab', 'overview');
        $type = $request->query('type');
        $category = $request->query('category');
        $search = $request->query('search');

        // Overview Transactions
        $txQuery = FinanceTransaction::query()
            ->with(['buyer:id,company_name', 'supplier:id,company_name', 'creator:id,name'])
            ->latest('transaction_date')
            ->latest('id');

        if ($type) {
            $txQuery->where('type', $type);
        }
        if ($category) {
            $txQuery->where('category', $category);
        }
        if ($search) {
            $txQuery->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('invoice_num', 'like', "%{$search}%")
                  ->orWhere('voucher_num', 'like', "%{$search}%");
            });
        }

        $transactions = $txQuery->paginate(20)->withQueryString();

        // Financial Stats
        $totalIncome  = FinanceTransaction::where('type', 'income')->sum('amount');
        $totalExpense = FinanceTransaction::where('type', 'expense')->sum('amount');
        $netPat       = $totalIncome - $totalExpense;

        // Invoices Tab Sync
        $invoices = BillingInvoice::with('buyer:id,company_name')->latest('id')->paginate(15);

        // GST Filings Tab Sync
        $gstFilings = GstFiling::latest('period')->get();

        $buyers = Buyer::orderBy('company_name')->get(['id', 'company_name']);
        $suppliers = Supplier::orderBy('company_name')->get(['id', 'company_name']);

        return view('finance.tracker.index', compact(
            'activeTab', 'transactions', 'invoices', 'gstFilings',
            'buyers', 'suppliers', 'type', 'category', 'search',
            'totalIncome', 'totalExpense', 'netPat'
        ));
    }

    public function storeTransaction(Request $request): RedirectResponse
    {
        $request->validate([
            'transaction_date' => 'required|date',
            'type'             => 'required|in:income,expense',
            'category'         => 'required|string',
            'amount'           => 'required|numeric|min:0.01',
            'payment_mode'     => 'required|string',
            'buyer_id'         => 'nullable|exists:buyers,id',
            'supplier_id'      => 'nullable|exists:suppliers,id',
            'description'      => 'nullable|string',
            'gst_amount'       => 'nullable|numeric|min:0',
            'tds_amount'       => 'nullable|numeric|min:0',
        ]);

        FinanceTransaction::create([
            'transaction_date' => $request->transaction_date,
            'type'             => $request->type,
            'category'         => $request->category,
            'amount'           => $request->amount,
            'payment_mode'     => $request->payment_mode,
            'buyer_id'         => $request->buyer_id,
            'supplier_id'      => $request->supplier_id,
            'description'      => $request->description,
            'gst_amount'       => $request->gst_amount ?? 0,
            'tds_amount'       => $request->tds_amount ?? 0,
            'status'           => 'completed',
            'created_by'       => auth()->id(),
        ]);

        return redirect()->route('finance.tracker.index')->with('success', 'Transaction entry added to Finance Tracker.');
    }

    public function destroyTransaction(FinanceTransaction $transaction): RedirectResponse
    {
        $transaction->delete();

        return redirect()->route('finance.tracker.index')->with('success', 'Transaction deleted.');
    }
}
