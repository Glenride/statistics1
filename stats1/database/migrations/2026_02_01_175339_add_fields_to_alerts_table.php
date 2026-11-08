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
        Schema::table('alerts', function (Blueprint $table) {
            // Add new fields if they don't exist
            if (!Schema::hasColumn('alerts', 'severity')) {
                $table->string('severity')->default('info')->after('source');
            }
            if (!Schema::hasColumn('alerts', 'data')) {
                $table->json('data')->nullable()->after('description');
            }
            if (!Schema::hasColumn('alerts', 'entity_type')) {
                $table->string('entity_type')->nullable()->after('data');
            }
            if (!Schema::hasColumn('alerts', 'entity_id')) {
                $table->string('entity_id')->nullable()->after('entity_type');
            }
            if (!Schema::hasColumn('alerts', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('entity_id');
            }
            if (!Schema::hasColumn('alerts', 'dismissed_at')) {
                $table->timestamp('dismissed_at')->nullable()->after('read_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn(['severity', 'data', 'entity_type', 'entity_id', 'read_at', 'dismissed_at']);
        });
    }
};
