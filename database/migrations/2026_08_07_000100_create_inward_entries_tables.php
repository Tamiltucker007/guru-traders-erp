<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inward_entries', function (Blueprint $table) {
            $table->id();
            $table->string('inward_no')->unique();
            $table->string('financial_year', 10);

            $table->date('inward_date');
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();

            $table->string('challan_no')->nullable();
            $table->date('challan_date')->nullable();
            $table->text('remarks')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamp('qc_inspected_at')->nullable();
            $table->foreignId('qc_inspected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('financial_year');
            $table->index('inward_date');
        });

        Schema::create('inward_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inward_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->text('description')->nullable();
            $table->string('unit', 50)->nullable();

            $table->unsignedInteger('ordered_qty')->default(0);
            $table->unsignedInteger('received_qty')->default(0);
            $table->unsignedInteger('passed_qty')->default(0);
            $table->unsignedInteger('rejected_qty')->default(0);

            $table->text('remarks')->nullable();
            $table->text('qc_remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inward_entry_items');
        Schema::dropIfExists('inward_entries');
    }
};
