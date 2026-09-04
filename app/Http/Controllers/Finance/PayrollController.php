<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\PayrollCalculatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->query('period', date('Y-m'));

        $records = PayrollRecord::query()
            ->with('user:id,name,email,employment_type,joining_date,monthly_salary')
            ->where('period', $period)
            ->get();

        $usersWithoutPayroll = User::active()
            ->whereNotIn('id', $records->pluck('user_id'))
            ->get(['id', 'name', 'employment_type', 'joining_date', 'monthly_salary']);

        $totalGross    = $records->sum('gross_salary');
        $totalDeduct   = $records->sum('total_deductions');
        $totalOvertime = $records->sum('overtime_amount');
        $totalNetPay   = $records->sum('net_pay');

        return view('finance.payroll.index', compact(
            'period', 'records', 'usersWithoutPayroll',
            'totalGross', 'totalDeduct', 'totalOvertime', 'totalNetPay'
        ));
    }

    public function process(Request $request, PayrollCalculatorService $calculator, FinanceService $financeService): RedirectResponse
    {
        $request->validate([
            'period' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $period = $request->period;
        $records = $calculator->processPeriod($period, auth()->id());

        // Auto-record HR payroll expense in Finance Tracker
        $totalNetPay = $records->sum('net_pay');
        $financeService->recordPayrollPeriod($period, $totalNetPay, auth()->id());

        return redirect()->route('finance.payroll.index', ['period' => $period])
            ->with('success', "Payroll for period {$period} processed successfully (" . count($records) . " employees).");
    }

    public function update(Request $request, PayrollRecord $payroll, PayrollCalculatorService $calculator): RedirectResponse
    {
        $request->validate([
            'working_days'    => 'required|integer|min:1|max:31',
            'days_present'    => 'required|integer|min:0|max:31',
            'normal_lop_days' => 'required|integer|min:0|max:31',
            'double_lop_days' => 'required|integer|min:0|max:31',
            'overtime_hours'  => 'required|numeric|min:0',
        ]);

        $user = $payroll->user;
        $calcData = $calculator->calculateForUser(
            $user,
            $payroll->period,
            $request->working_days,
            $request->days_present,
            $request->normal_lop_days,
            $request->double_lop_days,
            $request->overtime_hours
        );

        $payroll->update($calcData);

        return redirect()->route('finance.payroll.index', ['period' => $payroll->period])
            ->with('success', "Payroll record for {$user->name} updated.");
    }

    public function destroy(PayrollRecord $payroll): RedirectResponse
    {
        $period = $payroll->period;
        $payroll->delete();

        return redirect()->route('finance.payroll.index', ['period' => $period])
            ->with('success', 'Payroll record deleted.');
    }
}
