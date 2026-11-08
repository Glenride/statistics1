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
        Schema::create('press_release_monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // 'NYT/Siena', 'Quinnipiac', etc.
            $table->string('url');
            $table->string('content_hash')->nullable();     // MD5 hash for change detection
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('press_release_monitors');
    }
};
