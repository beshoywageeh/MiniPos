<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->date('due_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('purchases')
            ->whereNull('due_date')
            ->update(['due_date' => DB::raw('invoice_date')]);

        Schema::table('purchases', function (Blueprint $table): void {
            $table->date('due_date')->nullable(false)->change();
        });
    }
};
