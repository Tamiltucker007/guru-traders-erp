<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\FinanceTransaction;
use App\Models\PayrollRecord;
use App\Models\Voucher;

class FinanceService
{
    /**
     * Record a transaction from a paid Billing Invoice.
     */
    public function recordBillingPayment(BillingInvoice $invoice, float $paymentAmount, string $paymentMode = 'Bank Transfer', ?int $createdById = null): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_date'   => now()->toDateString(),
            'type'               => 'income',
            'category'           => 'billing',
            'billing_invoice_id' => $invoice->id,
            'buyer_id'           => $invoice->buyer_id,
            'invoice_num'        => $invoice->invoice_num,
            'description'        => "Receipt for Sales Invoice #{$invoice->invoice_num}",
            'payment_mode'       => $paymentMode,
            'amount'             => $paymentAmount,
            'gst_amount'         => 0,
            'tds_amount'         => $invoice->tds_amount,
            'status'             => 'completed',
            'created_by'         => $createdById,
        ]);
    }

    /**
     * Record an expense transaction from an approved Voucher.
     */
    public function recordApprovedVoucher(Voucher $voucher, ?int $createdById = null): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_date' => now()->toDateString(),
            'type'             => 'expense',
            'category'         => $voucher->category ?? 'voucher',
            'voucher_id'       => $voucher->id,
            'voucher_num'      => $voucher->voucher_num,
            'description'      => "Approved Voucher: {$voucher->title} ({$voucher->user->name})",
            'payment_mode'     => 'Bank Transfer',
            'amount'           => $voucher->amount,
            'attachment_path'  => $voucher->receipt_path,
            'status'           => 'completed',
            'created_by'       => $createdById,
        ]);
    }

    /**
     * Record a payroll expense transaction for a processed payroll period.
     */
    public function recordPayrollPeriod(string $period, float $totalNetPay, ?int $createdById = null): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_date' => now()->toDateString(),
            'type'             => 'expense',
            'category'         => 'staff',
            'description'      => "Payroll Disbursement for Period {$period}",
            'payment_mode'     => 'Bank Transfer',
            'amount'           => $totalNetPay,
            'status'           => 'completed',
            'created_by'       => $createdById,
        ]);
    }
}
