<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->string('period', 10); // YYYY-MM
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('employment_type', 30)->default('Full-Time'); // Full-Time, Part-Time
            $table->date('joining_date')->nullable();
            $table->decimal('monthly_salary', 15, 2)->default(0);
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->integer('working_days')->default(26);
            $table->integer('days_present')->default(26);
            $table->integer('normal_lop_days')->default(0);
            $table->integer('double_lop_days')->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('overtime_amount', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->string('status', 30)->default('draft'); // draft, processed, paid
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['period', 'user_id'], 'payroll_records_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
    }
};
