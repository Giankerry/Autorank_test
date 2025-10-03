<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the old rank column
            $table->dropColumn('rank');

            // Rename ahp_score to general_score
            if (Schema::hasColumn('users', 'ahp_score')) {
                $table->renameColumn('ahp_score', 'general_score');
            }

            // Ensure faculty_rank exists and default is "Unset"
            if (!Schema::hasColumn('users', 'faculty_rank')) {
                $table->string('faculty_rank')->default('Unset')->after('instructor_number');
            } else {
                $table->string('faculty_rank')->default('Unset')->change();
            }

            // Add new rank_assigned_at and rank_assigned_by
            $table->timestamp('rank_assigned_at')->nullable()->after('faculty_rank');
            $table->string('rank_assigned_by')->default('N/A')->after('rank_assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'general_score')) {
                $table->renameColumn('general_score', 'ahp_score');
            }

            // Recreate rank column
            $table->string('rank')->nullable()->after('instructor_number');

            // Drop faculty_rank default back to nullable if needed
            $table->string('faculty_rank')->nullable()->change();

            // Drop rank_assigned_at and rank_assigned_by
            $table->dropColumn(['rank_assigned_at', 'rank_assigned_by']);
        });
    }
};
