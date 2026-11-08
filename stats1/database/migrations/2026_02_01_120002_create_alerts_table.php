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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type');                         // 'new_poll', 'page_change', 'new_pdf'
            $table->string('source');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            
            $table->index('type');
            $table->index('is_read');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
