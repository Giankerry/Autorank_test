<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_developments', function (Blueprint $table) {
            $table->string('extracted_issue_date')->nullable();
            $table->string('extracted_issuer')->nullable();
            $table->string('extracted_name_on_cert')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('professional_developments', function (Blueprint $table) {
            $table->dropColumn(['extracted_issue_date', 'extracted_issuer', 'extracted_name_on_cert']);
        });
    }
};
