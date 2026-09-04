<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->string('type', 20); // income, expense
            $table->string('category', 50); // billing, consulting, material, staff, rent, marketing, logistics, admin, depreciation, interest, tax, voucher, other
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->unsignedBigInteger('billing_invoice_id')->nullable();
            $table->foreignId('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('voucher_num')->nullable();
            $table->string('invoice_num')->nullable();
            $table->text('description')->nullable();
            $table->string('payment_mode', 30)->default('Bank Transfer'); // Bank Transfer, Cash, UPI, Cheque, Credit Card
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('gst_amount', 15, 2)->default(0);
            $table->decimal('tds_amount', 15, 2)->default(0);
            $table->string('status', 30)->default('completed'); // completed, pending
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');
    }
};
