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
        Schema::table('unified_datasets', function (Blueprint $table) {
            // Polling-specific fields
            $table->string('poll_source', 50)->nullable()->after('poll_avg'); // 538, RCP, simulated
            $table->integer('poll_count')->nullable()->after('poll_source'); // Number of polls averaged
            $table->string('poll_trend', 10)->nullable()->after('poll_count'); // up, down, stable
            $table->decimal('poll_high', 5, 2)->nullable()->after('poll_trend'); // Highest recent poll
            $table->decimal('poll_low', 5, 2)->nullable()->after('poll_high'); // Lowest recent poll
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unified_datasets', function (Blueprint $table) {
            $table->dropColumn([
                'poll_source',
                'poll_count',
                'poll_trend',
                'poll_high',
                'poll_low',
            ]);
        });
    }
};
