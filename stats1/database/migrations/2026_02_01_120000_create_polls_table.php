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
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->string('poll_id')->unique();           // External ID from 538/RCP
            $table->string('source');                       // '538', 'rcp', 'nyt', etc.
            $table->string('pollster');
            $table->string('race_type');                    // 'presidential', 'senate', etc.
            $table->string('state')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('sample_size')->nullable();
            $table->string('fte_grade')->nullable();        // FiveThirtyEight grade
            $table->string('source_type')->default('standard'); // 'standard', 'r_internal', 'd_internal'
            $table->integer('trust_score')->default(50);
            $table->json('candidates_data');                // JSON with candidate results
            $table->string('content_hash')->nullable();     // For detecting updates
            $table->boolean('needs_review')->default(false);
            $table->timestamps();
            
            $table->index(['source', 'race_type']);
            $table->index(['state', 'race_type']);
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
