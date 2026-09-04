<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\BillingInvoiceItem;
use App\Models\Buyer;
use App\Models\ExportDocument;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $query = BillingInvoice::query()
            ->with(['buyer:id,company_name', 'exportDocument:id,doc_num'])
            ->latest('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_num', 'like', "%{$search}%")
                  ->orWhereHas('buyer', fn ($b) => $b->where('company_name', 'like', "%{$search}%"));
            });
        }

        $invoices = $query->paginate(20)->withQueryString();
        $buyers = Buyer::orderBy('company_name')->get(['id', 'company_name']);
        $exportDocs = ExportDocument::latest('id')->get(['id', 'doc_num']);

        // Stats summary
        $totalInvoiced = BillingInvoice::sum('total_amount');
        $totalPaid     = BillingInvoice::sum('paid_amount');
        $totalTds      = BillingInvoice::sum('tds_amount');
        $totalBalance  = BillingInvoice::sum('balance_amount');

        return view('finance.billing.index', compact(
            'invoices', 'buyers', 'exportDocs', 'status', 'search',
            'totalInvoiced', 'totalPaid', 'totalTds', 'totalBalance'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'buyer_id'           => 'required|exists:buyers,id',
            'export_document_id' => 'nullable|exists:export_documents,id',
            'invoice_date'       => 'required|date',
            'due_date'           => 'required|date|after_or_equal:invoice_date',
            'tds_percentage'     => 'nullable|numeric|min:0|max:100',
            'notes'              => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.description'=> 'required|string',
            'items.*.hsn_sac'    => 'nullable|string',
            'items.*.qty'        => 'required|numeric|min:0.01',
            'items.*.rate'       => 'required|numeric|min:0',
        ]);

        $invoiceNum = 'INV-' . date('Y') . '-' . str_pad((string) (BillingInvoice::max('id') + 1), 4, '0', STR_PAD_LEFT);

        $invoice = BillingInvoice::create([
            'invoice_num'        => $invoiceNum,
            'buyer_id'           => $request->buyer_id,
            'export_document_id' => $request->export_document_id,
            'invoice_date'       => $request->invoice_date,
            'due_date'           => $request->due_date,
            'tds_percentage'     => $request->tds_percentage ?? 0,
            'notes'              => $request->notes,
            'created_by'         => auth()->id(),
        ]);

        foreach ($request->items as $item) {
            $amount = round($item['qty'] * $item['rate'], 2);
            $invoice->items()->create([
                'description' => $item['description'],
                'hsn_sac'     => $item['hsn_sac'] ?? null,
                'qty'         => $item['qty'],
                'rate'        => $item['rate'],
                'amount'      => $amount,
            ]);
        }

        $invoice->recalculateTotals();

        return redirect()->route('finance.billing.index')->with('success', "Invoice {$invoiceNum} created successfully.");
    }

    public function recordPayment(Request $request, BillingInvoice $billing, FinanceService $financeService): RedirectResponse
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_mode'   => 'required|string',
        ]);

        $billing->paid_amount += $request->payment_amount;
        $billing->recalculateTotals();

        // Create transaction entry in Finance Tracker
        $financeService->recordBillingPayment($billing, $request->payment_amount, $request->payment_mode, auth()->id());

        return redirect()->route('finance.billing.index')->with('success', "Payment recorded for Invoice {$billing->invoice_num}.");
    }

    public function destroy(BillingInvoice $billing): RedirectResponse
    {
        $billing->delete();

        return redirect()->route('finance.billing.index')->with('success', 'Invoice deleted successfully.');
    }
}
