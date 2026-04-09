<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('master_projects', function (Blueprint $table) {
            $table->foreign('preferred_project_id')->references('id')->on('projects');
        });
    }

    public function down()
    {
        Schema::table('master_projects', function (Blueprint $table) {
            $table->dropForeign(['preferred_project_id']);
        });
    }
};
