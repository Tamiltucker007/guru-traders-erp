<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OCR can resolve scanned buyer / supplier names onto masters; store the
 * matched ids on the checklist row so Save keeps the party link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_document_checklists', function (Blueprint $table) {
            $table->foreignId('matched_buyer_id')
                ->nullable()
                ->after('remarks')
                ->constrained('buyers')
                ->nullOnDelete();

            $table->foreignId('matched_supplier_id')
                ->nullable()
                ->after('matched_buyer_id')
                ->constrained('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('export_document_checklists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matched_buyer_id');
            $table->dropConstrainedForeignId('matched_supplier_id');
        });
    }
};
