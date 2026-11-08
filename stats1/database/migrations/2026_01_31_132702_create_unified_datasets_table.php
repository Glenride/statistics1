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
        Schema::create('unified_datasets', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('candidate')->nullable();
            $table->string('party')->nullable();
            $table->decimal('poll_avg', 5, 2)->nullable(); // Polling average percentage
            $table->decimal('cash_on_hand', 15, 2)->nullable(); // FEC cash on hand
            $table->decimal('total_receipts', 15, 2)->nullable(); // FEC total receipts
            $table->decimal('total_disbursements', 15, 2)->nullable(); // FEC disbursements
            $table->decimal('inflation_rate', 5, 2)->nullable(); // FRED CPI
            $table->decimal('unemployment_rate', 5, 2)->nullable(); // FRED unemployment
            $table->decimal('gdp_growth', 5, 2)->nullable(); // FRED GDP
            $table->decimal('consumer_confidence', 8, 2)->nullable(); // FRED consumer confidence
            $table->string('source')->nullable(); // Which connector provided this row
            $table->foreignId('pipeline_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'candidate']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unified_datasets');
    }
};
