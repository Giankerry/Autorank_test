<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('professional_developments', function (Blueprint $table) {
        $table->string('extracted_credential_type')->nullable()->after('extracted_name_on_cert');
    });
}

public function down()
{
    Schema::table('professional_developments', function (Blueprint $table) {
        $table->dropColumn('extracted_credential_type');
    });
}

};
