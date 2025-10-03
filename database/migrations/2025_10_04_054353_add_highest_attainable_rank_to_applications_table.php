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
        Schema::table('applications', function (Blueprint $table) {
            // Rename general_score to final_score
            $table->renameColumn('general_score', 'final_score');

            // Add the new column to store the final rank determination
            $table->string('highest_attainable_rank')->nullable()->after('final_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('highest_attainable_rank');
            $table->renameColumn('final_score', 'general_score');
        });
    }
};
