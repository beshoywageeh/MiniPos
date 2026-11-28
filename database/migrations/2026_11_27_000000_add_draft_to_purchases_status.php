<?php

use App\Enums\DocumentStatus;
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
            $table->enum('status', ['draft', 'pending', 'paid', 'partial'])
                ->default('pending')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('purchases')
            ->where('status', DocumentStatus::DRAFT->value)
            ->update(['status' => DocumentStatus::PENDING->value]);

        Schema::table('purchases', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'paid', 'partial'])
                ->default('pending')
                ->change();
        });
    }
};
