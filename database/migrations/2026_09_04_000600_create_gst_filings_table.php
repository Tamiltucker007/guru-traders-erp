<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_filings', function (Blueprint $table) {
            $table->id();
            $table->string('period', 10); // YYYY-MM
            $table->string('financial_year', 15); // e.g. 2026-27
            $table->string('gstr1_status', 30)->default('pending'); // pending, filed, overdue
            $table->date('gstr1_filed_at')->nullable();
            $table->string('gstr3b_status', 30)->default('pending'); // pending, filed, overdue
            $table->date('gstr3b_filed_at')->nullable();
            $table->decimal('total_sales_tax', 15, 2)->default(0);
            $table->decimal('total_purchase_tax', 15, 2)->default(0);
            $table->decimal('net_gst_payable', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['period'], 'gst_filings_unique_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_filings');
    }
};
