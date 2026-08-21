<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OCR also resolves the scan onto an Order Confirmation (via buyer + invoice /
 * buyer-ref), so Save keeps the OC link beside buyer/supplier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_document_checklists', function (Blueprint $table) {
            $table->foreignId('matched_order_confirmation_id')
                ->nullable()
                ->after('matched_supplier_id')
                ->constrained('order_confirmations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('export_document_checklists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matched_order_confirmation_id');
        });
    }
};
