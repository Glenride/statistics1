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
        Schema::create('scraped_data', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // e.g., 'ballotpedia', 'rcp'
            $table->string('data_type'); // e.g., 'election', 'poll', 'event'
            $table->string('title')->nullable();
            $table->text('url')->nullable();
            $table->longText('content')->nullable(); // JSON data
            $table->timestamp('scraped_at')->nullable();
            $table->foreignId('pipeline_run_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->index(['source', 'data_type']);
            $table->index('scraped_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraped_data');
    }
};
