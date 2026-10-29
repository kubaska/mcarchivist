<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->text('payload')->nullable()->default(null)->after('uuid');
        });
    }

    public function down()
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
