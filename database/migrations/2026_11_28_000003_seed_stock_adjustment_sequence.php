<?php

use App\Enums\ResetPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('document_sequences')->insertOrIgnore([
            'document_type' => 'stock_adjustment',
            'prefix' => 'STADJ',
            'next_number' => 1,
            'padding' => 6,
            'reset_period' => ResetPeriod::MONTHLY->value,
            'last_reset_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('document_sequences')
            ->where('document_type', 'stock_adjustment')
            ->delete();
    }
};
