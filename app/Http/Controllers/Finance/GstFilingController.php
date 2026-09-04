<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\GstFiling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GstFilingController extends Controller
{
    public function index(Request $request): View
    {
        $fy = $request->query('fy', '2026-27');

        $filings = GstFiling::where('financial_year', $fy)
            ->orderBy('period', 'desc')
            ->get();

        return view('finance.gst.index', compact('filings', 'fy'));
    }

    public function updateStatus(Request $request, GstFiling $gst): RedirectResponse
    {
        $request->validate([
            'gstr1_status'       => 'required|in:pending,filed,overdue',
            'gstr1_filed_at'     => 'nullable|date',
            'gstr3b_status'      => 'required|in:pending,filed,overdue',
            'gstr3b_filed_at'    => 'nullable|date',
            'total_sales_tax'    => 'nullable|numeric|min:0',
            'total_purchase_tax' => 'nullable|numeric|min:0',
            'notes'              => 'nullable|string',
        ]);

        $salesTax = $request->total_sales_tax ?? $gst->total_sales_tax;
        $purchaseTax = $request->total_purchase_tax ?? $gst->total_purchase_tax;
        $netPayable = max(0, $salesTax - $purchaseTax);

        $gst->update([
            'gstr1_status'       => $request->gstr1_status,
            'gstr1_filed_at'     => $request->gstr1_filed_at,
            'gstr3b_status'      => $request->gstr3b_status,
            'gstr3b_filed_at'    => $request->gstr3b_filed_at,
            'total_sales_tax'    => $salesTax,
            'total_purchase_tax' => $purchaseTax,
            'net_gst_payable'    => $netPayable,
            'notes'              => $request->notes,
        ]);

        return redirect()->back()->with('success', "GST filing status for period {$gst->period} updated.");
    }
}
