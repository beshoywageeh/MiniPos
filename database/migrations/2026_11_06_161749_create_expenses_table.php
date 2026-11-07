<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->string('document_number', 50)->unique();

            // PHP ExpenseCategory Enum
            $table->string('expense_category', 50);

            $table->foreignId('cashbox_id')
                ->constrained('cashboxes')
                ->restrictOnDelete();

            $table->decimal('amount', 15, 2);

            $table->date('expense_date');

            $table->string('description')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('expense_date');
            $table->index('expense_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
