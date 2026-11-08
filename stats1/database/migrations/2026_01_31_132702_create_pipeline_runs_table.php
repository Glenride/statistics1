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
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('records_processed')->default(0);
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->json('connectors_run')->nullable(); // Which connectors were included
            $table->json('date_range')->nullable(); // Start and end date for the run
            $table->text('error_log')->nullable();
            $table->string('triggered_by')->default('manual'); // manual, scheduled
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
    }
};
