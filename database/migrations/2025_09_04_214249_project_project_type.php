<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('project_project_type', function (Blueprint $table) {
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('project_type_id');

            $table->foreign('project_id')->references('id')->on('projects');
            $table->foreign('project_type_id')->references('id')->on('project_types');
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_project_type');
    }
};
