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
            // FEC-specific fields for complete candidate data
            $table->string('candidate_id')->nullable()->after('id'); // FEC unique identifier
            $table->string('party_full')->nullable()->after('party'); // Full party name
            $table->string('incumbent_challenge', 10)->nullable()->after('party_full'); // I=Incumbent, C=Challenger
            $table->decimal('individual_contributions', 15, 2)->nullable()->after('total_disbursements');
            $table->decimal('pac_contributions', 15, 2)->nullable()->after('individual_contributions');
            $table->decimal('debts_owed', 15, 2)->nullable()->after('pac_contributions');
            $table->date('coverage_start')->nullable()->after('source');
            $table->date('coverage_end')->nullable()->after('coverage_start');
            
            // Add index for faster lookups
            $table->index('candidate_id');
            $table->index('party');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unified_datasets', function (Blueprint $table) {
            $table->dropIndex(['candidate_id']);
            $table->dropIndex(['party']);
            $table->dropColumn([
                'candidate_id',
                'party_full',
                'incumbent_challenge',
                'individual_contributions',
                'pac_contributions',
                'debts_owed',
                'coverage_start',
                'coverage_end',
            ]);
        });
    }
};
