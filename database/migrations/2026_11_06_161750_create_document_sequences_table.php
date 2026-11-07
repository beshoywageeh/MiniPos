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
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();

            $table->string('document_type', 50)->unique();
            $table->string('prefix', 20);

            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedInteger('padding')->default(6);

            // PHP ResetPeriod Enum
            $table->string('reset_period', 20)->default('never');

            $table->timestamp('last_reset_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
