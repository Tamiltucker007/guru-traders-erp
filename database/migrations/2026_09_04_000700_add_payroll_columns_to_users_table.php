<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'joining_date')) {
                $table->date('joining_date')->nullable();
            }
            if (!Schema::hasColumn('users', 'employment_type')) {
                $table->string('employment_type', 30)->default('Full-Time');
            }
            if (!Schema::hasColumn('users', 'monthly_salary')) {
                $table->decimal('monthly_salary', 15, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['joining_date', 'employment_type', 'monthly_salary']);
        });
    }
};
