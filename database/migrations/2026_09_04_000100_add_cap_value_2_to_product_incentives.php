<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RoSCTL on the Product List sheet has two % columns and two cap columns
 * (parser: S/%1 U/cap1, W/%2 Y/cap2). percent_2 already existed; store the
 * second cap properly instead of stuffing it into remarks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_incentives', function (Blueprint $table) {
            $table->decimal('cap_value_2', 12, 4)->nullable()->after('cap_value');
        });
    }

    public function down(): void
    {
        Schema::table('product_incentives', function (Blueprint $table) {
            $table->dropColumn('cap_value_2');
        });
    }
};
