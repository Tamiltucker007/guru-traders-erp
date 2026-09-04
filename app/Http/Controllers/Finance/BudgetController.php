<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BudgetTarget;
use App\Models\FinanceTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request): View
    {
        $periodType = $request->query('period_type', 'month');
        $periodVal = $request->query('period_value', date('Y-m'));

        $targets = BudgetTarget::query()
            ->where('period_type', $periodType)
            ->where('period_value', $periodVal)
            ->get();

        // Calculate actual spend per category/vertical from FinanceTransaction
        $actualSpends = FinanceTransaction::query()
            ->where('type', 'expense')
            ->where('status', 'completed')
            ->selectRaw('category, SUM(amount) as total_spend')
            ->groupBy('category')
            ->pluck('total_spend', 'category');

        $categories = [
            'material'     => 'Material & Fabric',
            'staff'        => 'Staff & HR / Payroll',
            'rent'         => 'Rent & Utilities',
            'marketing'    => 'Marketing & Sales',
            'logistics'    => 'Logistics & IT',
            'admin'        => 'Admin Overhead',
            'consulting'   => 'Consulting & Legal',
            'voucher'      => 'Vouchers',
            'depreciation' => 'Depreciation',
            'interest'     => 'Interest & Bank Charges',
            'tax'          => 'Taxes & Statutory',
            'other'        => 'Other Operations',
        ];

        // Check untagged expenses count
        $untaggedCount = FinanceTransaction::where('type', 'expense')->whereNull('category')->count();

        $totalBudgetTarget = $targets->sum('target_amount');
        $totalActualSpend  = $actualSpends->sum();

        return view('finance.budget.index', compact(
            'periodType', 'periodVal', 'targets', 'actualSpends',
            'categories', 'untaggedCount', 'totalBudgetTarget', 'totalActualSpend'
        ));
    }

    public function storeTarget(Request $request): RedirectResponse
    {
        $request->validate([
            'period_type'          => 'required|in:month,quarter,fy',
            'period_value'         => 'required|string',
            'category_or_vertical' => 'required|string',
            'target_amount'        => 'required|numeric|min:0',
        ]);

        BudgetTarget::updateOrCreate(
            [
                'period_type'          => $request->period_type,
                'period_value'         => $request->period_value,
                'category_or_vertical' => $request->category_or_vertical,
            ],
            [
                'target_amount' => $request->target_amount,
                'created_by'    => auth()->id(),
            ]
        );

        return redirect()->route('finance.budget.index', [
            'period_type'  => $request->period_type,
            'period_value' => $request->period_value,
        ])->with('success', 'Budget target updated successfully.');
    }
}
