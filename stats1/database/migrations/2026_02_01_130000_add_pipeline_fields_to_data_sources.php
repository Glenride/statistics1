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
        Schema::table('data_sources', function (Blueprint $table) {
            $table->string('category')->default('core')->after('type'); // core, polling, custom
            $table->boolean('is_custom')->default(false)->after('category');
            $table->boolean('pipeline_enabled')->default(true)->after('is_custom');
            $table->string('sync_handler')->nullable()->after('config'); // Service class for syncing
            $table->text('description')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn(['category', 'is_custom', 'pipeline_enabled', 'sync_handler', 'description']);
        });
    }
};
