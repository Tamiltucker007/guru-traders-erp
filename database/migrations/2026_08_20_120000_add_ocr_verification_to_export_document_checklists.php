<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_document_checklists', function (Blueprint $table) {
            $table->json('ocr_verification')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('export_document_checklists', function (Blueprint $table) {
            $table->dropColumn('ocr_verification');
        });
    }
};
