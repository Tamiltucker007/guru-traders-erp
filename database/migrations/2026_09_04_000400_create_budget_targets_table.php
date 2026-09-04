<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_targets', function (Blueprint $table) {
            $table->id();
            $table->string('period_type', 20)->default('month'); // month, quarter, fy
            $table->string('period_value', 20); // e.g. 2026-09, 2026-Q3, 2026-27
            $table->string('category_or_vertical', 50); // material, staff, rent, marketing, logistics, admin, consulting, depreciation, interest, tax, other
            $table->decimal('target_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['period_type', 'period_value', 'category_or_vertical'], 'budget_targets_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_targets');
    }
};
