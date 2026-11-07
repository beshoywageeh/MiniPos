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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->string('document_number', 50)->unique();

            $table->foreignId('party_id')
                ->nullable()
                ->constrained('parties')
                ->restrictOnDelete();

            $table->foreignId('cashbox_id')
                ->constrained('cashboxes')
                ->restrictOnDelete();

            $table->foreignId('sale_id')
                ->nullable()
                ->constrained('sales')
                ->restrictOnDelete();

            $table->foreignId('purchase_id')
                ->nullable()
                ->constrained('purchases')
                ->restrictOnDelete();

            $table->foreignId('expense_id')
                ->nullable()
                ->constrained('expenses')
                ->restrictOnDelete();

            $table->string('type', 20);
            $table->string('method', 30);

            $table->decimal('amount', 15, 2);

            $table->dateTime('payment_date');

            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('payment_date');
            $table->index('type');
            $table->index(['party_id', 'payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
