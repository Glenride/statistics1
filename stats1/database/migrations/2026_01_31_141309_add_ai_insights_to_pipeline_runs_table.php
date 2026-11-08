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
        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->text('ai_insights')->nullable()->after('error_log');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->dropColumn('ai_insights');
        });
    }
};
