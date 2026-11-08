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
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // fec, fred, polling
            $table->string('name');
            $table->string('status')->default('inactive'); // active, inactive, error
            $table->json('config')->nullable(); // API keys, date ranges, etc.
            $table->integer('record_count')->default(0);
            $table->decimal('data_quality_score', 5, 2)->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};
